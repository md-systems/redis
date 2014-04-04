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
    $skey   = $this->getKey(CacheBase::TEMP_SET);
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

    switch ($expire) {

//      case CACHE_TEMPORARY:
//        $lifetime = variable_get('cache_lifetime', CacheBase::LIFETIME_DEFAULT);
//        if (0 < $lifetime) {
//          $pipe->expire($key, $lifetime);
//        }
//        $pipe->sadd($skey, $cid);
//        break;

      case Cache::PERMANENT:
        if (0 !== ($ttl = $this->getPermTtl())) {
          $pipe->expire($key, $ttl);
        }
        // We dont need the PERSIST command, since it's the default.
        break;

      default:
        // If caller gives us an expiry timestamp in the past
        // the key will expire now and will never be read.
        $ttl = $expire - time();
        if ($ttl < 0) {
          // Behavior between Predis and PhpRedis seems to change here: when
          // setting a negative expire time, PhpRedis seems to ignore the
          // command and leave the key permanent.
          $pipe->expire($key, 0);
        } else {
          $pipe->expire($key, $ttl);
          $pipe->sadd($skey, $cid);
        }
        break;
    }

    $pipe->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $keys   = array();
    $skey   = $this->getKey(CacheBase::TEMP_SET);
    $client = ClientFactory::getClient();

    // Single key drop.
    $keys[] = $key = $this->getKey($cid);
    $client->srem($skey, $key);

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
    $skey   = $this->getKey(CacheBase::TEMP_SET);
    $client = ClientFactory::getClient();

    $keys[] = $skey;

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

//  /**
//   * @todo: Remove this is only here for referenece.
//   */
//  function clear($cid = NULL, $wildcard = FALSE) {
//
//    $keys   = array();
//    $skey   = $this->getKey(CacheBase::TEMP_SET);
//    $client = ClientFactory::getClient();
//
//    if (NULL === $cid) {
//      switch ($this->getClearMode()) {
//
//        // One and only case of early return.
//        case CacheBase::FLUSH_NOTHING:
//          return;
//
//        // Default behavior.
//        case CacheBase::FLUSH_TEMPORARY:
//          if (CacheBase::LIFETIME_INFINITE == variable_get('cache_lifetime', CacheBase::LIFETIME_DEFAULT)) {
//            $keys[] = $skey;
//            foreach ($client->smembers($skey) as $tcid) {
//              $keys[] = $this->getKey($tcid);
//            }
//          }
//          break;
//
//        // Fallback on most secure mode: flush full bin.
//        default:
//        case CacheBase::FLUSH_ALL:
//          $keys[] = $skey;
//          $cid = '*';
//          $wildcard = true;
//          break;
//      }
//    }
//
//    if ('*' !== $cid && $wildcard) {
//      // Prefix flush.
//      $remoteKeys = $client->keys($this->getKey($cid . '*'));
//      // PhpRedis seems to suffer of some bugs.
//      if (!empty($remoteKeys) && is_array($remoteKeys)) {
//        $keys = array_merge($keys, $remoteKeys);
//      }
//    }
//    else if ('*' === $cid) {
//      // Full bin flush.
//      $remoteKeys = $client->keys($this->getKey('*'));
//      // PhpRedis seems to suffer of some bugs.
//      if (!empty($remoteKeys) && is_array($remoteKeys)) {
//        $keys = array_merge($keys, $remoteKeys);
//      }
//    }
//    else if (empty($keys) && !empty($cid)) {
//      // Single key drop.
//      $keys[] = $key = $this->getKey($cid);
//      $client->srem($skey, $key);
//    }
//
//    if (!empty($keys)) {
//      if (count($keys) < CacheBase::KEY_THRESHOLD) {
//        $client->del($keys);
//      } else {
//        $pipe = $client->multi(\Redis::PIPELINE);
//        do {
//          $buffer = array_splice($keys, 0, CacheBase::KEY_THRESHOLD);
//          $pipe->del($buffer);
//        } while (!empty($keys));
//        $pipe->exec();
//      }
//    }
//  }
}
