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
class ShardedPhpRedis extends PhpRedis {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'ShardedPhpRedis';
  }

}
