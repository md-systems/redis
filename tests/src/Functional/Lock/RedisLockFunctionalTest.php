<?php

namespace Drupal\Tests\redis\Functional\Lock;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;
use Drupal\Tests\system\Functional\Lock\LockFunctionalTest;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Confirm locking works between two separate requests.
 *
 * @group redis
 */
class RedisLockFunctionalTest extends LockFunctionalTest {

  use RedisTestInterfaceTrait;

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
    $redis_interface = self::getRedisInterfaceEnv();
    $contents .= "\n\n" . '$settings[\'container_yamls\'][] = \'modules/redis/example.services.yml\';';
    $contents .= "\n\n" . '$settings["redis.connection"]["interface"] = \'' . $redis_interface . '\';';
    file_put_contents($filename, $contents);
    $settings = Settings::getAll();
    $settings['container_yamls'][] = 'modules/redis/example.services.yml';
    $settings['redis.connection']['interface'] = '\'' .  $redis_interface . '\'';
    new Settings($settings);
    OpCodeCache::invalidate(DRUPAL_ROOT . '/' . $filename);

    $this->rebuildContainer();

    // Get database schema.
    $db_schema = Database::getConnection()->schema();
    // Make sure that the semaphore table isn't used.
    $db_schema->dropTable('semaphore');
  }

}
