<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\AbstractRedisCacheFixesUnitTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\Core\Cache\Cache;
use Drupal\redis\CacheBase;

/**
 * Bugfixes made over time test class.
 */
abstract class AbstractRedisCacheFixesUnitTestCase extends AbstractRedisCacheUnitTestCase {
  public function testTemporaryCacheExpire() {
    $backend = $this->getBackend();

    // Permanent entry.
    $backend->set('test1', 'foo', Cache::PERMANENT);
    $data = $backend->get('test1');
    $this->assertNotEqual(false, $data);
    $this->assertIdentical('foo', $data->data);

    // Expiring entry with permanent default lifetime.
    $backend->set('test2', 'bar');
    sleep(2);
    $data = $backend->get('test2');
    $this->assertNotEqual(false, $data);
    $this->assertIdentical('bar', $data->data);
    sleep(2);
    $data = $backend->get('test2');
    $this->assertNotEqual(false, $data);
    $this->assertIdentical('bar', $data->data);

    // Expiring entry with negative lifetime.
    $backend->set('test3', 'baz', REQUEST_TIME - 100);
    $data = $backend->get('test3');
    $this->assertEqual(false, $data);
  }

  public function testDefaultPermTtl() {
    $backend = $this->getBackend();
    $this->assertIdentical(CacheBase::LIFETIME_PERM_DEFAULT, $backend->getPermTtl());
  }

  public function testUserSetDefaultPermTtl() {
    global $config;
    // This also testes string parsing. Not fully, but at least one case.
    $config['redis.settings']['perm_ttl_cache'] = "3 months";
    $backend = $this->getBackend();
    $this->assertIdentical(7776000, $backend->getPermTtl());
  }

  public function testUserSetPermTtl() {
    global $config;
    // This also testes string parsing. Not fully, but at least one case.
    $config['redis.settings']['perm_ttl_cache'] = "1 months";
    $backend = $this->getBackend();
    $this->assertIdentical(2592000, $backend->getPermTtl());
  }

  public function testPermTtl() {
    global $config;
    // This also testes string parsing. Not fully, but at least one case.
    $config['redis.settings']['perm_ttl_cache'] = "2 seconds";
    $backend = $this->getBackend();
    $this->assertIdentical(2, $backend->getPermTtl());

    $backend->set('test6', 'cats are mean');
    $this->assertIdentical('cats are mean', $backend->get('test6')->data);

    sleep(3);
    $item = $backend->get('test6');
    $this->assertTrue(empty($item));
  }

}
