<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PhpRedisCacheFixesUnitTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\redis\ClientFactory;

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
        global $config;

        if (extension_loaded('redis') && class_exists('Redis')) {
            $config['redis.settings']['connection']['interface'] = 'PhpRedis';
            return ClientFactory::REDIS_IMPL_CACHE . 'PhpRedis';
        }
    }
}
