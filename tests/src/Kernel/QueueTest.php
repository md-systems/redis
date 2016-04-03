<?php

/**
 * @file
 * Definition of Drupal\Tests\redis\Kernel\QueueTest.
 */

namespace Drupal\Tests\redis\Kernel;

use Drupal\redis\ClientFactory;
use \Drupal\KernelTests\Core\Queue\QueueTest as CoreQueueTest;

/**
 * Tests the Redis queue functions.
 *
 * @group redis
 */
class QueueTest extends CoreQueueTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('redis');

  /**
   * Tests Redis non-blocking queue.
   */
  public function testRedisNonBlockingQueue() {
    $client_factory = \Drupal::service('redis.factory');
    $settings = ['reserve_timeout' => NULL];
    $class_name = $client_factory->getClass(ClientFactory::REDIS_IMPL_QUEUE);

    /** @var \Drupal\Core\Queue\QueueInterface $queue1 */
    $queue1 = new $class_name($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue1->createQueue();

    /** @var \Drupal\Core\Queue\QueueInterface $queue2 */
    $queue2 = new $class_name($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue2->createQueue();

    $this->queueTest($queue1, $queue2);
    $queue1->deleteQueue();
    $queue2->deleteQueue();

    $class_name = $client_factory->getClass(ClientFactory::REDIS_IMPL_RELIABLE_QUEUE);

    /** @var \Drupal\Core\Queue\QueueInterface $queue1 */
    $queue1 = new $class_name($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue1->createQueue();

    /** @var \Drupal\Core\Queue\QueueInterface $queue2 */
    $queue2 = new $class_name($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue2->createQueue();

    $this->queueTest($queue1, $queue2);
  }

  /**
   * Tests Redis blocking queue.
   */
  public function testRedisBlockingQueue() {
    // Create two queues.
    $client_factory = \Drupal::service('redis.factory');
    $settings = ['reserve_timeout' => 30];
    $class_name = $client_factory->getClass(ClientFactory::REDIS_IMPL_QUEUE);

    /** @var \Drupal\Core\Queue\QueueInterface $queue1 */
    $queue1 = new $class_name($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue1->createQueue();

    /** @var \Drupal\Core\Queue\QueueInterface $queue2 */
    $queue2 = new $class_name($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue2->createQueue();

    $this->queueTest($queue1, $queue2);
  }

  /**
   * Overrides \Drupal\system\Tests\Queue\QueueTestQueueTest::testSystemQueue().
   *
   * We override tests from core class we extend to prevent them from running.
   */
  public function testSystemQueue() {}

  /**
   * Overrides \Drupal\system\Tests\Queue\QueueTestQueueTest::testMemoryQueue().
   *
   * We override tests from core class we extend to prevent them from running.
   */
  public function testMemoryQueue() {}
}

