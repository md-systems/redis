<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PredisLockingUnitTestCase.
 */

namespace Drupal\redis\Tests;

/**
 * Predis lock testing.
 */
class PredisLockingUnitTestCase extends AbstractRedisLockingUnitTestCase
{
    public static function getInfo()
    {
        return array(
            'name'        => 'Predis Redis locking',
            'description' => 'Ensure that Redis locking feature is working OK.',
            'group'       => 'Redis',
        );
    }

    protected function getLockBackendClass()
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

        $conf['redis_client_interface'] = 'Predis';

        return 'Redis_Lock_Backend_Predis';
    }
}
