<?php

/**
 * @file
 * Redis cache backend using Predis PHP library as Redis client.
 */

// No autoloader is here during early bootstrap phases.
if (!class_exists('Redis_Client_Predis')) {
  $local_redis_path = dirname(__FILE__) . '/lib/Redis';
  require_once $local_redis_path . '/Client/Interface.php';
  require_once $local_redis_path . '/Client.php';
  require_once $local_redis_path . '/Client/Predis.php';
}

// Register our specific client.
if (!Redis_Client::hasClient()) {
  Redis_Client::setClient(new Redis_Client_Predis);
}

/**
 * Predis cache backend.
 */
class RedisPredisCache implements DrupalCacheInterface {
  /**
   * @var string
   */
  protected $_bin;

  function __construct($bin) {
    $this->_bin = $bin;
  }

  protected function _buildKey($cid) {
    // FIXME: Handle site.
    return $this->_bin . ':' . $cid;
  }

  function get($cid) {
    $client     = Redis_Client::getClient();
    $key        = $this->_buildKey($cid);

    $serialized = $client->get($key . ':serialized');

    if (!isset($serialized)) {
      return FALSE;
    }

    $cached          = new stdClass;
    $cached->data    = $client->get($key . ':data');
    $cached->expires = 0; // FIXME: Redis does not seem to allow us to fetch
                          // expire value. The only solution would be to create
                          // a new key. Who on earth need this value anyway?

    if ($serialized) {
      $cached->data  = unserialize($cached->data);
    }

    return $cached;
  }

  function getMultiple(&$cids) {
    $client = Redis_Client::getClient();

    $ret = $keys = $exclude = array();

    foreach ($cids as $cid) {
      $key       = $this->_buildKey($cid);
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
    $key    = $this->_buildKey($cid);

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
      $key  = $this->_buildKey('*' . $cid . '*');
      $many = TRUE;
    }
    else if ('*' === $cid) {
      $key  = $this->_buildKey($cid);
      $many = TRUE;
    }
    else {
      $key = $this->_buildKey($cid);
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
