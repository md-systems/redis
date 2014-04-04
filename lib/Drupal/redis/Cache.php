<?php

/**
 * @file
 * Contains \Drupal\redis\Cache.
 */

namespace Drupal\redis;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Cache backend for Redis module.
 */
class Cache implements CacheBackendInterface {
  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $backend;

  public function __construct($bin) {
    $className = ClientFactory::getClass(ClientFactory::REDIS_IMPL_CACHE);
    $this->backend = new $className($bin);
  }

  public function get($cid) {
    return $this->backend->get($cid);
  }

  public function getMultiple(&$cids) {
    return $this->backend->getMultiple($cids);
  }

  public function set($cid, $data, $expire = CACHE_PERMANENT) {
    $this->backend->set($cid, $data, $expire);
  }

  public function clear($cid = NULL, $wildcard = FALSE) {
    // This function also accepts arrays, thus handle everything like an array.
    $cids = is_array($cid) ? $cid : array($cid);
    foreach ($cids as $cid) {
      $this->backend->clear($cid, $wildcard);
    }
  }

  public function isEmpty() {
    return $this->backend->isEmpty();
  }
}
