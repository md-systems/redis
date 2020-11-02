<?php

namespace Drupal\Test\redis\Kernel\Cache;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redis\Cache\CacheBase;

/**
 * @coversDefaultClass \Drupal\redis\Cache\CacheBase
 */
class CacheBaseTest extends KernelTestBase {

  private function getMockCache() {
    return new class ('testbin', $this->prophesize(SerializationInterface::class)
      ->reveal()) extends CacheBase {

      public function getMultiple(&$cids, $allow_invalid = FALSE) {
      }

      public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
        return $this->getExpiration($expire);
      }

      protected function doDeleteMultiple(array $cids) {
      }
    };
  }

  /**
   * @covers ::getPermTtl
   */
  public function testSet() {
    $class = $this->getMockCache();
    $class->setPermTtl();
    $this->assertSame(CacheBase::LIFETIME_PERM_DEFAULT, $class->getPermTtl());
    $class->setPermTtl(5);
    $this->assertSame(5, $class->getPermTtl());
    new Settings(['redis.settings' => ['perm_ttl_testbin' => 15]]);
    $class->setPermTtl();
    $this->assertSame(15, $class->getPermTtl());
    new Settings(['redis.settings' => ['perm_ttl_testbin' => '1 year']]);
    $class->setPermTtl();
    $this->assertSame(31536000, $class->getPermTtl());
    new Settings(['redis.settings' => ['perm_ttl_testbin' => '1 month']]);
    $class->setPermTtl();
    $this->assertSame(2592000, $class->getPermTtl());
    // TODO Fix this.
    new Settings(['redis.settings' => ['perm_ttl_testbin' => '1 day']]);
    $class->setPermTtl();
    $this->assertSame(86400, $class->getPermTtl());
    new Settings(['redis.settings' => ['perm_ttl_testbin' => '1 hour']]);
    $class->setPermTtl();
    $this->assertSame(3600, $class->getPermTtl());
    new Settings(['redis.settings' => ['perm_ttl_testbin' => '1 minute']]);
    $class->setPermTtl();
    $this->assertSame(60, $class->getPermTtl());
    new Settings(['redis.settings' => ['perm_ttl_testbin' => '1 minute 15 seconds']]);
    $class->setPermTtl();
    $this->assertSame(75, $class->getPermTtl());
  }

  /**
   * @covers ::getExpiration
   */
  public function testGetExpiration() {
    $class = $this->getMockCache();
    $class->setPermTtl(CacheBase::LIFETIME_PERM_DEFAULT);

    $time = \Drupal::time()->getRequestTime();
    $this->assertSame(CacheBase::LIFETIME_PERM_DEFAULT, $class->set('a', 'a', Cache::PERMANENT), 'Cache::PERMANENT uses permTtl');
    $this->assertSame(CacheBase::LIFETIME_PERM_DEFAULT, $class->set('a', 'a', $time + CacheBase::LIFETIME_PERM_DEFAULT), 'expire same as permTtl');
    $this->assertSame(CacheBase::LIFETIME_PERM_DEFAULT, $class->set('a', 'a', $time + CacheBase::LIFETIME_PERM_DEFAULT + 5), 'expire bigger than permTtl uses permTtl');
    $this->assertSame(60, $class->set('a', 'a', $time + 60), 'smaller offsets return correct TTL');
  }

}
