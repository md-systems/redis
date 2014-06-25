<?php

/**
 * @file
 * Contains Drupal\redis\LockFactory.
 */

namespace Drupal\redis;

/**
 * Lock backend singleton handling.
 */
class LockFactory {
  /**
   * @var \Drupal\redis\LockInterface.
   */
  private static $instance;

  /**
   * Get actual lock backend.
   *
   * @return \Drupal\redis\LockInterface
   *   Return lock backend instance.
   */
  public static function get() {
    if (!isset(self::$instance)) {
      $className = ClientFactory::getClass(ClientFactory::REDIS_IMPL_LOCK);
      self::$instance = new $className();
    }
    return self::$instance;
  }
}
