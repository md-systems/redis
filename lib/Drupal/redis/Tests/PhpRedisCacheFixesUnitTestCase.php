<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PhpRedisCacheFixesUnitTestCase.
 */

namespace Drupal\redis\Tests;

/**
 * PhpRedis cache flush testing.
 */
class PhpRedisCacheFixesUnitTestCase extends AbstractRedisCacheFixesUnitTestCase
{
    public static function getInfo()
    {
        return array(
            'name'        => 'PhpRedis cache fixes',
            'description' => 'Tests Redis module cache fixes feature.',
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
