<?php

/**
 * @file
 * Definition of Drupal\redis\Tests\Cache\PhpRedisUnitTest.
 */

namespace Drupal\redis\Tests\Cache;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\system\Tests\Cache\GenericCacheBackendUnitTestBase;
use Symfony\Component\DependencyInjection\Reference;

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

  public function containerBuild(ContainerBuilder $container) {
    parent::containerBuild($container);
    // Replace the default checksum service with the redis implementation.
    if ($container->has('redis.factory')) {
      $container->register('cache_tags.invalidator.checksum', 'Drupal\redis\Cache\RedisCacheTagsChecksum')
        ->addArgument(new Reference('redis.factory'))
        ->addTag('cache_tags_invalidator');
    }
  }


  /**
   * Creates a new instance of PhpRedis cache backend.
   *
   * @return \Drupal\redis\Cache\PhpRedis
   *   A new PhpRedis cache backend.
   */
  protected function createCacheBackend($bin) {
    $cache = \Drupal::service('cache.backend.redis')->get($bin);
    $cache->setMinTtl(10);
    return $cache;
  }

}
