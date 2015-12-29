<?php

/**
 * @file
 * Contains Drupal\redis\Lock\LockFactory.
 */

namespace Drupal\redis\Lock;

use Drupal\redis\ClientFactory;

/**
 * Lock backend singleton handling.
 */
class LockFactory {

  /**
   * @var \Drupal\redis\ClientInterface
   */
  protected $clientFactory;

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
  function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Get actual lock backend.
   *
   * @return \Drupal\Core\Lock\LockBackendInterface
   *   Return lock backend instance.
   */
  public function get() {
    $className = $this->clientFactory->getClass(ClientFactory::REDIS_IMPL_LOCK);
    return new $className($this->clientFactory);
  }
}
