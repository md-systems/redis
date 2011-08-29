<?php

/**
 * Predis cache backend.
 */
class Redis_Cache_Predis extends Redis_Cache_Base {

  function get($cid) {
    $client     = Redis_Client::getClient();
    $key        = $this->getKey($cid);

    list($serialized, $data) = $client->mget(array($key . ':serialized', $key . ':data'));

    // Fixes http://drupal.org/node/1241922
    // FIXME: Not sure this test is fail-proof. Using the PhpRedis extension,
    // Redis values returned seems more strongly typed, which allows a safer
    // and quicker test here. But using Predis, some returned value are empty
    // strings, which makes these tests incoherent, sadly.
    if (!is_bool($serialized) && empty($data)) {
      return FALSE;
    }

    $cached          = new stdClass;
    $cached->data    = $serialized ? unserialize($data) : $data;
    $cached->expires = 0; // FIXME: Redis does not seem to allow us to fetch
                          // expire value. The only solution would be to create
                          // a new key. Who on earth need this value anyway?

    return $cached;
  }

  function getMultiple(&$cids) {
    $client = Redis_Client::getClient();

    $ret = $keys = $exclude = array();

    foreach ($cids as $cid) {
      $key       = $this->getKey($cid);
      $keys[]    = $key . ':data';
      $keys[]    = $key . ':serialized';
    }

    $result = $client->mget($keys);

    $index = 0;
    foreach ($cids as $cid) {
      $serialized = $result[$index + 1];

      if (!$serialized) {
        $exclude[$cid] = TRUE;

        continue;
      }

      $cached          = new stdClass;
      $cached->data    = $result[$index];
      $cached->expires = 0; // FIXME: See comment in get() method.
  
      if ($serialized) {
        $cached->data  = unserialize($cached->data);
      }

      $ret[$cid] = $cached;
      $index += 2;
    }

    // WTF Drupal, we need to manually remove entries from &$cids.
    foreach ($cids as $index => $cid) {
      if (isset($exclude[$cid])) {
        unset($cids[$index]);
      }
    }

    return $ret;
  }

  function set($cid, $data, $expire = CACHE_PERMANENT) {
    $client = Redis_Client::getClient();
    $key    = $this->getKey($cid);

    $client->pipeline(function($pipe) use ($key, $data, $expire) {

      if (isset($data) && !is_scalar($data)) {
        $serialize = TRUE;
        $data      = serialize($data);
      }
      else {
        $serialize = FALSE;
      }

      switch ($expire) {

        // FIXME: Handle CACHE_TEMPORARY correctly.
        case CACHE_TEMPORARY:
        case CACHE_PERMANENT:
          $pipe->set($key . ':data',        $data);
          $pipe->set($key . ':serialized' , $serialize);
          // We dont need the PERSIST command, since it's the default.
          break;

        default:
          $delay = $expire - time();
          $pipe->setex($key . ':data',       $delay, $data);
          $pipe->setex($key . ':serialized', $delay, $serialize);
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
      $key  = $this->getKey('*' . $cid . '*');
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
      $client->del(array(
        $key . ':data',
        $key . ':serialized',
      ));
    }
  }

  function isEmpty() {
    // FIXME: Todo.
  }
}
