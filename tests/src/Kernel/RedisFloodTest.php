<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\redis\Flood\PhpRedis;
use Drupal\Core\Site\Settings;

/**
 * Tests Redis flood backend.
 *
 * @group redis
 */
class RedisFloodTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array( 'redis');

  /**
   * Test flood control.
   */
  public function testFlood() {
    self::setUpSettings();
    $threshold = 2;
    $window = 1;
    $name = 'flood_test_cleanup';

    $client_factory = \Drupal::service('redis.factory');
    $request_stack = \Drupal::service('request_stack');
    $flood = new PhpRedis($client_factory, $request_stack);

    // By default the event is allowed.
    $this->assertTrue($flood->isAllowed($name, $threshold));

    // Register event.
    $flood->register($name, $window);

    // The event is still allowed.
    $this->assertTrue($flood->isAllowed($name, $threshold));

    $flood->register($name, $window);

    // Verify event is not allowed.
    $this->assertFalse($flood->isAllowed($name, $threshold));

    // "Sleep" two seconds, then the event is allowed again.
    $_SERVER['REQUEST_TIME'] += 2;
    $this->assertTrue($flood->isAllowed($name, $threshold));

  }

  /**
   * Uses an env variable to set the redis client to use for this test.
   */
  protected function setUpSettings() {

    // Write redis_interface settings manually.
    $redis_interface = getenv('REDIS_INTERFACE');
    $settings = Settings::getAll();
    $settings['redis.connection']['interface'] = $redis_interface;
    new Settings($settings);
  }

}
