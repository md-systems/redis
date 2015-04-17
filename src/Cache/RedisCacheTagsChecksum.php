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
  protected $tagCache = array();

  /**
   * A list of tags that have already been invalidated in this request.
   *
   * Used to prevent the invalidation of the same cache tag multiple times.
   *
   * @var array
   */
  protected $invalidatedTags = array();

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
    $keys_to_increment = [];
    foreach ($tags as $tag) {
      // Only invalidate tags once per request unless they are written again.
      if (isset($this->invalidatedTags[$tag])) {
        continue;
      }
      $this->invalidatedTags[$tag] = TRUE;
      unset($this->tagCache[$tag]);
      $keys_to_increment[] = $this->getTagKey($tag);
    }
    if ($keys_to_increment) {
      $multi = $this->client->multi(\Redis::PIPELINE);
      foreach ($keys_to_increment as $key) {
        $multi->incr($key);
      }
      $multi->exec();
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
    return $checksum == $this->calculateChecksum($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateChecksum(array $tags) {
    $checksum = 0;

    $fetch = array_values(array_diff($tags, array_keys($this->tagCache)));
    if ($fetch) {
      $keys = array_map(array($this, 'getTagKey'), $fetch);
      foreach ($this->client->mget($keys) as $index => $invalidations) {
        $this->tagCache[$fetch[$index]] = $invalidations ?: 0;
      }
    }

    foreach ($tags as $tag) {
      $checksum += $this->tagCache[$tag];
    }

    return $checksum;
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->tagCache = array();
    $this->invalidatedTags = array();
  }

  /**
   * Return the key for the given cache tag.
   *
   * @param string $tag
   *   The cache tag.
   *
   * @return string
   *   The prefixed cache tag.
   */
  protected function getTagKey($tag) {
    return $this->getPrefix() . ':cachetags:' . $tag;
  }

}
