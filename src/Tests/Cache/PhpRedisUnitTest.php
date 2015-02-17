<?php

/**
 * @file
 * Definition of Drupal\redis\Tests\Cache\PhpRedisUnitTest.
 */

namespace Drupal\redis\Tests\Cache;

use Drupal\redis\Cache\PhpRedis;
use Drupal\system\Tests\Cache\GenericCacheBackendUnitTestBase;

/**
 * Tests PhpRedis cache backend using GenericCacheBackendUnitTestBase.
 *
 * @group redis
 */
class PhpRedisUnitTest extends GenericCacheBackendUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'redis');

  /**
   * Creates a new instance of PhpRedis cache backend.
   *
   * @return \Drupal\redis\Cache\PhpRedis
   *   A new PhpRedis cache backend.
   */
  protected function createCacheBackend($bin) {
    $cache = new PhpRedis($bin);
    $cache->setMinTtl(10);
    return $cache;
  }

}
