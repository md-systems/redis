<?php

/**
 * Predis cache backend.
 */
class Redis_Cache_Predis extends Redis_Cache_Base {

  function get($cid) {
    $client     = Redis_Client::getClient();
    $key        = $this->getKey($cid);

    $cached = $client->get($key);

    if (!is_string($cached)) {
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

    // Not doing array_values() call here would make Predis change behavior and
    // fail raising PHP warnings.
    $result = $client->mget(array_values($keys));

    foreach ($result as $key => $cached) {
      if ($cached) {
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

    $client->pipeline(function($pipe) use ($cid, $key, $data, $expire) {

      $cached = array(
        'cid' => (string)$cid,
        'created' => REQUEST_TIME,
        'expire' => $expire,
        'data' => $data,
      );

      switch ($expire) {

        // FIXME: Handle CACHE_TEMPORARY correctly.
        case CACHE_TEMPORARY:
        case CACHE_PERMANENT:
          $pipe->set($key, serialize((object)$cached));
          // We dont need the PERSIST command, since it's the default.
          break;

        default:
          $delay = $expire - time();
          $pipe->setex($key, $delay, serialize((object)$cached));
      }
    });
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
      $client->del($key);
    }
  }

  function isEmpty() {
    // FIXME: Todo.
  }
}
