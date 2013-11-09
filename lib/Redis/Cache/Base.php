<?php

/**
 * Because those objects will be spawned during boostrap all its configuration
 * must be set in the settings.php file.
 *
 * For a detailed history of flush modes see:
 *   https://drupal.org/node/1980250
 */
abstract class Redis_Cache_Base implements DrupalCacheInterface
{
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
   * @var string
   */
  protected $prefix;

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

  /**
   * Get prefix for bin using the configuration.
   * 
   * @param string $bin
   * 
   * @return string
   *   Can be an empty string, if no prefix set.
   */
  protected static function getPrefixForBin($bin) {
    if (isset($GLOBALS['drupal_test_info']) && !empty($test_info['test_run_id'])) {
      return $test_info['test_run_id'];
    } else {
      $prefixes = variable_get('cache_prefix', '');

      if (is_string($prefixes)) {
        // Variable can be a string, which then considered as a default behavior.
        return $prefixes;
      }

      if (isset($prefixes[$bin])) {
        if (FALSE !== $prefixes[$bin]) {
          // If entry is set and not FALSE, an explicit prefix is set for the bin.
          return $prefixes[$bin];
        } else {
          // If we have an explicit false, it means no prefix whatever is the
          // default configuration.
          return '';
        }
      } else {
        // Key is not set, we can safely rely on default behavior.
        if (isset($prefixes['default']) && FALSE !== $prefixes['default']) {
          return $prefixes['default'];
        } else {
          // When default is not set or an explicit FALSE, this means no prefix.
          return '';
        }
      }
    }
  }

  function __construct($bin) {
    $this->bin = $bin;

    $prefix = self::getPrefixForBin($this->bin);

    if (empty($prefix) && isset($_SERVER['HTTP_HOST'])) {
      // Provide a fallback for multisite. This is on purpose not inside the
      // getPrefixForBin() function in order to decouple the unified prefix
      // variable logic and custom module related security logic, that is not
      // necessary for all backends.
      $this->prefix = $_SERVER['HTTP_HOST'] . '_';
    } else {
      $this->prefix = $prefix;
    }

    if (null !== ($mode = variable_get('redis_flush_mode_' . $this->bin))) {
      // A bin specific flush mode has been set.
      $this->clearMode = (int)$mode;
    } else if (null !== ($mode = variable_get('redis_flush_mode'))) {
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

  protected function getKey($cid) {
    return $this->prefix . $this->bin . ':' . $cid;
  }
}
