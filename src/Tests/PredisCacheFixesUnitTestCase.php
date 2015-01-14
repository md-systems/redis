<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PredisCacheFixesUnitTestCase.
 */

namespace Drupal\redis\Tests;
use Drupal\redis\ClientFactory;

/**
 * Predis cache flush testing.
 */
class PredisCacheFixesUnitTestCase extends AbstractRedisCacheFixesUnitTestCase
{

    public static function getInfo()
    {
        return array(
            'name'         => 'Predis cache fixes',
            'description'  => 'Tests Redis module cache fixes feature.',
            'group'        => 'Redis',
        );
    }

    protected function getCacheBackendClass()
    {
        global $conf;

        // FIXME: This is definitely ugly but we have no choice: during unit
        // testing Drupal will attempt to reach the database if do not prepend
        // our autoloader manually. We can't do class_exists() calls either,
        // they will lead to Drupal crash in all case.
        if (!defined('PREDIS_BASE_PATH')) {
            define('PREDIS_BASE_PATH', DRUPAL_ROOT . '/sites/all/libraries/predis/lib/');
        }

        spl_autoload_register(function($className) {
            $parts = explode('\\', $className);
            if ('Predis' === $parts[0]) {
                $filename = PREDIS_BASE_PATH . implode('/', $parts) . '.php';
                return (bool)include_once $filename;
            }
            return false;
        }, null, true);

      global $config;
      $config['redis.settings']['connection']['interface'] = 'Predis';
      throw new \Exception('todo implement or remove Predis');
      return ClientFactory::REDIS_IMPL_CACHE . 'Predis';
    }
}
