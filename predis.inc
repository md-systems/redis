<?php

/**
 * @file
 * Redis cache backend using Predis PHP library as Redis client.
 * 
 * Please read README.txt
 */

// Define Predis base path if not already set, and if we need to set the
// autoloader by ourself. This will ensure no crash. Best way would have
// been that Drupal ships a PSR-0 autoloader, in which we could manually
// add our library path.
if (!class_exists('Predis\Client')) {

  // Define Predis library base path if not already done.
  if (!defined('PREDIS_BASE_PATH')) {
    /**
     * Predis library base path.
     */
    define('PREDIS_BASE_PATH', dirname(__FILE__) . '/predis/lib/');
  }

  // Register a simple autoloader for Predis library. Since the Predis
  // library is PHP 5.3 only, we can afford doing closures safely.
  spl_autoload_register(function($class) {
    $file = PREDIS_BASE_PATH . strtr($class, '\\', '/') . '.php';
    if (file_exists($file)) {
      require $file;
      return TRUE;
    }
  });
}

/**
 * Predis cache backend.
 */
class RedisPredisCache implements DrupalCacheInterface {
  /**
   * @var Predis\Client
   */
  protected static $_client;

  protected static function _init() {
    if (isset(self::$_client)) {
      return;
    }
    global $conf;
    if (isset($conf['predis_cache_uri'])) {
      self::$_client = new Predis\Client($conf['predis_cache_uri']);
    }
    else {
      // No parameters given, attempt a connection on localhost.
      self::$_client = new Predis\Client();
    }
  }

  /**
   * @var string
   */
  protected $_bin;

  function __construct($bin) {
    // Force static init if not already done by another instance.
    self::_init();
    $this->_bin = $bin;
  }

  protected function _buildKey($cid) {
    // FIXME: Handle site.
    return $this->_bin . ':' . $cid;
  }

  function get($cid) {
    $client     = self::$_client;
    $key        = $this->_buildKey($cid);

    $serialized = $client->get($key . ':serialized');

    if (!isset($serialized)) {
      return FALSE;
    }

    $cached          = new stdClass;
    $cached->data    = $client->get($key . ':data');
    $cached->expires = 0; // FIXME

    if ($serialized) {
      $cached->data  = unserialize($cached->data);
    }

    return $cached;
  }

  function getMultiple(&$cids) {
    // FIXME: GROS TODO ICI.
    $ret = $keys = $exclude = array();

    foreach ($cids as $cid) {
      $keys[$cid] = $this->_buildKey($cid);
    }

    $result  = self::$_client->mget($keys);

    // FIXME: Waiting for it to happen.
    print_r($result);die("Not implemented");

    foreach ($keys as $cid => $key) {
      if (isset($result[$key])) {
        $ret[$cid] = $key;
      }
      else {
        $exclude[$cid] = TRUE;
      }
    }

    // WTF Drupal, we need to remove entries from &$cids.
    foreach ($cids as $index => $cid) {
      if (isset($exclude[$cid])) {
        unset($cids[$index]);
      }
    }

    return $ret;
  }

  function set($cid, $data, $expire = CACHE_PERMANENT) {
    $client = self::$_client;
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
    $client = self::$_client;

    if (!isset($cid)) {
      // Redis handles for us cache key expiration.
      return;
    }

    if ('*' !== $cid && $wildcard) {
      $key = $this->_buildKey('*' . $cid . '*');
    }
    else {
      // If $cid is '*', then the built key will match the full bin.
      $key = $this->_buildKey($cid);
    }

    $client->del($key);
  }

  function isEmpty() {
    // FIXME: Todo.
  }
}
