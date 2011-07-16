<?php

/**
 * @file
 * Common code for all Redis clients.
 */

/**
 * Redis default port.
 */
define('REDIS_DEFAULT_HOST', 'localhost');

/**
 * Redis default port.
 */
define('REDIS_DEFAULT_PORT', 6379);

/**
 * Redis default database: will select none (Database 0).
 */
define('REDIS_DEFAULT_BASE', NULL);

/**
 * Client proxy, client handling class tied to the bare mininum.
 */
interface Redis_Client_Proxy_Interface {
  /**
   * Get the connected client instance.
   * 
   * @return mixed
   *   Real client depends from the library behind.
   */
  public function getClient($host = NULL, $port = NULL, $base = NULL);

  /**
   * Get underlaying library name used.
   * 
   * This can be useful for contribution code that may work with only some of
   * the provided clients.
   * 
   * @return string
   */
  public function getName();
}

/**
 * Share some common code.
 */
class Redis_Client {
  /**
   * @var Redis_Client_Proxy_Interface
   */
  protected static $_clientProxy;

  /**
   * @var mixed
   */
  protected static $_client;

  /**
   * Set client proxy.
   */
  public static function setClient(Redis_Client_Proxy_Interface $proxy) {
    if (isset(self::$_client)) {
      throw new Exception("Once Redis client is connected, you cannot change client proxy instance.");
    }

    self::$_clientProxy = $proxy;
  }

  /**
   * Get underlaying library name.
   * 
   * @return string
   */
  public static function getClientName() {
    if (!isset(self::$_clientProxy)) {
      throw new Exception("No client proxy set.");
    }

    return self::$_clientProxy->getName();
  }

  /**
   * Get client singleton. 
   */
  public static function getClient() {
    if (!isset(self::$_client)) {
      global $conf;

      if (!isset(self::$_clientProxy)) {
        throw new Exception("No client proxy set.");
      }

      // Always prefer socket connection.
      self::$_client = self::$_clientProxy->getClient(
        isset($conf['redis_cache_host']) ? $conf['redis_cache_host'] : REDIS_DEFAULT_HOST,
        isset($conf['redis_cache_port']) ? $conf['redis_cache_port'] : REDIS_DEFAULT_PORT,
        isset($conf['redis_cache_base']) ? $conf['redis_cache_base'] : REDIS_DEFAULT_BASE);
    }

    return self::$_client;
  }
}
