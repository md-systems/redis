<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PhpRedisLockingUnitTestCase.
 */

namespace Drupal\redis\Tests;

/**
 * PhpRedis lock testing.
 */
class PhpRedisLockingUnitTestCase extends AbstractRedisLockingUnitTestCase
{
    public static function getInfo()
    {
        return array(
            'name'        => 'PhpRedis Redis locking',
            'description' => 'Ensure that Redis locking feature is working OK.',
            'group'       => 'Redis',
        );
    }

    protected function getLockBackendClass()
    {
        global $conf;

        if (extension_loaded('redis') && class_exists('Redis')) {
            $conf['redis_client_interface'] = 'PhpRedis';

            return 'Redis_Lock_Backend_PhpRedis';
        }
    }
}
