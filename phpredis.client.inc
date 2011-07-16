<?php

/**
 * @file
 * PhpRedis client handling.
 */

require_once dirname(__FILE__) . '/redis.common.inc';

/**
 * Specific implementation.
 */
class Redis_Client_Proxy_PhpRedis implements Redis_Client_Proxy_Interface {

  public function getClient($host = NULL, $port = NULL, $base = NULL) {
    $client = new Redis;
    $client->connect($host, $port);

    if (isset($base)) {
      $client->select($base);
    }

    // Do not allow PhpRedis serialize itself data, we are going to do it
    // ourself. This will ensure less memory footprint on Redis size when
    // we will attempt to store small values.
    $client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);

    return $client;
  }

  public function getName() {
    return 'PhpRedis';
  }
}

// Register client proxy.
Redis_Client::setClient(new Redis_Client_Proxy_PhpRedis);
