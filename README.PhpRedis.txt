PhpRedis cache backend
======================

This client, for now, is only able to use the PhpRedis extension.

This code is ALPHA code. This means: DO NOT USE IT IN PRODUCTION. Not until
I don't ship any BETA release as a full Drupal.org module.

Get PhpRedis
------------

You can download this library at:
  https://github.com/nicolasff/phpredis

This is PHP extension, too recent for being packaged in most distribution, you
will probably need to compile it yourself.

Tell Drupal to use it
---------------------

Usual cache backend configuration, as follows, to add into your settings.php
file like any other backend:
  $conf['cache_backends'][]            = 'sites/all/modules/redis/phpredis.inc';
  $conf['cache_class_cache']           = 'RedisPhpRedisCache';
  $conf['cache_class_cache_menu']      = 'RedisPhpRedisCache';
  $conf['cache_class_cache_bootstrap'] = 'RedisPhpRedisCache';
  // ... Any other bins.

Default behavior is to connect via tcp://localhost:6379 but you might want to
connect differently.

Connect via UNIX socket
-----------------------

Just add this line to your settings.php file:
  $conf['redis_cache_socket'] = '/tmp/redis.sock';

Don't forget to change the path depending on you operating system.

Connect to a remote host
------------------------

If your Redis instance is remote, you can use this syntax:
  $conf['redis_cache_host'] = '1.2.3.4';
  $conf['redis_cache_port'] = 1234;

Port is optional, default used is 6379 (default Redis port).
