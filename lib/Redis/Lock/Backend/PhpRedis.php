<?php

/**
 * Predis lock backend implementation.
 */
class Redis_Lock_Backend_PhpRedis extends Redis_Lock_Backend_Default {

  public function lockAcquire($name, $timeout = 30.0) {
    $client  = Redis_Client::getClient();
    $key     = 'lock:' . $name;
    $keyOwn  = $key . ':owner';
    $id      = $this->getLockId();

    // Insure that the timeout is at least 1 second, we cannot do otherwise with
    // Redis, this is a minor change to the function signature, but in real life
    // nobody will notice with so short duration.
    $timeout = ceil(max($timeout, 1));

    // If we already have the lock, check for his owner and attempt a new EXPIRE
    // command on it.
    if (isset($this->_locks[$name])) {

      // Create a new transaction, for atomicity.
      $client->watch($keyOwn);

      // Global tells us we are the owner, but in real life it could have expired
      // and another process could have taken it, check that.
      if ($client->get($keyOwn) != $id) {
        // Explicit UNWATCH we are not going to run the MULTI/EXEC block.
        $client->unwatch($keyOwn);
        unset($this->_locks[$name]);
        return FALSE;
      }

      $result = $client
        ->multi()
        ->expire($key, $timeout)
        ->setex($keyOwn, $timeout, $id)
        ->exec();

      // Did it broke?
      if (FALSE === $result || 1 != $result[1]) {
        unset($this->_locks[$name]);
        return FALSE;
      }

      return TRUE;
    }
    else {
      $client->watch($key);

      $result = $client
        ->multi()
        ->incr($key)
        // The INCR command should reset the EXPIRE state, so we are now the
        // official owner. Set the owner flag and real EXPIRE delay.
        ->expire($key, $timeout)
        ->setex($key . ':owner', $timeout, $id)
        ->exec();

      // If another client modified the $key value, transaction will be discarded
      // $result will be set to FALSE. This means atomicity have been broken and
      // the other client took the lock instead of us. The another condition is
      // the INCR result test. If we succeeded in incrementing the counter but
      // that counter was more than 0, then someone else already have the lock
      // case in which we cannot proceed.
      if (FALSE === $result || 1 != $result[0]) {
        return FALSE;
      }

      // Register the lock.
      return ($this->_locks[$name] = TRUE);
    }
    return FALSE;
  }

  public function lockMayBeAvailable($name) {
    $client  = Redis_Client::getClient();
    $key     = 'lock:' . $name;
    $id      = $this->getLockId();

    list($value, $owner) = $client->mget(array($key, $key . ':owner'));

    return (FALSE !== $value || 0 == $value) && $id == $owner;
  }

  public function lockRelease($name) {
    $client  = Redis_Client::getClient();
    $key     = 'lock:' . $name;
    $keyOwn  = $key . ':owner';
    $id      = $this->getLockId();

    unset($this->_locks[$name]);

    // Ensure the lock deletion is an atomic transaction. If another thread
    // manages to removes all lock, we can not alter it anymore else we will
    // release the lock for the other thread and cause race conditions.
    $client->watch($keyOwn);

    if ($client->get($keyOwn) == $id) {
      $client->multi();
      $client->del(array($key, $keyOwn));
      $client->exec();
    }
    else {
      $client->unwatch();
    }
  }

  public function lockReleaseAll($lock_id = NULL) {
    if (!isset($lock_id) && empty($locks)) {
      return;
    }

    $client  = Redis_Client::getClient();
    $id      = isset($lock_id) ? $lock_id : $this->getLockId();

    // We can afford to deal with a slow algorithm here, this should not happen
    // on normal run because we should have removed manually all our locks.
    foreach ($this->_locks as $name => $foo) {
      $key    = 'lock:' . $name;
      $keyOwn = $key . ':owner';
      $owner  = $client->get($keyOwn);

      if (empty($owner) || $owner == $id) {
        $client->del(array($key, $keyOwn));
      }
    }
  }
}
