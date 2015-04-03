<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\PhpRedis.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * PhpRedis cache backend.
 */
class PhpRedis extends CacheBase {

  /**
   * @var \Redis
   */
  protected $client;

  /**
   * Creates a PHpRedis cache backend.
   */
  function __construct($bin, \Redis $client) {
    parent::__construct($bin);
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $key = $this->getKey($cid);

    list($cached, $deleted, $stale) = $this->client->multi(\Redis::PIPELINE)
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
    $ret = array();
    $keys = array_map(array($this, 'getKey'), $cids);

    $pipe = $this->client->multi(\Redis::PIPELINE);
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
    Cache::validateTags($tags);
    $entry = (object) array(
      'cid' => $cid,
      'created' => REQUEST_TIME,
      'expire' => $expire,
      'data' => $data,
      'tags' => $tags,
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
    $this->client->sadd($this->getDeletedMetaSet(), $this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $pipe = $this->client->multi(\Redis::PIPELINE);
    foreach ($cids as $cid) {
      $pipe->sadd($this->getDeletedMetaSet(), $this->getKey($cid));
    }
    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    // The first entry is where to store, the second is the same,
    // so that existing entries are kept.
    $this->client->sUnionStore($this->getDeletedMetaSet(), $this->getDeletedMetaSet(), $this->getKeysByTagSet($this->getTagForBin()));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->client->sadd($this->getStaleMetaSet(), $this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $pipe = $this->client->multi(\Redis::PIPELINE);
    foreach ($cids as $cid) {
      $pipe->sadd($this->getStaleMetaSet(), $this->getKey($cid));
    }
    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    // The first entry is where to store, the second is the same,
    // so that existing entries are kept.
    $this->client->sUnionStore($this->getStaleMetaSet(), $this->getStaleMetaSet(), $this->getKeysByTagSet($this->getTagForBin()));
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    $n = $this->client->scard($this->getDeletedMetaSet());
    for ($i = 0; $i < $n; $i++) {
      $this->client->watch($this->getDeletedMetaSet());
      $key = $this->client->srandmember($this->getDeletedMetaSet());
      if ($key) {
        $this->replace($key);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->deleteAll();
  }

  /**
   * Replace or remove a cache entry.
   */
  protected function replace($key, $entry = NULL) {
    $this->client->watch($key);
    $old_tags = $this->client->smembers($this->getTagsByKeySet($key));

    $serialized = NULL;
    if ($entry) {
      // Serialize the data before entering the transaction, as this could
      // could call __sleep() implementations that might load data from the
      // cache too.
      $serialized = serialize($entry);
    }

    $pipe = $this->client->multi(\Redis::MULTI);

    // Remove.
    $pipe->del($key);
    $pipe->del($this->getTagsByKeySet($key));
    foreach ($old_tags as $tag) {
      $pipe->srem($this->getKeysByTagSet($tag), $key);
    }
    $pipe->srem($this->getDeletedMetaSet($key), $key);
    $pipe->srem($this->getStaleMetaSet($key), $key);

    // Insert.
    if ($serialized) {
      $pipe->set($key, $serialized);
      $pipe->sadd($this->getTagsByKeySet($key), $this->getTagForBin());
      $pipe->sadd($this->getKeysByTagSet($this->getTagForBin()), $key);
      foreach ($entry->tags as $tag) {
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
        $ttl = max($this->minTtl, $entry->expire - REQUEST_TIME);
        $pipe->expire($key, $ttl);
        $pipe->expire($this->getTagsByKeySet($key), $ttl);
      }
    }

    return $pipe->exec();
  }

}
