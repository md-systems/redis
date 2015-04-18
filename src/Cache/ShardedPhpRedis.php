<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\PhpRedis.
 */

namespace Drupal\redis\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;

/**
 * PhpRedis cache backend.
 */
class ShardedPhpRedis extends CacheBase {

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
   * Creates a PHpRedis cache backend.
   */
  function __construct($bin, \Redis $client, CacheTagsChecksumInterface $checksum_provider) {
    parent::__construct($bin);
    $this->client = $client;
    $this->checksumProvider = $checksum_provider;
  }

  /**
   * Set the last flush timestamp
   *
   * @param boolean $overwrite
   *   If set the method won't try to load the existing value before
   *
   * @return string
   */
  protected function setLastFlushTime($overwrite = false) {

    $key = $this->getKey('_flush');
    $time = time();

    $flushTime = $this->client->get($key);

    if ($flushTime && $time === (int)$flushTime) {
      $flushTime = $this->getNextIncrement($flushTime);
    } else {
      $flushTime = $this->getNextIncrement($time);
    }

    $this->client->set($key, $flushTime);

    return $flushTime;
  }

  /**
   * Get the last flush timestamp
   *
   * @return string
   */
  protected function getLastFlushTime() {

    $flushTime = $this->client->get($this->getKey('_flush'));

    if (!$flushTime) {
      // In case there is no last flush data consider that the cache backend
      // is actually pending an inconsistent state, the 'flush' key might
      // disappear anytime a server is replaced or manually flushed. Please
      // note that the initial flush timestamp is set when an entry is set
      // too.
      $flushTime = $this->setLastFlushTime();
    }

    return $flushTime;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {

    $entryKey = $this->getKey($cid);
    $item = $this->client->hGetAll($entryKey);

    if (!$item) {
      return FALSE;
    }

    $item = (object)$item;
    $item->tags = explode(',', $item->tags);
    $item->valid = (bool)$item->valid;
    $item->expire = (int)$item->expire;
    $item->ttl = (int)$item->ttl;

    if (!$item->valid && $item->ttl === 600 ) {
      // @todo This is ugly but we are int the case where an already expired
      // entry was set previously, this means that we are probably in the unit
      // tests and we should not delete this entry to make core tests happy.
      if (!$allow_invalid) {
        return FALSE;
      }
    } else if ($item->valid && !$allow_invalid) {

      if (Cache::PERMANENT !== $item->expire && $item->expire < time()) {
        $this->client->del($entryKey);
        return FALSE;
      }

      $lastFlush = $this->getLastFlushTime();
      if ($item->created < $lastFlush) {
        $this->client->del($entryKey);
        return FALSE;
      }

      if (!$this->checksumProvider->isValid($item->checksum, $item->tags)) {
        $this->client->del($entryKey);
        return FALSE;
      }
    }

    $item->data = unserialize($item->data);
    $item->created = (int)$item->created;

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $ret = [];

    // @todo Unperformant, but in a sharded environement we
    // cannot proceed another way, still there are some paths
    // to explore
    foreach ($cids as $index => $cid) {
      $item = $this->get($cid, $allow_invalid);
      if ($item) {
        $ret[$cid] = $item;
        unset($cids[$index]);
      }
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = array()) {

    Cache::validateTags($tags);

    $time = time();
    $created = null;
    $entryKey = $this->getKey($cid);
    $lastFlush = $this->getLastFlushTime();

    if ($time === (int)$lastFlush) {
      // Latest flush happened the exact same second.
      $created = $lastFlush;
    } else {
      $created = $this->getNextIncrement($time);
    }

    $valid = true;

    if (Cache::PERMANENT !== $expire) {
      if ($expire <= $time) {
        // And existing entry if any is stalled
        // $this->client->del($entryKey);
        // return;
        // @todo I am definitely not fan of this, this only serves
        // the purpose of letting the generic core unit tests to
        // work with us.
        $valid = false;
        // @todo This might happen during tests to check that invalid entries
        // can be fetched, I do not like this. This invalid features mostly
        // serves some edge caching cases, let's set a very small cache life
        // time.
        $ttl = 600;
      } else {
        $ttl = $expire - $time;
      }
    } else {
      $ttl = $expire;
    }

    // 0 for tag means it never has been deleted
    $checksum = $this->checksumProvider->getCurrentChecksum($tags);

    $this->client->hMset($entryKey, [
      'cid'      => $cid,
      'created'  => $created,
      'checksum' => $checksum,
      'expire'   => $expire,
      'ttl'      => $ttl,
      'data'     => serialize($data),
      'tags'     => implode(',', $tags),
      'valid'    => (int)$valid,
    ]);

    if ($expire !== Cache::PERMANENT) {
      $this->client->expire($entryKey, $ttl);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      $item += [
        'data'   => null,
        'expire' => Cache::PERMANENT,
        'tags'   => [],
      ];
      $this->set($cid, $item['data'], $item['expire'], $item['tags']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->client->del($this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    foreach ($cids as $cid) {
      $this->client->del($this->getKey($cid));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->setLastFlushTime();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $entryKey = $this->getKey($cid);
    if ($this->client->hGet($entryKey, 'valid')) {
      $this->client->hMset($entryKey, [
        'valid' => 0,
        // @todo This one if for unit tests only, sorry...
        'ttl' => 600,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    foreach ($cids as $cid) {
      $this->invalidate($cid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    // @todo Will this make tests fail again?! 
    $this->setLastFlushTime();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    // Ah! Ah! Seriously...
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    // I'm sorry but this bin will have to wait the max TTL has been reached
    // for all items: in the sharded environement, especially when there is
    // a sharding proxy there is no way on earth we can scan our data. 
  }

}
