Predis cache backend
====================

This client, for now, is only able to use the Predis PHP library.

The Predis library requires PHP 5.3 minimum. If your hosted environment does
not ships with at least PHP 5.3, please do not use this cache backend.

This code is ALPHA code. This means: DO NOT USE IT IN PRODUCTION. Not until
I don't ship any BETA release as a full Drupal.org module.

Please consider using an OPCode cache such as APC. Predis is a good and fully
featured API, the cost is that the code is a lot more than a single file in
opposition to some other backends such as the APC one.

Get Predis
----------

You can download this library at:

  https://github.com/nrk/predis

This file explains how to install the Predis library and the Drupal cache
backend. If you are an advanced Drupal integrator, please consider the fact
that you can easily change all the pathes. Pathes used in this file are
likely to be default for non advanced users.

Download and install library
----------------------------

Once done, you either have to clone it into:
  sites/all/libraries/predis

So that you have the following directory tree:

  sites/all/libraries/lib/Predis # Where the PHP code stands

Or, any other place in order to share it:
For example, into your libraries folder, in order to get:

  some/dir/predis/lib

If you choose this solution, you have to alter a bit your $conf array into
the settings.php file as this:

  define('PREDIS_BASE_PATH', DRUPAL_ROOT . '/some/dir/predis/lib/');

Tell Drupal to use the cache backend
------------------------------------

Usual cache backend configuration, as follows, to add into your settings.php
file like any other backend:

  $conf['cache_backends'][]            = 'sites/all/modules/redis/predis.cache.inc';
  $conf['cache_class_cache']           = 'Redis_Cache_Predis';
  $conf['cache_class_cache_menu']      = 'Redis_Cache_Predis';
  $conf['cache_class_cache_bootstrap'] = 'Redis_Cache_Predis';
  // ... Any other bins.

Tell Drupal to use the lock backend
-----------------------------------

Usual lock backend override, update you settings.php file as this:

  $conf['lock_inc'] = 'sites/all/modules/custom/redis/predis.lock.inc';

Connect to a remote host and database
-------------------------------------

See README.txt file.

Advanced configuration (PHP expert)
-----------------------------------

Best solution is, whatever is the place where you put the Predis library, that
you set up a fully working autoloader able to use it.
