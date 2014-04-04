<?php

/**
 * @file
 * Contains Drupal\redis\Client\PhpRedis.
 */

namespace Drupal\redis\Client;

use Drupal\redis\ClientInterface;

/**
 * PhpRedis client specific implementation.
 */
class PhpRedis implements ClientInterface {

  /**
   * {@inheritdoc}
   */
  public function getClient($host = NULL, $port = NULL, $base = NULL, $password = NULL) {
    $client = new \Redis();
    $client->connect($host, $port);

    if (isset($password)) {
      $client->auth($password);
    }

    if (isset($base)) {
      $client->select($base);
    }

    // Do not allow PhpRedis serialize itself data, we are going to do it
    // ourself. This will ensure less memory footprint on Redis size when
    // we will attempt to store small values.
    $client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'PhpRedis';
  }
}
