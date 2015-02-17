<?php

/**
 * @file
 * Contains \Drupal\redis\CacheBase.
 */

namespace Drupal\redis;

use \DateInterval;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

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
   * Default temporary cache items lifetime.
   */
  const LIFETIME_DEFAULT = 0;

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
   * Get TTL for CACHE_PERMANENT items.
   *
   * @return int
   *   Lifetime in seconds.
   */
  public function getPermTtl() {
    return $this->permTtl;
  }

  public function __construct($bin) {

    parent::__construct();

    $this->bin = $bin;

    $this->setPermTtl();
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
   * Return the key for the set holding the keys of deletable entries.
   */
  protected function getDeletedMetaSet() {
    return parent::getKey('meta/deleted');
  }

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
   * Return the key for the tags-by-cid set.
   */
  protected function getTagsByKeySet($key) {
    return parent::getKey('meta/tagsByKey:' . $key);
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
