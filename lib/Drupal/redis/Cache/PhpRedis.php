<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\PhpRedis.
 */

namespace Drupal\redis\Cache;

use Drupal\redis\CacheBase;
use Drupal\redis\ClientFactory;
use Drupal\Core\Cache\Cache;

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

    $cached = $client->hgetall($key);

    // Recent versions of PhpRedis will return the Redis instance
    // instead of an empty array when the HGETALL target key does
    // not exists. I see what you did there.
    if (empty($cached) || !is_array($cached)) {
      return FALSE;
    }

    $cached = (object)$cached;

    if ($cached->serialized) {
      $cached->data = unserialize($cached->data);
    }

    return $cached;
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
      $pipe->hgetall($key);
    }
    $replies = $pipe->exec();

    foreach ($replies as $reply) {
      if (!empty($reply)) {
        $cached = (object)$reply;

        if ($cached->serialized) {
          $cached->data = unserialize($cached->data);
        }

        $ret[$cached->cid] = $cached;
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
   *
   * @todo: Add tags to a special hash (tag -> cid)
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = array()) {

    $client = ClientFactory::getClient();
    $key    = $this->getKey($cid);

    $hash = array(
      'cid' => $cid,
      'created' => time(),
      'expire' => $expire,
    );

    // Let Redis handle the data types itself.
    if (!is_scalar($data)) {
      $hash['data'] = serialize($data);
      $hash['serialized'] = 1;
    }
    else {
      $hash['data'] = $data;
      $hash['serialized'] = 0;
    }

    $pipe = $client->multi(\Redis::PIPELINE);
    $pipe->hmset($key, $hash);

    if ($expire == Cache::PERMANENT) {
      $ttl = $this->getPermTtl();
      if ($ttl !== 0) {
        $pipe->expire($key, $ttl);
      }
    }
    else {
      $ttl = $expire - REQUEST_TIME;
      $pipe->expire($key, max(0, $ttl));
    }

    $pipe->exec();
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
    $keys   = array();
    $client = ClientFactory::getClient();

    // Single key drop.
    $keys[] = $this->getKey($cid);
    $client->del($keys);
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function deleteMultiple(array $cids) {
    foreach ($cids as $cid) {
      $this->delete($cid);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function deleteTags(array $tags) {
    $this->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $keys   = array();
    $client = ClientFactory::getClient();


    $remoteKeys = $client->keys($this->getKey('*'));
    // PhpRedis seems to suffer of some bugs.
    if (!empty($remoteKeys) && is_array($remoteKeys)) {
      $keys = array_merge($keys, $remoteKeys);
    }

    if (!empty($keys)) {
      if (count($keys) < CacheBase::KEY_THRESHOLD) {
        $client->del($keys);
      } else {
        $pipe = $client->multi(\Redis::PIPELINE);
        do {
          $buffer = array_splice($keys, 0, CacheBase::KEY_THRESHOLD);
          $pipe->del($buffer);
        } while (!empty($keys));
        $pipe->exec();
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function invalidate($cid) {
    $this->delete($cid);
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function invalidateMultiple(array $cids) {
    $this->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function invalidateTags(array $tags) {
    $this->deleteTags($tags);
  }

  /**
   * {@inheritdoc}
   *
   * @todo: implement
   */
  public function invalidateAll() {
    $this->deleteAll();
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
   *
   * @todo: implement
   */
  public function removeBin() {
  }

  function isEmpty() {
    // FIXME: Todo.
  }

}
