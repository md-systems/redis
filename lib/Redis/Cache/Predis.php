<?php

/**
 * Predis cache backend.
 */
class Redis_Cache_Predis extends Redis_Cache_Base {

  function get($cid) {
    $client     = Redis_Client::getClient();
    $key        = $this->getKey($cid);

    $cached = $client->hgetall($key);

    if (empty($cached)) {
      return FALSE;
    }

    $cached = (object)$cached;

    if ($cached->serialized) {
      $cached->data = unserialize($cached->data);
    }

    return $cached;
  }

  function getMultiple(&$cids) {
    $client = Redis_Client::getClient();

    $ret = $keys = array();
    $keys = array_map(array($this, 'getKey'), $cids);

    $replies = $client->pipeline(function($pipe) use ($keys) {
      foreach ($keys as $key) {
        $pipe->hgetall($key);
      }
    });

    foreach ($replies as $reply) {
      if (!empty($reply)) {

        $cache = (object)$reply;

        if ($cache->serialized) {
          $cache->data = unserialize($cache->data);
        }

        $ret[$cache->cid] = $cache;
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

      $hash = array(
        'cid' => $cid,
        'created' => time(),
        'expire' => $expire,
      );

      if (!is_scalar($data)) {
        $hash['data'] = serialize($data);
        $hash['serialized'] = 1;
      }
      else {
        $hash['data'] = $data;
        $hash['serialized'] = 0;
      }

      $pipe->hmset($key, $hash);

      switch ($expire) {

        // FIXME: Handle CACHE_TEMPORARY correctly.
        case CACHE_TEMPORARY:
        case CACHE_PERMANENT:
          // We dont need the PERSIST command, since it's the default.
          break;

        default:
          $delay = $expire - time();
          $pipe->expire($key, $delay);
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
