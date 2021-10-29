<?php

namespace Drupal\redis\Session;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\Session\SessionManager as CoreSessionManager;
use Drupal\redis\ClientFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

/**
 * Manages user sessions in redis.
 */
class SessionManager extends CoreSessionManager {

  /**
   * @var \Drupal\redis\ClientInterface
   */
  protected $clientFactory;

  /**
   * The Redis client.
   *
   * @var mixed
   */
  protected $redis;

  /**
   * {@inheritdoc}
   *
   * @param ClientFactory $clientFactory
   *
   * @throws \Exception
   */
  public function __construct(RequestStack $request_stack, ClientFactory $clientFactory, MetadataBag $metadata_bag, SessionConfigurationInterface $session_configuration, $handler = NULL) {
    parent::__construct($request_stack, \Drupal::database(), $metadata_bag, $session_configuration, $handler);

    $this->clientFactory = $clientFactory;
    $this->redis = $this->clientFactory->getClient();
    if ($this->clientFactory->getClientName() == 'PhpRedis') {
      ini_set('session.save_path', $this->getSavePath());
      ini_set('session.save_handler', 'redis');
    }
    elseif ($this->clientFactory->getClientName() == 'Predis') {
      $handler = new \Predis\Session\Handler($this->redis, array('gc_maxlifetime' => 5));
      $handler->register();
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
      $settings += [
        'port' => '6379',
      ];
      $save_path = "tcp://${settings['host']}:${settings['port']}";
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
   * Fetch the User ID from the metadata bags rather than a traditional user
   * lookup in case the UID is in the process of changing (logging in or out).
   *
   * @return int
   *   User id as passed to the constructor in a metadata bag.
   */
  private function getSessionBagUid() {
    foreach ($this->bags as $bag) {
      // In Drupal 8.5 and above, the bag may be a proxy, in which case we need to get the actual bag.
      if (method_exists($bag, 'getBag')) {
        $bag = $bag->getBag();
      }
      if ($bag instanceof AttributeBagInterface && $bag->has('uid')) {
        return (int) $bag->get('uid');
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
    if ($this->isCli()) {
      // We don't have anything to do if we are not allowed to save the session.
      return;
    }

    if ($this->isSessionObsolete()) {
      // There is no session data to store, destroy the session if it was
      // previously started.
      if ($this->getSaveHandler()->isActive()) {
        $this->destroy();
      }
    }
    else {
      // There is session data to store. Start the session if it is not already
      // started.
      if (!$this->getSaveHandler()->isActive()) {
        $this->startNow();
      }
      // Write the session data.
      parent::save();
    }

    $this->startedLazy = FALSE;

    // Write a key:value pair to be able to find the UID by the SID later.
    // NOTE: Checking for $uid here ensures that only sessions for logged-in
    // users will have lookup keys. Anonymous sessions (if they exist at all)
    // are transient and will be cleaned up via garbage collection.
    // TODO: Add EX Seconds to the set() method for session life length.
    // TODO: After adding EX and PX seconds, add 'NX'.
    // See: https://redis.io/commands/set.
    if ($this->getSessionBagUid()) {
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
    if ($this->isCli()) {
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
    $this->redis->del("SESS_DESTROY:$uid:" . \Drupal::currentUser()->id());

    if ($uid) {
      if (\Drupal::currentUser()->id() == 0) {
        $sid = $this->redis->get($this->getUidSessionKey());

        $this->redis->del($sid);
        $this->redis->del($this->getUidSessionKey());
        $this->redis->del($this->getKey());
      }
    }

    parent::destroy();
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
