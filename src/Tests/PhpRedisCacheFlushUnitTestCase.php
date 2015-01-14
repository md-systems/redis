<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\PhpRedisCacheFlushUnitTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\redis\ClientFactory;

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
        global $config;

        if (extension_loaded('redis') && class_exists('Redis')) {
            $config['redis.settings']['connection']['interface'] = 'PhpRedis';

            return ClientFactory::REDIS_IMPL_CACHE . 'PhpRedis';
        }
    }
}
