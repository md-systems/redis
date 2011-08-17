<?php

/**
 * @file
 * Drupal core lock.inc implementation using Redis via Predis library.
 */

// No autoloader is here during early bootstrap phases.
if (!class_exists('Redis_Client_Predis')) {
  require_once dirname(__FILE__) . '/predis.cache.inc';
}
if (!class_exists('Redis_Lock_Backend_Predis')) {
  $local_redis_path = dirname(__FILE__) . '/lib/Redis';
  require_once $local_redis_path . '/Lock/Backend/Interface.php';
  require_once $local_redis_path . '/Lock/Backend/Default.php';
  require_once $local_redis_path . '/Lock/Backend/Predis.php';
  require_once $local_redis_path . '/Lock.php';
}

require_once dirname(__FILE__) . '/redis.lock.inc';

Redis_Lock::setBackend(new Redis_Lock_Backend_Predis());
