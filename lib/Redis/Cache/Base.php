<?php

abstract class Redis_Cache_Base implements DrupalCacheInterface {
  /**
   * @var string
   */
  protected static $prefix;

  /**
   * @var string
   */
  protected $bin;

  function __construct($bin) {
    $this->bin = $bin;
  }

  protected function getKey($cid) {
    if (!isset(self::$prefix)) {
      if (isset($GLOBALS['drupal_test_info']) && !empty($test_info['test_run_id'])) {
        self::$prefix = $test_info['test_run_id'];
      } else {
        self::$prefix = variable_get('cache_prefix', $_SERVER['HTTP_HOST'] . '_');
      }
    }
    return self::$prefix . $this->bin . ':' . $cid;
  }
}
