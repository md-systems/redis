<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\RedisAdminVariableTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Checks that Redis module variables are correctly type hinted when saved.
 *
 * @group redis
 */
class RedisAdminVariableTestCase extends WebTestBase {

  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public static $modules = array('redis');

  public function testSave() {

    $this->adminUser = $this->drupalCreateUser(array('administer site configuration'));
    $this->drupalLogin($this->adminUser);

    // Tests port is an int.
    $this->drupalGet('admin/config/development/performance/redis');
    $edit = array(
      'base'      => '',
      'port'      => 1234,
      'host'      => 'localhost',
      'interface' => 'auto',
    );
    $this->drupalPostForm('admin/config/development/performance/redis', $edit, t('Save configuration'));

    $config = \Drupal::config('redis.settings');

    $this->assertFalse($config->get('connection.base'), "Empty int value has been removed");
    $this->assertEqual($config->get('connection.interface'), 'auto', "Empty string value has been removed");
    $this->assertIdentical($config->get('connection.port'), 1234, "Saved int is an int");
    $this->assertIdentical($config->get('connection.host'), 'localhost', "Saved string is a string");

    $this->drupalGet('admin/config/development/performance/redis');
    $edit = array(
      'base'      => 0,
      'port'      => 1234,
      'host'      => 'localhost',
      'interface' => 'auto',
    );
    $this->drupalPostForm('admin/config/development/performance/redis', $edit, t('Save configuration'));

    // Force variable cache to refresh.
    $config = \Drupal::config('redis.settings');

    $this->assertIdentical($config->get('connection.base'), 0, "Saved 0 valueed int is an int");
  }
}
