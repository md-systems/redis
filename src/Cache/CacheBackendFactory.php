<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\CacheBackendFactory.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\redis\ClientFactory;

/**
 * A cache backend factory responsible for the construction of redis cache bins.
 */
class CacheBackendFactory implements CacheFactoryInterface {

  /**
   * @var \Drupal\redis\ClientInterface
   */
  protected $clientFactory;

  /**
   * Creates a redis CacheBackendFactory.
   */
  function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $class_name = $this->clientFactory->getClass(ClientFactory::REDIS_IMPL_CACHE);
    \Drupal::service('redis.phpredis.invalidator')->enable();
    return new $class_name($bin, $this->clientFactory->getClient());
  }

}
