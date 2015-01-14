<?php

/**
 * @file
 * Contains Drupal\redis\Tests\AbstractRedisCacheUnitTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\simpletest\KernelTestBase;

/**
 * Base implementation for locking functionnal testing.
 */
abstract class AbstractRedisCacheUnitTestCase extends KernelTestBase
{
    /**
     * @var DrupalCacheInterface
     */
    private $backend;

    /**
     * Set up the Redis configuration.
     *
     * Set up the needed variables using variable_set() if necessary.
     *
     * @return bool
     *   TRUE in case of success FALSE otherwise.
     */
    abstract protected function getCacheBackendClass();

    /**
     * Get cache backend
     *
     * @return \Drupal\Core\Cache\CacheBackendInterface
     */
    final protected function getBackend()
    {
        if (null === $this->backend) {
            $class = $this->getCacheBackendClass();

            if (null === $class) {
                throw new \Exception("Test skipped due to missing driver");
            }

            $this->backend = new $class('cache');
        }

        return $this->backend;
    }

    public function setUp()
    {
        parent::setUp();

        drupal_install_schema('system');
    }

    public function tearDown()
    {
        $this->backend = null;

        drupal_uninstall_schema('system');

        parent::tearDown();
    }
}
