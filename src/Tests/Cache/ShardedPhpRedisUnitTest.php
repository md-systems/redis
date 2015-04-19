<?php

/**
 * @file
 * Definition of Drupal\redis\Tests\Cache\PhpRedisUnitTest.
 */

namespace Drupal\redis\Tests\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\redis\Cache\ShardedPhpRedis;
use Drupal\system\Tests\Cache\GenericCacheBackendUnitTestBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests PhpRedis cache backend using GenericCacheBackendUnitTestBase.
 *
 * @group redis
 */
class ShardedPhpRedisUnitTest extends GenericCacheBackendUnitTestBase {

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
    $cache = new ShardedPhpRedis(
        $bin,
        \Drupal::service('redis.factory')->getClient(),
        \Drupal::service('cache_tags.invalidator.checksum')
    );
    $cache->setMinTtl(10);
    return $cache;
  }


  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::invalidateTags().
   */
  function testInvalidateTags() {
    $backend = $this->getCacheBackend();

    // Create two cache entries with the same tag and tag value.
    $backend->set('test_cid_invalidate1', $this->defaultValue, Cache::PERMANENT, array('test_tag:2'));
    $backend->set('test_cid_invalidate2', $this->defaultValue, Cache::PERMANENT, array('test_tag:2'));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Two cache items were created.');

    // Invalidate test_tag of value 1. This should invalidate both entries.
    Cache::invalidateTags(array('test_tag:2'));
    $this->assertFalse($backend->get('test_cid_invalidate1') || $backend->get('test_cid_invalidate2'), 'Two cache items invalidated after invalidating a cache tag.');

    // Create two cache entries with the same tag and an array tag value.
    $backend->set('test_cid_invalidate1', $this->defaultValue, Cache::PERMANENT, array('test_tag:1'));
    $backend->set('test_cid_invalidate2', $this->defaultValue, Cache::PERMANENT, array('test_tag:1'));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Two cache items were created.');

    // Invalidate test_tag of value 1. This should invalidate both entries.
    Cache::invalidateTags(array('test_tag:1'));
    $this->assertFalse($backend->get('test_cid_invalidate1') || $backend->get('test_cid_invalidate2'), 'Two caches removed after invalidating a cache tag.');

    // Create three cache entries with a mix of tags and tag values.
    $backend->set('test_cid_invalidate1', $this->defaultValue, Cache::PERMANENT, array('test_tag:1'));
    $backend->set('test_cid_invalidate2', $this->defaultValue, Cache::PERMANENT, array('test_tag:2'));
    $backend->set('test_cid_invalidate3', $this->defaultValue, Cache::PERMANENT, array('test_tag_foo:3'));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2') && $backend->get('test_cid_invalidate3'), 'Three cached items were created.');
    Cache::invalidateTags(array('test_tag_foo:3'));
    $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'), 'Cache items not matching the tag were not invalidated.');
    $this->assertFalse($backend->get('test_cid_invalidated3'), 'Cached item matching the tag was removed.');

    // Create cache entry in multiple bins. Two cache entries
    // (test_cid_invalidate1 and test_cid_invalidate2) still exist from previous
    // tests.
    $tags = array('test_tag:1', 'test_tag:2', 'test_tag:3');
    $bins = array('path', 'bootstrap', 'page');
    foreach ($bins as $bin) {
      $this->getCacheBackend($bin)->set('test', $this->defaultValue, Cache::PERMANENT, $tags);
      $this->assertTrue($this->getCacheBackend($bin)->get('test'), 'Cache item was set in bin.');
    }

    Cache::invalidateTags(array('test_tag:2'));

    // Test that the cache entry has been invalidated in multiple bins.
    foreach ($bins as $bin) {
      $this->assertFalse($this->getCacheBackend($bin)->get('test'), 'Tag invalidation affected item in bin.');
    }
    // Test that the cache entry with a matching tag has been invalidated.
    $this->assertFalse($this->getCacheBackend($bin)->get('test_cid_invalidate2'), 'Cache items matching tag were invalidated.');
    // Test that the cache entry with without a matching tag still exists.
    $this->assertTrue($this->getCacheBackend($bin)->get('test_cid_invalidate1'), 'Cache items not matching tag were not invalidated.');
  }

  /**
   * Test Drupal\Core\Cache\CacheBackendInterface::invalidateAll().
   */
  public function testInvalidateAll() {
    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    // Set both expiring and permanent keys.
    $backend_a->set('test1', 1, Cache::PERMANENT);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    $backend_a->invalidateAll();

    $this->assertFalse($backend_a->get('test1'), 'First key has been invalidated.');
    $this->assertFalse($backend_a->get('test2'), 'Second key has been invalidated.');
    $this->assertTrue($backend_b->get('test3'), 'Item in other bin is preserved.');
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::removeBin().
   */
  public function testRemoveBin() {
    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    // Set both expiring and permanent keys.
    $backend_a->set('test1', 1, Cache::PERMANENT);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    $backend_a->removeBin();

    $this->assertFalse($backend_a->get('test1'), 'First key has been deleted.');
    $this->assertFalse($backend_a->get('test2'), 'Second key has been deleted.');
    $this->assertTrue($backend_b->get('test3'), 'Item in other bin is preserved.');
  }

}
