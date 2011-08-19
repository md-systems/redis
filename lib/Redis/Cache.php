<?php

/**
 * Cache backend for Redis module.
 */
class Redis_Cache implements DrupalCacheInterface {
  /**
   * @var DrupalCacheInterface
   */
  protected $_parent;

  function __construct($bin) {
    $className = Redis_Client::getClass(Redis_Client::REDIS_IMPL_CACHE);
    $this->_parent = new $className($bin);
  }

  function get($cid) {
    return $this->_parent->get($cid);
  }

  function getMultiple(&$cids) {
    return $this->_parent->getMultiple($cids);
  }

  function set($cid, $data, $expire = CACHE_PERMANENT) {
    $this->_parent->set($cid, $data, $expire);
  }

  function clear($cid = NULL, $wildcard = FALSE) {
    $this->_parent->clear($cid, $wildcard);
  }

  function isEmpty() {
    return $this->_parent->isEmpty();
  }
}
