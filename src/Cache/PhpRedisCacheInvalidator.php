<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\PhpRedisCacheInvalidator.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\redis\AbstractBackend;
use Drupal\redis\ClientFactory;

/**
 * PhpRedis cache backend.
 */
class PhpRedisCacheInvalidator extends AbstractBackend implements CacheTagsInvalidatorInterface {

  /**
   * Return the key for the set holding the keys of stale entries.
   */
  protected function getStaleMetaSet() {
    return parent::getKey('meta/stale');
  }

  /**
   * Return the key for the keys-by-tag set.
   */
  protected function getKeysByTagSet($tag) {
    return parent::getKey('meta/keysByTag:' . $tag);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    $client = ClientFactory::getClient();
    // Build a list of cache tags, the first entry is where to store, the
    // second is the same, so that existing entries are kept.
    $lists = [$this->getStaleMetaSet(), $this->getStaleMetaSet()];

    // Extend the list for each cache tag.
    foreach ($tags as $tag) {
      $lists[] = $this->getKeysByTagSet($tag);
    }
    // Execute the command.
    call_user_func_array(array($client, 'sUnionStore'), $lists);
  }

}
