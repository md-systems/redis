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
   * @var Redis_Client_Interface
   */
  protected static $_clientInterface;

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
  public static function setClient(Redis_Client_Interface $interface) {
    if (isset(self::$_client)) {
      throw new Exception("Once Redis client is connected, you cannot change client proxy instance.");
    }

    self::$_clientInterface = $interface;
  }

  /**
   * Lazy instanciate client proxy depending on the actual configuration.
   * 
   * If you are using a lock, session or cache backend using one of the Redis
   * client implementation, this will be overrided at early bootstrap phase
   * and configuration will be ignored.
   * 
   * @return Redis_Client_Interface
   */
  public static function getClientInterface() {
    if (!isset(self::$_clientInterface)) {
      global $conf;

      if (isset($conf['redis_client_interface']) && class_exists($conf['redis_client_interface'])) {
        self::$_clientInterface = new $conf['redis_client_interface'];
      }
      else {
        if (!isset(self::$_clientInterface)) {
          throw new Exception("No client interface set.");
        }
      }
    }

    return self::$_clientInterface;
  }

  /**
   * Get underlaying library name.
   * 
   * @return string
   */
  public static function getClientName() {
    return self::getClientInterface()->getName();
  }

  /**
   * Get client singleton. 
   */
  public static function getClient() {
    if (!isset(self::$_client)) {
      global $conf;

      // Always prefer socket connection.
      self::$_client = self::getClientInterface()->getClient(
        isset($conf['redis_cache_host']) ? $conf['redis_client_host'] : self::REDIS_DEFAULT_HOST,
        isset($conf['redis_cache_port']) ? $conf['redis_client_port'] : self::REDIS_DEFAULT_PORT,
        isset($conf['redis_cache_base']) ? $conf['redis_client_base'] : self::REDIS_DEFAULT_BASE);
    }

    return self::$_client;
  }
}
