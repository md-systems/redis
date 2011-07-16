<?php

/**
 * @file
 * Predis client handling.
 * 
 * Please read README.Predis.txt
 */

require_once dirname(__FILE__) . '/redis.common.inc';

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
 * Specific implementation.
 */
class Redis_Client_Proxy_Predis implements Redis_Client_Proxy_Interface {

  public function getClient($host = NULL, $port = NULL, $base = NULL) {
    $connectionInfo = array(
      'host'     => $host,
      'port'     => $port,
      'database' => $base
    );

    foreach ($connectionInfo as $key => $value) {
      if (!isset($value)) {
        unset($connectionInfo[$key]);
      }
    }

    return new Predis\Client($connectionInfo);
  }

  public function getName() {
    return 'Predis';
  }
}

// Register client proxy.
Redis_Client::setClient(new Redis_Client_Proxy_Predis);
