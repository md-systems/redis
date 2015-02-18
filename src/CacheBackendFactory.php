<?php

/**
 * @file
 * Contains \Drupal\redis\CacheBackendFactory.
 */

namespace Drupal\redis;

use Drupal\Core\Cache\CacheFactoryInterface;

/**
 * A cache backend factory responsible for the construction of redis cache bins.
 */
class CacheBackendFactory implements CacheFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $class_name = ClientFactory::getClass(ClientFactory::REDIS_IMPL_CACHE);
    \Drupal::service('redis.phpredis.invalidator')->enable();
    return new $class_name($bin);
  }

}
