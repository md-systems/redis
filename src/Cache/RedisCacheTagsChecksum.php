<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\RedisCacheTagsChecksum.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\redis\ClientFactory;
use Drupal\redis\RedisPrefixTrait;

/**
 * Cache tags invalidations checksum implementation that uses redis.
 */
class RedisCacheTagsChecksum implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface {

  use RedisPrefixTrait;

  /**
   * Contains already loaded cache invalidations from the database.
   *
   * @var array
   */
  protected $tagCache = [];

  /**
   * A list of tags that have already been invalidated in this request.
   *
   * Used to prevent the invalidation of the same cache tag multiple times.
   *
   * @var array
   */
  protected $invalidatedTags = [];

  /**
   * @var \Redis
   */
  protected $client;

  /**
   * Creates a PHpRedis cache backend.
   */
  function __construct(ClientFactory $factory) {
    $this->client = $factory->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    foreach ($tags as $tag) {
      if (isset($this->invalidatedTags[$tag])) {
        // Only invalidate tags once per request unless they are written again.
        continue;
      }

      $tagKey = $this->getKey(['tag', $tag]);
      $current = $this->client->get($tagKey);

      $current = $this->getNextIncrement($current);
      $this->client->set($tagKey, $current);

      // Rightly populate the tag cache with the new values.
      $this->invalidatedTags[$tag] = TRUE;
      $this->tagCache[$tag] = $current;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentChecksum(array $tags) {
    // Remove tags that were already invalidated during this request from the
    // static caches so that another invalidation can occur later in the same
    // request. Without that, written cache items would not be invalidated
    // correctly.
    foreach ($tags as $tag) {
      unset($this->invalidatedTags[$tag]);
    }
    return $this->calculateChecksum($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($checksum, array $tags) {
    return $this->calculateChecksum($tags) <= $checksum;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateChecksum(array $tags) {
    $checksum = 0;

    foreach ($tags as $tag) {

      if (isset($this->tagCache[$tag])) {
        $current = $this->tagCache[$tag];
      }
      else {
        $tagKey = $this->getKey(['tag', $tag]);
        $current = $this->client->get($tagKey);

        if (!$current) {
          // Tag has never been created yet, so ensure it has an entry in Redis
          // database. When dealing in a sharded environment, the tag checksum
          // itself might have been dropped silently, case in which giving back
          // a 0 value can cause invalided cache entries to be considered as
          // valid back.
          // Note that doing that, in case a tag key was dropped by the holding
          // Redis server, all items based upon the droppped tag will then become
          // invalid, but that's the definitive price of trying to being
          // consistent in all cases.
          $current = $this->getNextIncrement();
          $this->client->set($tagKey, $current);
        }

        $this->tagCache[$tag] = $current;
      }

      if ($checksum < $current) {
        $checksum = $current;
      }
    }

    return $checksum;
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->tagCache = [];
    $this->invalidatedTags = [];
  }

}
