<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\CacheBackendFactory.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
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
   * List of cache bins.
   *
   * Renderer and possibly other places fetch backends directly from the
   * factory. Avoid that the backend objects have to fetch meta information like
   * the last delete all timestamp multiple times.
   *
   * @var array
   */
  protected $bins = [];

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
    if (!isset($this->bins[$bin])) {
      $class_name = $this->clientFactory->getClass(ClientFactory::REDIS_IMPL_CACHE);
      $this->bins[$bin] = new $class_name($bin, $this->clientFactory->getClient(), $this->checksumProvider);
    }
    return $this->bins[$bin];
  }

}
