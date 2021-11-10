<?php

namespace Drupal\redis_sessions\Session;

use Drupal\redis\ClientFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

/**
 * Session handler handling.
 */
class SessionHandlerFactory {

  /**
   * The client factory.
   *
   * @var \Drupal\redis\ClientFactory
   */
  protected $clientFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\redis\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * Get actual session handler.
   *
   * @return \SessionHandlerInterface
   *   Return the redis session handler.
   */
  public function get() {
    $client = $this->clientFactory->getClient();
    return new RedisSessionHandler($client);
  }

}
