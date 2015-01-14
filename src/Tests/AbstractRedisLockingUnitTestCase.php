<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\AbstractRedisLockingUnitTestCase.
 */

namespace Drupal\redis\Tests;

use Drupal\simpletest\KernelTestBase;

/**
 * Base implementation for locking functionnal testing.
 */
abstract class AbstractRedisLockingUnitTestCase extends KernelTestBase
{
    /**
     * Ensure lock flush at tear down.
     *
     * @var array
     */
    protected $backends = array();

    /**
     * Set up the Redis configuration.
     *
     * Set up the needed variables using variable_set() if necessary.
     *
     * @return bool
     *   TRUE in case of success FALSE otherwise.
     */
    abstract protected function getLockBackendClass();

    public function setUp()
    {
        parent::setUp();

        drupal_install_schema('system');
    }

    public function tearDown()
    {
        if (!empty($this->backends)) {
            foreach ($this->backends as $backend) {
                $backend->lockReleaseAll();
            }

            $this->backends = array();
        }

        drupal_uninstall_schema('system');

        parent::tearDown();
    }

    /**
     * Create a new lock backend with a generated lock id
     *
     * @return Redis_Lock_Backend_Interface
     */
    public function createLockBackend()
    {
        $class = $this->getLockBackendClass();

        if (!class_exists($class)) {
            throw new \Exception("Lock backend class does not exist");
        }

        return $this->backends[] = new $class();
    }

    public function testLock()
    {
        $b1 = $this->createLockBackend();
        $b2 = $this->createLockBackend();

        $s = $b1->lockAcquire('test1', 20000);
        $this->assertTrue($s, "Lock test1 acquired");

        $s = $b1->lockAcquire('test1', 20000);
        $this->assertTrue($s, "Lock test1 acquired a second time by the same thread");

        $s = $b2->lockAcquire('test1', 20000);
        $this->assertFalse($s, "Lock test1 could not be acquired by another thread");

        $b2->lockRelease('test1');
        $s = $b2->lockAcquire('test1');
        $this->assertFalse($s, "Lock test1 could not be released by another thread");

        $b1->lockRelease('test1');
        $s = $b2->lockAcquire('test1');
        $this->assertTrue($s, "Lock test1 has been released by the first thread");
    }

    public function testReleaseAll()
    {
        $b1 = $this->createLockBackend();
        $b2 = $this->createLockBackend();

        $b1->lockAcquire('test1', 200);
        $b1->lockAcquire('test2', 2000);
        $b1->lockAcquire('test3', 20000);

        $s = $b2->lockAcquire('test2');
        $this->assertFalse($s, "Lock test2 could not be released by another thread");
        $s = $b2->lockAcquire('test3');
        $this->assertFalse($s, "Lock test4 could not be released by another thread");

        $b1->lockReleaseAll();

        $s = $b2->lockAcquire('test1');
        $this->assertTrue($s, "Lock test1 has been released");
        $s = $b2->lockAcquire('test2');
        $this->assertTrue($s, "Lock test2 has been released");
        $s = $b2->lockAcquire('test3');
        $this->assertTrue($s, "Lock test3 has been released");

        $b2->lockReleaseAll();
    }

    public function testConcurentLock()
    {
        /*
         * Code for web test case
         *
        $this->drupalGet('redis/acquire/test1/1000');
        $this->assertText("REDIS_ACQUIRED", "Lock test1 acquired");

        $this->drupalGet('redis/acquire/test1/1');
        $this->assertText("REDIS_FAILED", "Lock test1 could not be acquired by a second thread");

        $this->drupalGet('redis/acquire/test2/1000');
        $this->assertText("REDIS_ACQUIRED", "Lock test2 acquired");

        $this->drupalGet('redis/acquire/test2/1');
        $this->assertText("REDIS_FAILED", "Lock test2 could not be acquired by a second thread");
         */
    }
}
