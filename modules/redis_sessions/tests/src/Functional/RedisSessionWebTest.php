<?php

namespace Drupal\Tests\redis_sessions\Functional;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Core\Site\Settings;
use Drupal\Tests\redis\Functional\WebTest;

/**
 * Tests complex processes like installing modules with redis backends.
 *
 * @group redis
 * @group redis_sessions
 */
class RedisSessionWebTest extends WebTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'redis_sessions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Set in-memory settings.
    $settings = Settings::getAll();
    $settings['container_yamls'][] = drupal_get_path('module', 'redis_sessions') . '/example.services.yml';
    new Settings($settings);

    // Write the containers_yaml update by hand, since writeSettings() doesn't
    // support some of the definitions.
    $filename = $this->siteDirectory . '/settings.php';
    chmod($filename, 0666);
    $contents = file_get_contents($filename);

    // Add the container_yaml and cache definition.
    $contents .= "\n\n" . '$settings["container_yamls"][] = "' . drupal_get_path('module', 'redis_sessions') . '/example.services.yml";';
    file_put_contents($filename, $contents);
    OpCodeCache::invalidate(DRUPAL_ROOT . '/' . $filename);

    $this->rebuildContainer();
  }

}
