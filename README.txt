Redis cache backends
====================

This package provides two different Redis cache backends. If you want to use
Redis as cache backend, you have to choose one of the two, but you cannot use
both at the same time. Well, it will be technically possible, but it would be
quite a dumb thing to do.

Predis
------

This implementation uses the Predis PHP library. It is compatible PHP 5.3
only, and need Redis >= 2.1.0 for using the WATCH command.

PhpRedis
--------

This implementation uses the PhpRedis PHP extention. In order to use it, you
will need to compile the extension yourself.

Redis version
-------------

Be careful with lock.inc replacement, actual implementation uses the Redis
WATCH command, it is actually there only since version 2.1.0. If you use
the it, it will just pass silently and work gracefully, but lock exclusive
mutex is exposed to race conditions.

Use Redis 2.1.0 or later if you can! I warned you.

Notes
-----

Both backends provide the exact same functionnalities. The major difference is
because PhpRedis uses a PHP extension, and not PHP code, it more performant.

Difference is not that visible, it's really a few millisec on my testing box.

Note that most of the settings are shared. See next sections.

Install
=======

Choose the Redis client library to use
--------------------------------------

Add into your settings.php file:

  $conf['redis_client_interface']      = 'PhpRedis';

You can replace 'PhpRedis' with 'Predis', depending on the library you chose. 

Tell Drupal to use the cache backend
------------------------------------

Usual cache backend configuration, as follows, to add into your settings.php
file like any other backend:

  $conf['cache_backends'][]            = 'sites/all/modules/redis/redis.autoload.inc';
  $conf['cache_class_cache']           = 'Redis_Cache';
  $conf['cache_class_cache_menu']      = 'Redis_Cache';
  $conf['cache_class_cache_bootstrap'] = 'Redis_Cache';
  // ... Any other bins.

Tell Drupal to use the lock backend
-----------------------------------

Usual lock backend override, update you settings.php file as this:

  $conf['lock_inc'] = 'sites/all/modules/custom/redis/redis.lock.inc';

Common settings
===============

Connect to a remote host
------------------------

If your Redis instance is remote, you can use this syntax:

  $conf['redis_client_host'] = '1.2.3.4';
  $conf['redis_client_port'] = 1234;

Port is optional, default used is 6379 (default Redis port).

Using a specific database
-------------------------

Per default, Redis ships the database "0". All default connections will be one
this one if nothing is specified.

Depending on you OS or OS distribution, you might have numerous database. To
use one in particular, just add to your settings.php file:

  $conf['redis_client_base'] = 12;

Lock backends
-------------

Both implementations provides a Redis lock backend. Lock backend, being used
with MySQL and Redis on the same backend is faster than default one, whatever
implementation you are using.

Both backends, thanks to the Redis WATCH, MULTI and EXEC commands provides a
real race condition free mutexes if you use Redis >= 2.1.0.
