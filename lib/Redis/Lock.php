<?php

/**
 * Lock backend singleton handling.
 */
class Redis_Lock {
  /**
   * @var Redis_Lock_Backend_Interface
   */
  private static $__instance;

  /**
   * Set the current lock backend.
   * 
   * @param Redis_Lock_Backend_Interface $lockBackend
   */
  public static function setBackend(Redis_Lock_Backend_Interface $lockBackend) {
    if (isset(self::$__instance)) {
      throw new Exception("Lock backend already set, changing it would cause already acquired locks to stall.");
    }
    self::$__instance = $lockBackend;
  }

  /**
   * Get actual lock backend.
   * 
   * @return Redis_Lock_Backend_Interface
   */
  public static function getBackend() {
    if (!isset(self::$__instance)) {
      throw new Exception("No lock backend set.");
    }
    return self::$__instance;
  }
}
