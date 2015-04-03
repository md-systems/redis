<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\CacheBackendFactory.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\RedisCacheTagsChecksum;
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
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * Creates a redis CacheBackendFactory.
   */
  function __construct(ClientFactory $client_factory, CacheTagsChecksumInterface $checksum_provider) {
    $this->clientFactory = $client_factory;
    $this->checksumProvider = $checksum_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $class_name = $this->clientFactory->getClass(ClientFactory::REDIS_IMPL_CACHE);
    //\Drupal::service('redis.phpredis.invalidator')->enable();
    return new $class_name($bin, $this->clientFactory->getClient(), $this->checksumProvider);
  }

}
