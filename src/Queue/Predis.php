<?php

/**
 * @file
 * Contains \Drupal\redis\Queue\Predis.
 */

namespace Drupal\redis\Queue;

/**
 * Redis queue implementation using Predis library backend.
 *
 * @ingroup queue
 */
class Predis extends QueueBase {

  /**
   * The Redis connection.
   *
   * @var \Predis\Client $client
   */
  protected $client;

  /**
   * Constructs a \Drupal\redis\Queue\Predis object.
   *
   * @param string $name
   *   The name of the queue.
   * @param array $settings
   *   Array of Redis-related settings for this queue.
   * @param \Predis\Client $client
   *   The Predis client.
   */
  function __construct($name, array $settings, \Predis\Client $client) {
    parent::__construct($name, $settings);
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    // TODO: Fixme
    $record = new \stdClass();
    $record->data = $data;
    $record->qid = $this->incrementId();
    // We cannot rely on REQUEST_TIME because many items might be created
    // by a single request which takes longer than 1 second.
    $record->timestamp = time();

    if (!$this->client->hsetnx($this->availableItems, $record->qid, serialize($record))) {
      return FALSE;
    }

    $start_len = $this->client->lLen($this->availableListKey);
    if ($start_len < $this->client->lpush($this->availableListKey, $record->qid)) {
      return $record->qid;
    }
  }

  /**
   * Gets next serial ID for Redis queue items.
   *
   * @return int
   *   Next serial ID for Redis queue item.
   */
  protected function incrementId() {
    // TODO: Fixme
    return $this->client->incr($this->incrementCounterKey);
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    // TODO: Fixme
    return $this->client->lLen($this->availableListKey);
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    // TODO: Fixme
    $item = FALSE;
    $qid = $this->client->rpoplpush($this->avail, $this->claimed);
    if ($qid) {
      $job = $this->client->hget($this->avail . '_hash', $qid);
      if ($job) {
        $item = unserialize($job);
        $this->client->setex($this->lease . $item->qid, $lease_time, '1');
      }
    }

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    // TODO: Fixme
    $this->client->lrem($this->claimedListKey, $item->qid, -1);
    $this->client->lpush($this->availableListKey, $item->qid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    // TODO: Fixme
    $this->client->lrem($this->claimedListKey, $item->qid, -1);
    $this->client->hdel($this->availableItems, $item->qid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    // TODO: Fixme
    $keys_to_remove = [
      $this->claimedListKey,
      $this->availableListKey,
      $this->availableItems,
      $this->incrementCounterKey
    ];

    foreach ($this->client->keys($this->leasedKeyPrefix . '*') as $key) {
      $keys_to_remove[] = $key;
    }

    $this->client->del($keys_to_remove);
  }
}
