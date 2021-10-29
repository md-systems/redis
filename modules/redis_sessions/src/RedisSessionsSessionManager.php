<?php

/**
 * @file
 * Contains \Drupal\redis_sessions\RedisSessionsSessionManager.
 */

namespace Drupal\redis_sessions;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\SessionManager;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\redis\ClientFactory;

/**
 * Manages user sessions.
 *
 * This class implements the custom session management code inherited from
 * Drupal 7 on top of the corresponding Symfony component. Regrettably the name
 * NativeSessionStorage is not quite accurate. In fact the responsibility for
 * storing and retrieving session data has been extracted from it in Symfony 2.1
 * but the class name was not changed.
 *
 * @todo
 *   In fact the NativeSessionStorage class already implements all of the
 *   functionality required by a typical Symfony application. Normally it is not
 *   necessary to subclass it at all. In order to reach the point where Drupal
 *   can use the Symfony session management unmodified, the code implemented
 *   here needs to be extracted either into a dedicated session handler proxy
 *   (e.g. sid-hashing) or relocated to the authentication subsystem.
 *   Was: class SessionManager extends NativeSessionStorage implements
 *   SessionManagerInterface.
 */
class RedisSessionsSessionManager extends SessionManager {

  /**
   * Constructs a new session manager instance.
   *
   * @param \Drupal\Core\Session\SessionManager $session_manager
   *   The innerService object that this service decorates.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Session\MetadataBag $metadata_bag
   *   The session metadata bag.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration interface.
   * @param \Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy|Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler|\SessionHandlerInterface|null $handler
   *   The object to register as a PHP session handler.
   *
   * @see \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage::setSaveHandler()
   */
  public function __construct(SessionManager $session_manager, RequestStack $request_stack, Connection $connection, MetadataBag $metadata_bag, SessionConfigurationInterface $session_configuration, $handler = NULL) {
    $this->innerService = $session_manager;
    parent::__construct($request_stack, $connection, $metadata_bag, $session_configuration, $handler);

    $save_path = $this->getSavePath();
    if (ClientFactory::hasClient()) {
      if (!empty($save_path)) {
        ini_set('session.save_path', $save_path);
        ini_set('session.save_handler', 'redis');
        $this->redis = ClientFactory::getClient();
      }
      else {
        throw new \Exception("Redis Sessions has not been configured. See 'CONFIGURATION' in README.md in the redis_sessions module for instructions.");
      }
    }
    else {
      throw new \Exception("Redis client is not found. Is Redis module enabled and configured?");
    }
  }

  /**
   * Return the session.save_path string for PHP native session handling.
   *
   * Get save_path from site settings, since we can't inject it into the
   * service directly.
   *
   * @return string
   *   A string of the full URL to the redis service.
   */
  private function getSavePath() {
    // Use the save_path value from settings.php first.
    $settings = \Drupal\Core\Site\Settings::get('redis_sessions');
    if ($settings['save_path']) {
      $save_path = $settings['save_path'];
    }
    else {
      // If no save_path from settings.php, use Redis module's settings.
      $settings = \Drupal\Core\Site\Settings::get('redis.connection');
      $save_path = "tcp://${settings['host']}:6379";
    }

    return $save_path;
  }

  /**
   * Return a key prefix to use in redis keys.
   *
   * @return string
   *   A string of the redis key prefix, with a trailing colon.
   */
  private function getNativeSessionKey($suffix = '') {
    // TODO: Get the string from a config option, or use the default string.
    return 'PHPREDIS_SESSION:' . $suffix;
  }

  /**
   * Return the redis key for the current session ID.
   *
   * @return string
   *   A string of the redis key for the current session ID.
   */
  private function getKey() {
    return $this->getNativeSessionKey($this->getId());
  }

  /**
   * Return a Drupal-specific key prefix to use in redis keys.
   *
   * @return string
   *   A string of the redis key prefix, with a trailing colon.
   */
  private function getUidSessionKeyPrefix($suffix = '') {
    // TODO: Get Redis module prefix value to add to the $sid Redis key prefix.
    // TODO: Get the string from a config option, or use the default string.
    return 'DRUPAL_REDIS_SESSION:' . $suffix;
  }

  /**
   * Return the redis key for the current session ID.
   *
   * @return string
   *   A string of the redis key for the current session ID.
   */
  private function getUidSessionKey() {
    $uid = $this->getSessionBagUid();
    return $this->getUidSessionKeyPrefix(Crypt::hashBase64($uid));
  }

  /**
   * Get the User ID from the session metadata bags.
   *
   * Fetch the User ID from the metadata bags rather than a tradtional user
   * lookup in case the UID is in the process of changing (logging in or out).
   *
   * @return int
   *   User id as passed to the constructor in a metadata bag.
   */
  private function getSessionBagUid() {
    foreach ($this->bags as $bag) {
      if ($bag->getName() == 'attributes') {
        $bag = $bag->getBag();
        $attributes = $bag->all();
        if (!empty($attributes['uid'])) {
          return $attributes['uid'];
        }
      }
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isSessionObsolete() {
    $bag_uid = $this->getSessionBagUid();
    $current_uid = \Drupal::currentUser()->id();

    return ($bag_uid == 0 && $current_uid == 0);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $uid = $this->getSessionBagUid();

    // Write the session data.
    parent::save();

    // Write a key:value pair to be able to find the UID by the SID later.
    // NOTE: Checking for $uid here ensures that only sessions for logged-in
    // users will have lookup keys. Anonymous sessions (if they exist at all)
    // are transient and will be cleaned up via garbage collection.
    // TODO: Add EX Seconds to the set() method for session life length.
    // TODO: After adding EX and PX seconds, add 'NX'.
    // See: https://redis.io/commands/set.
    if ($uid) {
      if (\Drupal::currentUser()->id()) {
        $this->redis->set($this->getUidSessionKey(), $this->getKey());
      }
      else {
        $this->destroyObsolete($this->redis->get($this->getUidSessionKey()));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($uid) {
    // Nothing to do if we are not allowed to change the session.
    if ($this->isCli() || $this->innerService->isCli()) {
      return;
    }

    // Get the session key by $uid.
    $sid = $this->redis->get($this->getUidSessionKey());

    // Delete both key/value pairs associated with the session ID.
    $this->redis->del($sid);
    $this->redis->del($this->getKey());
  }

  /**
   * {@inheritdoc}
   */
  public function destroy() {
    $uid = $this->getSessionBagUid();
    $this->redis->set("SESS_DESTROY:$uid:" . \Drupal::currentUser()->id());

    if ($uid) {
      if (\Drupal::currentUser()->id() == 0) {
        $sid = $this->redis->get($this->getUidSessionKey());

        $this->redis->del($sid);
        $this->redis->del($this->getUidSessionKey());
        $this->redis->del($this->getKey());
      }
    }

    $this->innerService->destroy();
  }

  /**
   * Removes obsolete sessions.
   *
   * @param string $old_session_id
   *   The old session ID.
   */
  public function destroyObsolete($old_session_id) {
    $this->redis->del($old_session_id);
    $this->redis->del($this->getUidSessionKey());
  }

  /**
   * Migrates the current session to a new session id.
   *
   * @param string $old_session_id
   *   The old session ID. The new session ID is $this->getId().
   *
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Session%21SessionManager.php/function/SessionManager%3A%3AmigrateStoredSession/8.2.x
   */
  protected function migrateStoredSession($old_session_id) {
    // The original session has been copied to a new session with a new key;
    // remove the original session ID key.
    // Test: redis-cli KEYS "*SESS*" | xargs redis-cli DEL && redis-cli.
    $this->redis->del($this->getNativeSessionKey($old_session_id));
  }

}
