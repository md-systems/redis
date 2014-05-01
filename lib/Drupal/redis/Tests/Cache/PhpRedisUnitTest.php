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
 */
class PhpRedisUnitTest extends GenericCacheBackendUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('system', 'redis');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'PhpRedis cache backend',
      'description' => 'Unit test of the PhpRedis cache backend using the generic cache unit test base.',
      'group' => 'Redis',
    );
  }

  /**
   * Creates a new instance of PhpRedis cache backend.
   *
   * @return \Drupal\redis\Cache\PhpRedis
   *   A new PhpRedis cache backend.
   */
  protected function createCacheBackend($bin) {
    return new PhpRedis($bin);
  }

}
