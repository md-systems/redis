<?php

namespace Drupal\Tests\redis\Functional;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Site\Settings;
use Drupal\field_ui\Tests\FieldUiTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests complex processes like installing modules with redis backends.
 *
 * @group redis
 */
class WebTest extends BrowserTestBase {

  use FieldUiTestTrait;
  use RedisTestInterfaceTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['redis', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');

    // Set in-memory settings.
    $settings = Settings::getAll();

    // Get REDIS_INTERFACE env variable.
    $redis_interface = self::getRedisInterfaceEnv();
    $settings['redis.connection']['interface'] = $redis_interface;

    $settings['cache'] = [
      'default' => 'cache.backend.redis',
    ];

    $settings['container_yamls'][] = drupal_get_path('module', 'redis') . '/example.services.yml';

    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'redis.factory' => [
          'class' => 'Drupal\redis\ClientFactory',
        ],
        'cache.backend.redis' => [
          'class' => 'Drupal\redis\Cache\CacheBackendFactory',
          'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
        ],
        'cache.container' => [
          'class' => '\Drupal\redis\Cache\PhpRedis',
          'factory' => ['@cache.backend.redis', 'get'],
          'arguments' => ['container'],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
          'arguments' => ['@redis.factory'],
        ],
        'serialization.phpserialize' => [
          'class' => 'Drupal\Component\Serialization\PhpSerialize',
        ],
      ],
    ];
    new Settings($settings);

    // Write the containers_yaml update by hand, since writeSettings() doesn't
    // support some of the definitions.
    $filename = $this->siteDirectory . '/settings.php';
    chmod($filename, 0666);
    $contents = file_get_contents($filename);

    // Add the container_yaml and cache definition.
    $contents .= "\n\n" . '$settings["container_yamls"][] = "' . drupal_get_path('module', 'redis') . '/example.services.yml";';
    $contents .= "\n\n" . '$settings["cache"] = ' . var_export($settings['cache'], TRUE) . ';';

    // Add the classloader.
    $contents .= "\n\n" . '$class_loader->addPsr4(\'Drupal\\\\redis\\\\\', \'' . drupal_get_path('module', 'redis') . '/src\');';

    // Add the bootstrap container definition.
    $contents .= "\n\n" . '$settings["bootstrap_container_definition"] = ' . var_export($settings['bootstrap_container_definition'], TRUE) . ';';

    file_put_contents($filename, $contents);
    OpCodeCache::invalidate(DRUPAL_ROOT . '/' . $filename);

    // Reset the cache factory.
    $this->container->set('cache.factory', NULL);
    $this->rebuildContainer();

    // Make sure that the cache and lock tables aren't used.
    db_drop_table('cache_default');
    db_drop_table('cache_render');
    db_drop_table('cache_config');
    db_drop_table('cache_container');
    db_drop_table('cachetags');
    db_drop_table('semaphore');
    db_drop_table('flood');
  }

  /**
   * Tests enabling modules and creating configuration.
   */
  public function testModuleInstallation() {
    $admin_user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin_user);

    // Enable a few modules.
    $edit["modules[node][enable]"] = TRUE;
    $edit["modules[views][enable]"] = TRUE;
    $edit["modules[field_ui][enable]"] = TRUE;
    $edit["modules[text][enable]"] = TRUE;
    $this->drupalPostForm('admin/modules', $edit, t('Install'));
    $this->drupalPostForm(NULL, [], t('Continue'));

    $assert = $this->assertSession();

    // The order of the modules is not guaranteed, so just assert that they are
    // all listed.
    $assert->elementTextContains('css', '.messages--status', '6 modules have been enabled');
    $assert->elementTextContains('css', '.messages--status', 'Field UI');
    $assert->elementTextContains('css', '.messages--status', 'Node');
    $assert->elementTextContains('css', '.messages--status', 'Text');
    $assert->elementTextContains('css', '.messages--status', 'Views');
    $assert->elementTextContains('css', '.messages--status', 'Field');
    $assert->elementTextContains('css', '.messages--status', 'Filter');
    $assert->checkboxChecked('edit-modules-field-ui-enable');

    // Create a node type with a field.
    $edit = [
      'name' => $this->randomString(),
      'type' => $node_type = Unicode::strtolower($this->randomMachineName()),
    ];
    $this->drupalPostForm('admin/structure/types/add', $edit, t('Save and manage fields'));
    $field_name = Unicode::strtolower($this->randomMachineName());
    $this->fieldUIAddNewField('admin/structure/types/manage/' . $node_type, $field_name, NULL, 'text');

    // Create a node, check display, edit, verify that it has been updated.
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'body[0][value]' => $this->randomMachineName(),
      'field_' . $field_name . '[0][value]' => $this->randomMachineName(),
    ];
    $this->drupalPostForm('node/add/' . $node_type, $edit, t('Save and publish'));

    // Test the output as anonymous user.
    $this->drupalLogout();
    $this->drupalGet('node');
    $this->assertText($edit['title[0][value]']);

    $this->drupalLogin($admin_user);
    $this->drupalGet('node');
    $this->clickLink($edit['title[0][value]']);
    $this->assertText($edit['body[0][value]']);
    $this->clickLink(t('Edit'));
    $update = [
      'title[0][value]' => $this->randomMachineName(),
    ];
    $this->drupalPostForm(NULL, $update, t('Save and keep published'));
    $this->assertText($update['title[0][value]']);
    $this->drupalGet('node');
    $this->assertText($update['title[0][value]']);

    $this->drupalLogout();
    $this->drupalGet('node');
    $this->clickLink($update['title[0][value]']);
    $this->assertText($edit['body[0][value]']);

    $this->assertFalse(db_table_exists('cache_default'));
    $this->assertFalse(db_table_exists('cache_render'));
    $this->assertFalse(db_table_exists('cache_config'));
    $this->assertFalse(db_table_exists('cache_container'));
    $this->assertFalse(db_table_exists('cachetags'));
    $this->assertFalse(db_table_exists('semaphore'));
    $this->assertFalse(db_table_exists('flood'));
  }

}
