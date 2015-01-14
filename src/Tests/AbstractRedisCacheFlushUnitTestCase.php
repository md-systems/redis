<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\AbstractRedisCacheFlushUnitTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\Core\Cache\Cache;
use Drupal\redis\CacheBase;

/**
 * Base implementation for locking functionnal testing.
 */
abstract class AbstractRedisCacheFlushUnitTestCase extends AbstractRedisCacheUnitTestCase {
  /**
   * Test that the flush all flush mode flushes everything.
   */
  public function testDeleteAll() {
    $backend = $this->getBackend();
    $backend->set('test1', 42, Cache::PERMANENT);
    $backend->set('test2', 'foo', Cache::PERMANENT);
    $backend->set('test3', 'bar', 10);

    $backend->deleteAll();

    $this->assertFalse($backend->get('test1'));
    $this->assertFalse($backend->get('test2'));
    $this->assertFalse($backend->get('test3'));
  }

  /**
   * Tests tag deletion.
   */
  public function testTagsDeletion() {
    // Create cache entry in multiple bins.
    $tags = array('test_tag:1', 'test_tag:2', 'test_tag:3');
    $backend = $this->getBackend();

    $backend->set('test', 'value', Cache::PERMANENT, $tags);
    $backend->set('test2', 'value', Cache::PERMANENT, array('test_tag:2'));
    $this->assertTrue($backend->get('test'), 'Cache item was set in bin.');
    $this->assertTrue($backend->get('test2'), 'Cache item was set in bin.');

    $backend->deleteTags(array('test_tag:1'));

    // Test that cache entry has been deleted in multiple bins.
    $this->assertFalse($backend->get('test'), 'Tag invalidation affected item in bin.');

    // Test that only one tag deletion has occurred.
    $this->assertTrue($backend->get('test2'), 'Only one tag was removed.');
  }

  /**
   * Flushing more than 20 elements should switch to a pipeline that
   * sends multiple DEL batches.
   */
  public function testDeleteALot() {
    $backend = $this->getBackend();

    $cids = array();

    for ($i = 0; $i < 100; ++$i) {
      $cids[] = $cid = 'test' . $i;
      $backend->set($cid, 42, Cache::PERMANENT);
    }

    $backend->deleteMultiple($cids);

    foreach ($cids as $cid) {
      $this->assertFalse($backend->get($cid));
    }
  }

}
