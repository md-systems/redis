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
    $keys = array_map(array($this, 'getKey'), $cids);
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

    foreach (array_values($cids) as $index => $key) {
      if (isset($result[$index]) && is_array($result[$index])) {
        // Map the cache ID back to the original.
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
    $entry = $this->createEntryHash($cid, $data, $expire, $tags);
    $this->client->hMset($this->getKey($cid), $entry);
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
    usleep(1000);
    $this->lastDeleteAll = round(microtime(TRUE), 3);
    $this->client->set($this->getKey(static::LAST_DELETE_ALL_KEY), $this->lastDeleteAll);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
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
    $tags[] = $this->getTagForBin();
    Cache::validateTags($tags);
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
