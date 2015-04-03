<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\CacheBase.
 */

namespace Drupal\redis\Cache;

use \DateInterval;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\redis\AbstractBackend;
use Drupal\redis\ClientInterface;

/**
 * Because those objects will be spawned during bootsrap all its configuration
 * must be set in the settings.php file.
 *
 * For a detailed history of flush modes see:
 *   https://drupal.org/node/1980250
 */
abstract class CacheBase extends AbstractBackend implements CacheBackendInterface {

  /**
   * Temporary cache items lifetime is infinite.
   */
  const LIFETIME_INFINITE = 0;

  /**
   * Default lifetime for permanent items.
   * Approximatively 1 year.
   */
  const LIFETIME_PERM_DEFAULT = 31536000;

  /**
   * Computed keys are let's say arround 60 characters length due to
   * key prefixing, which makes 1,000 keys DEL command to be something
   * arround 50,000 bytes length: this is huge and may not pass into
   * Redis, let's split this off.
   * Some recommend to never get higher than 1,500 bytes within the same
   * command which makes us forced to split this at a very low threshold:
   * 20 seems a safe value here (1,280 average length).
   */
  const KEY_THRESHOLD = 20;

  /**
   * Latest delete all flush KEY name.
   */
  const LAST_DELETE_ALL_KEY = '_redis_last_delete_all';

  /**
   * @var string
   */
  protected $bin;

  /**
   * Default TTL for CACHE_PERMANENT items.
   *
   * See "Default lifetime for permanent items" section of README.txt
   * file for a comprehensive explaination of why this exists.
   *
   * @var int
   */
  protected $permTtl = self::LIFETIME_PERM_DEFAULT;

  /**
   * Minimal TTL to use.
   *
   * Note that this is for testing purposes. Do not specify the minimal TTL
   * outside of unit-tests.
   */
  protected $minTtl = 0;

  /**
   * @var \Drupal\redis\ClientInterface
   */
  protected $client;

  /**
   * Get TTL for CACHE_PERMANENT items.
   *
   * @return int
   *   Lifetime in seconds.
   */
  public function getPermTtl() {
    return $this->permTtl;
  }

  function __construct($bin) {
    parent::__construct();
    $this->bin = $bin;
    $this->setPermTtl();
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $cids = array($cid);
    $cache = $this->getMultiple($cids, $allow_invalid);
    return reset($cache);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      $this->set($cid, $item['data'], isset($item['expire']) ? $item['expire'] : CacheBackendInterface::CACHE_PERMANENT, isset($item['tags']) ? $item['tags'] : array());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->deleteMultiple([$cid]);
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->invalidateMultiple([$cid]);
  }

  /**
   * Return the key for the given cache-id.
   */
  public function getKey($cid = NULL) {
    if (NULL === $cid) {
      return parent::getKey($this->bin);
    }
    else {
      return parent::getKey($this->bin . ':' . $cid);
    }
  }

  /**
   * Calculate the correct expiration time.
   *
   * @todo Make the default configurable.
   *
   * @param int $expire
   *   The expiration time provided for the cache set.
   *
   * @return int
   *   The default expiration if expire is PERMANENT or higher than the default.
   *   May return negative values if the item is already expired.
   */
  protected function getExpiration($expire) {
    if ($expire == Cache::PERMANENT || $expire > static::LIFETIME_PERM_DEFAULT) {
      return static::LIFETIME_PERM_DEFAULT;
    }
    return $expire - REQUEST_TIME;
  }

  /**
   * Return the key for the tag used to specify the bin of cache-entries.
   */
  protected function getTagForBin() {
    return 'x-redis-bin:' . $this->bin;
  }

  /**
   * Set the minimum TTL (unit testing only).
   */
  public function setMinTtl($ttl) {
    $this->minTtl = $ttl;
  }

  /**
   * Set the permanent TTL.
   */
  public function setPermTtl($ttl = NULL) {
    if (isset($ttl)) {
      $this->permTtl = $ttl;
    }
    else {
      // Attempt to set from globals.
      global $config;
      if (isset($config['redis.settings']['perm_ttl_' . $this->bin])) {
        $ttl = $config['redis.settings']['perm_ttl_' . $this->bin];
        if ($ttl === (int) $ttl) {
          $this->permTtl = $ttl;
        }
        else {
          if ($iv = DateInterval::createFromDateString($ttl)) {
            // http://stackoverflow.com/questions/14277611/convert-dateinterval-object-to-seconds-in-php
            $this->permTtl = ($iv->y * 31536000 + $iv->m * 2592000 + $iv->days * 86400 + $iv->h * 3600 + $iv->i * 60 + $iv->s);
          }
          else {
            // Sorry but we have to log this somehow.
            // @todo throw exception instead?
            trigger_error(sprintf("Parsed TTL '%s' has an invalid value: switching to default", $ttl));
            $this->permTtl = self::LIFETIME_PERM_DEFAULT;
          }

        }
      }
    }
  }

}
