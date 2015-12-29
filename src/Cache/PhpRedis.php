<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\PhpRedis.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsChecksumInterface;

/**
 * PhpRedis cache backend.
 */
class PhpRedis extends CacheBase {

  /**
   * @var \Redis
   */
  protected $client;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface|\Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $checksumProvider;

  /**
   * The last delete timestamp.
   *
   * @var float
   */
  protected $lastDeleteAll = NULL;

  /**
   * Creates a PHpRedis cache backend.
   */
  function __construct($bin, \Redis $client, CacheTagsChecksumInterface $checksum_provider) {
    parent::__construct($bin);
    $this->client = $client;
    $this->checksumProvider = $checksum_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    // Avoid an error when there are no cache ids.
    if (empty($cids)) {
      return [];
    }

    $return = array();

    // Build the list of keys to fetch.
    $keys = array_map(array($this, 'getKey'), $cids);

    // Optimize for the common case when only a single cache entry needs to
    // be fetched, no pipeline is needed then.
    if (count($keys) > 1) {
      $pipe = $this->client->multi(\Redis::PIPELINE);
      foreach ($keys as $key) {
        $pipe->hgetall($key);
      }
      $result = $pipe->exec();
    }
    else {
      $result = [$this->client->hGetAll(reset($keys))];
    }

    // Loop over the cid values to ensure numeric indexes.
    foreach (array_values($cids) as $index => $key) {
      // Check if a valid result was returned from Redis.
      if (isset($result[$index]) && is_array($result[$index])) {
        // Check expiration and invalidation and convert into an object.
        $item = $this->expandEntry($result[$index], $allow_invalid);
        if ($item) {
          $return[$item->cid] = $item;
        }
      }
    }

    // Remove fetched cids from the list.
    $cids = array_diff($cids, array_keys($return));

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = array()) {

    $ttl = $this->getExpiration($expire);

    $key = $this->getKey($cid);

    // If the item is already expired, delete it.
    if ($ttl <= 0) {
      $this->delete($key);
    }

    // Build the cache item and save it as a hash array.
    $entry = $this->createEntryHash($cid, $data, $expire, $tags);
    $pipe = $this->client->multi(\REdis::PIPELINE);
    $pipe->hMset($key, $entry);
    $pipe->expire($key, $ttl);
    $pipe->exec();
  }


  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $keys = array_map(array($this, 'getKey'), $cids);
    $this->client->del($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    // The last delete timestamp is in milliseconds, ensure that no cache
    // was written in the same millisecond.
    // @todo This is needed to make the tests pass, is this safe enough for real
    //   usage?
    usleep(1000);
    $this->lastDeleteAll = round(microtime(TRUE), 3);
    $this->client->set($this->getKey(static::LAST_DELETE_ALL_KEY), $this->lastDeleteAll);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    // Loop over all cache items, they are stored as a hash, so we can access
    // the valid flag directly, only write if it exists and is not 0.
    foreach ($cids as $cid) {
      $key = $this->getKey($cid);
      if ($this->client->hGet($key, 'valid')) {
        $this->client->hSet($key, 'valid', 0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    // To invalidate the whole bin, we invalidate a special tag for this bin.
    $this->checksumProvider->invalidateTags([$this->getTagForBin()]);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    // @todo Do we need to do anything here?
  }

  /**
   *  Returns the last delete all timestamp.
   *
   * @return float
   *   The last delete timestamp as a timestamp with a millisecond precision.
   */
  protected function getLastDeleteAll() {
    // Cache the last delete all timestamp.
    if ($this->lastDeleteAll === NULL) {
      $this->lastDeleteAll = (float) $this->client->get($this->getKey(static::LAST_DELETE_ALL_KEY));
    }
    return $this->lastDeleteAll;
  }

  /**
   * Create cache entry.
   *
   * @param string $cid
   * @param mixed $data
   * @param int $expire
   * @param string[] $tags
   *
   * @return array
   */
  protected function createEntryHash($cid, $data, $expire = Cache::PERMANENT, array $tags) {
    // Always add a cache tag for the current bin, so that we can use that for
    // invalidateAll().
    $tags[] = $this->getTagForBin();
    assert('\Drupal\Component\Assertion\Inspector::assertAllStrings($tags)', 'Cache Tags must be strings.');
    $hash = array(
      'cid' => $cid,
      'created' => round(microtime(TRUE), 3),
      'expire' => $expire,
      'tags' => implode(' ', $tags),
      'valid' => 1,
      'checksum' => $this->checksumProvider->getCurrentChecksum($tags),
    );

    // Let Redis handle the data types itself.
    if (!is_string($data)) {
      $hash['data'] = serialize($data);
      $hash['serialized'] = 1;
    }
    else {
      $hash['data'] = $data;
      $hash['serialized'] = 0;
    }

    return $hash;
  }

  /**
   * Prepares a cached item.
   *
   * Checks that items are either permanent or did not expire, and unserializes
   * data as appropriate.
   *
   * @param array $values
   *   The hash returned from redis or false.
   * @param bool $allow_invalid
   *   If FALSE, the method returns FALSE if the cache item is not valid.
   *
   * @return mixed|false
   *   The item with data unserialized as appropriate and a property indicating
   *   whether the item is valid, or FALSE if there is no valid item to load.
   */
  protected function expandEntry(array $values, $allow_invalid) {
    // Check for entry being valid.
    if (empty($values['cid'])) {
      return FALSE;
    }

    $cache = (object) $values;

    $cache->tags = explode(' ', $cache->tags);

    // Check expire time, allow to have a cache invalidated explicitly, don't
    // check if already invalid.
    if ($cache->valid) {
      $cache->valid = $cache->expire == Cache::PERMANENT || $cache->expire >= REQUEST_TIME;

      // Check if invalidateTags() has been called with any of the items's tags.
      if ($cache->valid && !$this->checksumProvider->isValid($cache->checksum, $cache->tags)) {
        $cache->valid = FALSE;
      }
    }

    // Ensure the entry does not predate the last delete all time.
    $last_delete_timestamp = $this->getLastDeleteAll();
    if ($last_delete_timestamp && ((float)$values['created']) < $last_delete_timestamp) {
      return FALSE;
    }

    if (!$allow_invalid && !$cache->valid) {
      return FALSE;
    }

    if ($cache->serialized) {
      $cache->data = unserialize($cache->data);
    }

    return $cache;
  }

}
