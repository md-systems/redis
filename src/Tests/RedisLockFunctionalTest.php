<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\WebTest.
 */

namespace Drupal\redis\Tests;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Core\Site\Settings;
use Drupal\system\Tests\Lock\LockFunctionalTest;

/**
 * Confirm locking works between two separate requests.
 *
 * @group redis
 */
class RedisLockFunctionalTest extends LockFunctionalTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['redis'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Write the containers_yaml update by hand, since writeSettings() doesn't
    // support this syntax.
    $filename = $this->siteDirectory . '/settings.php';
    chmod($filename, 0666);
    $contents = file_get_contents($filename);
    $contents .= "\n\n" . '$settings[\'container_yamls\'][] = \'modules/redis/example.services.yml\';';
    file_put_contents($filename, $contents);
    $settings = Settings::getAll();
    $settings['container_yamls'][] = 'modules/redis/example.services.yml';
    new Settings($settings);
    OpCodeCache::invalidate(DRUPAL_ROOT . '/' . $filename);

    $this->rebuildContainer();

    // Make sure that the semaphore table isn't used.
    db_drop_table('semaphore');
  }

}
