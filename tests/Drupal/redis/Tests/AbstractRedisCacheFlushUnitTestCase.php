<?php

/**
 * @file
 * Contains \Drupal\redis\Tests\AbstractRedisCacheFlushUnitTestCase.
 */

namespace Drupal\redis\Tests;

/**
 * Base implementation for locking functionnal testing.
 */
abstract class AbstractRedisCacheFlushUnitTestCase extends AbstractRedisCacheUnitTestCase
{
    /**
     * Test that the flush all flush mode flushes everything.
     */
    public function testFlushAll()
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = 2;
        $backend = $this->getBackend();

        $this->assertEqual(Redis_Cache_Base::FLUSH_ALL, $backend->getClearMode());

        $backend->set('test1', 42, CACHE_PERMANENT);
        $backend->set('test2', 'foo', CACHE_TEMPORARY);
        $backend->set('test3', 'bar', 10);

        $backend->clear();

        $this->assertFalse($backend->get('test1'));
        $this->assertFalse($backend->get('test2'));
        $this->assertFalse($backend->get('test3'));
    }

    /**
     * Test that the flush nothing flush mode flushes nothing.
     */
    public function testFlushIsNothing()
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = 0;
        $backend = $this->getBackend();

        $this->assertEqual(Redis_Cache_Base::FLUSH_NOTHING, $backend->getClearMode());

        $backend->set('test4', 42, CACHE_PERMANENT);
        $backend->set('test5', 'foo', CACHE_TEMPORARY);
        $backend->set('test6', 'bar', time() + 10);

        $backend->clear();

        $cache = $backend->get('test4');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 42);
        $cache = $backend->get('test5');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 'foo');
        $cache = $backend->get('test6');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 'bar');
    }

    /**
     * Tests that with a default cache lifetime temporary non expired
     * items are kept even when in temporary flush mode.
     */
    public function testFlushIsTemporaryWithLifetime()
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = 1;
        $conf['cache_lifetime'] = 1000;
        $backend = $this->getBackend();

        $this->assertEqual(Redis_Cache_Base::FLUSH_TEMPORARY, $backend->getClearMode());

        $backend->set('test7', 42, CACHE_PERMANENT);
        $backend->set('test8', 'foo', CACHE_TEMPORARY);
        $backend->set('test9', 'bar', time() + 1000);

        $backend->clear();

        $cache = $backend->get('test7');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 42);
        $cache = $backend->get('test8');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 'foo');
        $cache = $backend->get('test9');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 'bar');
    }

    /**
     * Tests that with no default cache lifetime all temporary items are
     * droppped when in temporary flush mode.
     */
    public function testFlushIsTemporaryWithoutLifetime()
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = 1;
        $conf['cache_lifetime'] = 0;
        $backend = $this->getBackend();

        $this->assertEqual(Redis_Cache_Base::FLUSH_TEMPORARY, $backend->getClearMode());

        $backend->set('test10', 42, CACHE_PERMANENT);
        $backend->set('test11', 'foo', CACHE_TEMPORARY);
        $backend->set('test12', 'bar', time() + 10);

        $backend->clear();

        $cache = $backend->get('test10');
        $this->assertNotEqual(false, $cache);
        $this->assertEqual($cache->data, 42);
        $this->assertFalse($backend->get('test11'));
        $this->assertFalse($backend->get('test12'));
    }

    /**
     * Flushing more than 20 elements should switch to a pipeline that
     * sends multiple DEL batches.
     */
    public function testFlushALot()
    {
        global $conf;

        $conf['redis_flush_mode_cache'] = 2;
        $backend = $this->getBackend();

        $cids = array();

        for ($i = 0; $i < 100; ++$i) {
            $cids[] = $cid = 'test' . $i;
            $backend->set($cid, 42, CACHE_PERMANENT);
        }

        $backend->clear('*', true);

        foreach ($cids as $cid) {
            $this->assertFalse($backend->get($cid));
        }
    }
}
