<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\PhpRedis.
 */

namespace Drupal\redis\Cache;

use Drupal\redis\CacheBase;
use Drupal\redis\ClientFactory;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * PhpRedis cache backend.
 */
class PhpRedis extends CacheBase {

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {

    $client = ClientFactory::getClient();
    $key    = $this->getKey($cid);

    list($cached, $deleted, $stale) = $client->multi(\Redis::PIPELINE)
      ->get($key)
      ->sismember($this->getDeletedMetaSet(), $key)
      ->sismember($this->getStaleMetaSet(), $key)
      ->exec();

    if (!empty($cached) && !$deleted) {
      $cached = unserialize($cached);
      $cached->valid = ($cached->expire == Cache::PERMANENT || $cached->expire >= REQUEST_TIME) && !$stale;
      if ($allow_invalid || $cached->valid) {
        return $cached;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {

    $client = ClientFactory::getClient();

    $ret = array();
    $keys = array_map(array($this, 'getKey'), $cids);

    $pipe = $client->multi(\Redis::PIPELINE);
    foreach ($keys as $key) {
      $pipe->get($key);
      $pipe->sismember($this->getDeletedMetaSet(), $key);
      $pipe->sismember($this->getStaleMetaSet(), $key);
    }
    $replies = $pipe->exec();

    foreach (array_chunk($replies, 3) as $tuple) {
      list($cached, $deleted, $stale) = $tuple;
      if (!empty($cached) && !$deleted) {
        $cached = unserialize($cached);
        $cached->valid = ($cached->expire == Cache::PERMANENT || $cached->expire >= REQUEST_TIME) && !$stale;
        if ($allow_invalid || $cached->valid) {
          $ret[$cached->cid] = $cached;
        }
      }
    }

    foreach ($cids as $index => $cid) {
      if (isset($ret[$cid])) {
        unset($cids[$index]);
      }
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = array()) {
    $entry = (object) array(
      'cid' => $cid,
      'created' => REQUEST_TIME,
      'expire' => $expire,
      'data' => $data,
      'tags' => $this->flattenTags($tags),
    );
    $this->replace($this->getKey($cid), $entry);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      $this->set($cid, $item['data'], isset($item['expire']) ? $item['expire'] : CacheBackendInterface::CACHE_PERMANENT, isset($item['tags']) ? $item['tags'] : array());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $client = ClientFactory::getClient();
    $client->sadd($this->getDeletedMetaSet(), $this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $client = ClientFactory::getClient();
    $pipe = $client->multi(\Redis::PIPELINE);
    foreach ($cids as $cid) {
      $pipe->sadd($this->getDeletedMetaSet(), $this->getKey($cid));
    }
    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTags(array $tags) {
    $client = ClientFactory::getClient();
    $pipe = $client->multi(\Redis::PIPELINE);
    foreach ($this->flattenTags($tags) as $tag) {
      $pipe->sunionstore($this->getDeletedMetaSet(), $this->getKeysByTagSet($tag));
    }
    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $tag = $this->getTagForBin();
    $this->deleteTags(array($tag));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $client = ClientFactory::getClient();
    $client->sadd($this->getStaleMetaSet(), $this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $client = ClientFactory::getClient();
    $pipe = $client->multi(\Redis::PIPELINE);
    foreach ($cids as $cid) {
      $pipe->sadd($this->getStaleMetaSet(), $this->getKey($cid));
    }
    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    $client = ClientFactory::getClient();
    $pipe = $client->multi(\Redis::PIPELINE);
    foreach ($this->flattenTags($tags) as $tag) {
      $pipe->sunionstore($this->getStaleMetaSet(), $this->getKeysByTagSet($tag));
    }
    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $tag = $this->getTagForBin();
    $this->invalidateTags(array($tag));
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function garbageCollection() {
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->deleteAll();
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function isEmpty() {
  }

  /**
   * Replace or remove a cache entry.
   */
  protected function replace($key, $entry = NULL) {
    $client = ClientFactory::getClient();

    $client->watch($key);
    $old_tags = $client->smembers($this->getTagsByKeySet($key));

    $pipe = $client->multi(\Redis::MULTI);

    // Remove.
    $pipe->del($key);
    $pipe->del($this->getTagsByKeySet($key));
    foreach ($old_tags as $tag) {
      $pipe->srem($this->getKeysByTagSet($tag), $key);
    }
    $pipe->srem($this->getDeletedMetaSet($key), $key);
    $pipe->srem($this->getStaleMetaSet($key), $key);

    // Insert.
    if ($entry) {
      $pipe->set($key, serialize($entry));
      $pipe->sadd($this->getTagsByKeySet($key), $this->getTagForBin());
      $pipe->sadd($this->getKeysByTagSet($this->getTagForBin()), $key);
      foreach ($this->flattenTags($entry->tags) as $tag) {
        $pipe->sadd($this->getTagsByKeySet($key), $tag);
        $pipe->sadd($this->getKeysByTagSet($tag), $key);
      }

      if ($entry->expire == Cache::PERMANENT) {
        $ttl = $this->getPermTtl();
        if ($ttl !== 0) {
          $pipe->expire($key, $ttl);
          $pipe->expire($this->getTagsByKeySet($key), $ttl);
        }
      }
      else {
        $ttl = max(0, $entry->expire - REQUEST_TIME);
        $pipe->expire($key, $ttl);
        $pipe->expire($this->getTagsByKeySet($key), $ttl);
      }
    }

    return $pipe->exec();
  }

  /**
   * 'Flattens' a tags array into an array of strings.
   *
   * @param array $tags
   *   Associative array of tags to flatten.
   *
   * @return array
   *   An indexed array of flattened tag identifiers.
   */
  protected function flattenTags(array $tags) {
    if (isset($tags[0])) {
      return $tags;
    }

    $flat_tags = array();
    foreach ($tags as $namespace => $values) {
      if (is_array($values)) {
        foreach ($values as $value) {
          $flat_tags[] = "$namespace:$value";
        }
      }
      else {
        $flat_tags[] = "$namespace:$values";
      }
    }
    return $flat_tags;
  }

}
