<?php

/**
 * Predis cache backend.
 */
class Redis_Cache_PhpRedis extends Redis_Cache_Base {

  function get($cid) {
    $client     = Redis_Client::getClient();
    $key        = $this->getKey($cid);

    $cached = $client->get($key);

    if (FALSE === $cached) {
      return FALSE;
    }
    else {
      return unserialize($cached);
    }
  }

  function getMultiple(&$cids) {
    $client = Redis_Client::getClient();

    $ret = $keys = array();

    foreach ($cids as $cid) {
      $keys[$cid] = $this->getKey($cid);
    }

    $result = $client->mget($keys);

    foreach ($result as $cached) {
      if (is_string($cached)) {
        $cached = unserialize($cached);
        $ret[$cached->cid] = $cached;
      }
    }

    foreach ($cids as $index => $cid) {
      if (!isset($ret[$cid])) {
        unset($cids[$index]);
      }
    }

    return $ret;
  }

  function set($cid, $data, $expire = CACHE_PERMANENT) {
    $client = Redis_Client::getClient();
    $key    = $this->getKey($cid);

    $cached = array(
      'cid' => (string)$cid,
      'created' => REQUEST_TIME,
      'expire' => $expire,
      'data' => $data,
    );

    $client->multi(Redis::PIPELINE);

    switch ($expire) {

      // FIXME: Handle CACHE_TEMPORARY correctly.
      case CACHE_TEMPORARY:
      case CACHE_PERMANENT:
        $client->set($key, serialize((object)$cached));
        // We dont need the PERSIST command, since it's the default.
        break;

      default:
        $delay = $expire - time();
        $client->setex($key, $delay, serialize((object)$cached));
    }

    $client->exec();
  }

  function clear($cid = NULL, $wildcard = FALSE) {
    $client = Redis_Client::getClient();
    $many   = FALSE;

    // Redis handles for us cache key expiration.
    if (!isset($cid)) {
      return;
    }

    if ('*' !== $cid && $wildcard) {
      $key  = $this->getKey($cid . '*');
      $many = TRUE;
    }
    else if ('*' === $cid) {
      $key  = $this->getKey($cid);
      $many = TRUE;
    }
    else {
      $key = $this->getKey($cid);
    }

    if ($many) {
      $keys = $client->keys($key);

      // Attempt to clear an empty array will raise exceptions.
      if (!empty($keys)) {
        $client->del($keys);
      }
    }
    else {
      $client->del(array($key));
    }
  }

  function isEmpty() {
    // FIXME: Todo.
  }
}
