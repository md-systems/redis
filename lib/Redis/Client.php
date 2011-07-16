<?php

// It may happen we get here with no autoloader set during the Drupal core
// early bootstrap phase, at cache backend init time.
if (!interface_exists('Redis_Client_Interface')) {
  require_once dirname(__FILE__) . '/Client/Interface.php';
}

/**
 * Common code and client singleton, for all Redis clients.
 */
class Redis_Client {
  /**
   * Redis default host.
   */
  const REDIS_DEFAULT_HOST = "127.0.0.1";

  /**
   * Redis default port.
   */
  const REDIS_DEFAULT_PORT = 6379;

  /**
   * Redis default database: will select none (Database 0).
   */
  const REDIS_DEFAULT_BASE = NULL;

  /**
   * @var Redis_Client_Proxy_Interface
   */
  protected static $_clientProxy;

  /**
   * @var mixed
   */
  protected static $_client;

  public static function hasClient() {
    return isset(self::$_client);
  }

  /**
   * Set client proxy.
   */
  public static function setClient(Redis_Client_Interface $proxy) {
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
        isset($conf['redis_cache_host']) ? $conf['redis_cache_host'] : self::REDIS_DEFAULT_HOST,
        isset($conf['redis_cache_port']) ? $conf['redis_cache_port'] : self::REDIS_DEFAULT_PORT,
        isset($conf['redis_cache_base']) ? $conf['redis_cache_base'] : self::REDIS_DEFAULT_BASE);
    }

    return self::$_client;
  }
}
