<?php

/**
 * @file
 * Definition of Drupal\redis\Tests\Cache\PhpRedisUnitTest.
 */

namespace Drupal\redis\Tests\Cache;

use Drupal\redis\Cache\PhpRedis;
use Drupal\system\Tests\Cache\GenericCacheBackendUnitTestBase;
use Drupal\Core\Cache\Cache;

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

  /**
   * {@inheritdoc}
   */
  public function testIsEmpty() {
    // PhpRedis::isEmpty() is not implemented.
  }

  /**
   * {@inheritdoc}
   */
  public function testDeleteAll() {
    // PhpRedis::isEmpty() is not implemented. Therefore it is necessary to
    // reimplement testDeleteAll along the lines of testInvalidateAll (i.e.
    // without calling isEmpty()).
    $backend = $this->getCacheBackend();

    // Set both expiring and permanent keys.
    $backend->set('test1', 1, Cache::PERMANENT);
    $backend->set('test2', 3, time() + 1000);

    $backend->deleteAll();

    $this->assertFalse($backend->get('test1'), 'First key has been deleted.');
    $this->assertFalse($backend->get('test2'), 'Second key has been deleted.');
  }

}
