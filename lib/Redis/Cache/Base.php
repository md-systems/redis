<?php

/**
 * Because those objects will be spawned during boostrap all its configuration
 * must be set in the settings.php file.
 *
 * For a detailed history of flush modes see:
 *   https://drupal.org/node/1980250
 */
abstract class Redis_Cache_Base extends Redis_AbstractBackend implements
    DrupalCacheInterface
{
  /**
   * Temporary cache items lifetime is infinite.
   */
  const LIFETIME_INFINITE = 0;

  /**
   * Default temporary cache items lifetime.
   */
  const LIFETIME_DEFAULT = 0;

  /**
   * Flush nothing on generic clear().
   *
   * Because Redis handles keys TTL by itself we don't need to pragmatically
   * flush items by ourselves in most case: only 2 exceptions are the "page"
   * and "block" bins which are never expired manually outside of cron.
   */
  const FLUSH_NOTHING = 0;

  /**
   * Flush only temporary on generic clear().
   *
   * This dictate the cache backend to behave as the DatabaseCache default
   * implementation. This behavior is not documented anywere but hardcoded
   * there.
   */
  const FLUSH_TEMPORARY = 1;

  /**
   * Flush all on generic clear().
   *
   * This is a failsafe performance unwise behavior that probably no
   * one should ever use: it will force all items even those which are
   * still valid or permanent to be flushed. It exists only in order
   * to mimic the behavior of the pre 1.0 release of this module.
   */
  const FLUSH_ALL = 2;

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
   * Temporary items SET name.
   */
  const TEMP_SET = 'temporary_items';

  /**
   * @var string
   */
  protected $bin;

  /**
   * @var int
   */
  protected $clearMode = self::FLUSH_TEMPORARY;

  /**
   * Get clear mode.
   *
   * @return int
   *   One of the Redis_Cache_Base::FLUSH_* constant.
   */
  public function getClearMode() {
    return $this->clearMode;
  }

  public function __construct($bin) {

    parent::__construct();

    $this->bin = $bin;

    if (null !== ($mode = variable_get('redis_flush_mode_' . $this->bin, null))) {
      // A bin specific flush mode has been set.
      $this->clearMode = (int)$mode;
    } else if (null !== ($mode = variable_get('redis_flush_mode', null))) {
      // A site wide generic flush mode has been set.
      $this->clearMode = (int)$mode;
    } else {
      // No flush mode is set by configuration: provide sensible defaults.
      // See FLUSH_* constants for comprehensible explaination of why this
      // exists.
      switch ($this->bin) {

        case 'cache_page':
        case 'cache_block':
          $this->clearMode = self::FLUSH_TEMPORARY;
          break;

        default:
          $this->clearMode = self::FLUSH_NOTHING;
          break;
      }
    }
  }

  public function getKey($cid = null) {
    if (null === $cid) {
      return parent::getKey($this->bin);
    } else {
      return parent::getKey($this->bin . ':' . $cid);
    }
  }
}
