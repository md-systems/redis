Redis cache backends
====================

This package provides two different Redis cache backends. If you want to use
Redis as cache backend, you have to choose one of the two, but you cannot use
both at the same time. Well, it will be technically possible, but it would be
quite a dumb thing to do.

Predis
------

This implementation uses the Predis PHP library. It is compatible PHP 5.3
only.

PhpRedis
--------

This implementation uses the PhpRedis PHP extention. In order to use it, you
will need to compile the extension yourself.

Notes
-----

Both backends provide the exact same functionnalities. The major difference is
because PhpRedis uses a PHP extension, and not PHP code, it more performant.

Difference is not that visible, it's really a few millisec on my testing box.
