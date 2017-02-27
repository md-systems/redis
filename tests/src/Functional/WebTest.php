<?php

namespace Drupal\Tests\redis\Functional;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Site\Settings;
use Drupal\field_ui\Tests\FieldUiTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests complex processes like installing modules with redis backends.
 *
 * @group redis
 */
class WebTest extends BrowserTestBase {

  use FieldUiTestTrait;

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
    $settings['cache'] = [
      'default' => 'cache.backend.redis',
      'bins' => [
        'config' => 'cache.backend.chainedfast',
        'bootstrap' => 'cache.backend.chainedfast',
        'discovery' => 'cache.backend.chainedfast',
      ],
    ];
    new Settings($settings);

    // Write settings.php for HTTP requests.
    $settings = [];
    $settings['settings']['cache']['default'] = (object) array(
      'value' => 'cache.backend.redis',
      'required' => TRUE,
    );
    $settings['settings']['cache']['bins'] = (object) array(
      'value' => [
        'config' => 'cache.backend.chainedfast',
        'bootstrap' => 'cache.backend.chainedfast',
        'discovery' => 'cache.backend.chainedfast',
      ],
      'required' => TRUE,
    );

    $this->writeSettings($settings);

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

    // Reset the cache factory.
    $this->container->set('cache.factory', NULL);
    $this->rebuildContainer();

    // Make sure that the cache and lock tables aren't used.
    db_drop_table('cache_default');
    db_drop_table('cache_render');
    db_drop_table('cache_config');
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
    $this->assertFalse(db_table_exists('cachetags'));
    $this->assertFalse(db_table_exists('semaphore'));
    $this->assertFalse(db_table_exists('flood'));
  }

}
