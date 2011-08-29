<?php

/**
 * Predis cache backend.
 */
class Redis_Cache_PhpRedis extends Redis_Cache_Base {

  function get($cid) {
    $client     = Redis_Client::getClient();
    $key        = $this->getKey($cid);

    list($serialized, $data) = $client->mget(array($key . ':serialized', $key . ':data'));

    if (FALSE === $data) {
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

      if (FALSE === $serialized) {
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

    if (isset($data) && !is_scalar($data)) {
      $serialize = TRUE;
      $data      = serialize($data);
    }
    else {
      $serialize = FALSE;
    }

    $client->multi(Redis::PIPELINE);

    switch ($expire) {

      // FIXME: Handle CACHE_TEMPORARY correctly.
      case CACHE_TEMPORARY:
      case CACHE_PERMANENT:
        $client->set($key . ':data',        $data);
        $client->set($key . ':serialized' , (int)$serialize);
        // We dont need the PERSIST command, since it's the default.
        break;

      default:
        $delay = $expire - time();
        $client->setex($key . ':data',       $delay, $data);
        $client->setex($key . ':serialized', $delay, (int)$serialize);
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
