<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PhpRedisCacheFlushUnitTestCase.
 */

namespace Drupal\redis\Tests;

/**
 * PhpRedis cache flush testing.
 */
class PhpRedisCacheFlushUnitTestCase extends AbstractRedisCacheFlushUnitTestCase
{
    public static function getInfo()
    {
        return array(
            'name'        => 'PhpRedis cache flush',
            'description' => 'Tests Redis module cache flush modes feature.',
            'group'       => 'Redis',
        );
    }

    protected function getCacheBackendClass()
    {
        global $conf;

        if (extension_loaded('redis') && class_exists('Redis')) {
            $conf['redis_client_interface'] = 'PhpRedis';

            return 'Redis_Cache_PhpRedis';
        }
    }
}
