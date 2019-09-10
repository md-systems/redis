<?php

namespace Drupal\redis\Client;

use Drupal\redis\ClientInterface;
use Predis\Client;


/**
 * Predis client specific implementation.
 */
class Predis implements ClientInterface {

  public function getClient($host = NULL, $port = NULL, $base = NULL, $password = NULL, $replicationHosts = NULL) {
    $connectionInfo = [
      'password' => $password,
      'host'     => $host,
      'port'     => $port,
      'database' => $base
    ];

    foreach ($connectionInfo as $key => $value) {
      if (!isset($value)) {
        unset($connectionInfo[$key]);
      }
    }

    // I'm not sure why but the error handler is driven crazy if timezone
    // is not set at this point.
    // Hopefully Drupal will restore the right one this once the current
    // account has logged in.
    date_default_timezone_set(@date_default_timezone_get());

    // If we are passed in an array of $replicationHosts, we should attempt a clustered client connection.
    if ($replicationHosts !== NULL) {
      $parameters = [];

      foreach ($replicationHosts as $replicationHost) {
        // Configure master.
        if ($replicationHost['role'] === 'primary') {
          $parameters[] = 'tcp://' . $replicationHost['host'] . ':' . $replicationHost['port'] . '?alias=master';
        }
        else {
          $parameters[] = 'tcp://' . $replicationHost['host'] . ':' . $replicationHost['port'];
        }
      }

      $options = ['replication' => true];
      $client = new Client($parameters, $options);
    }
    else {
      $client = new Client($connectionInfo);
    }
    return $client;

  }

  public function getName() {
    return 'Predis';
  }
}
