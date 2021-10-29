CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------
The Redis Sessions module creates an alternative to database storage for user
sessions. It uses native a PHP Redis sessions manager and custom settings to
use Redis for sessions handling and storage.


REQUIREMENTS
------------

This module requires the following modules:

 * Redis (https://drupal.org/project/redis)


INSTALLATION
------------
 
 * Install as you would normally install a contributed Drupal module. See:
   https://www.drupal.org/docs/8/extending-drupal-8/installing-modules
   for further information.



CONFIGURATION
-------------

 * By default, Redis Sessions will attempt to use the redis.connection host.
 * OPTIONAL: You can add the save_path to your settings.php file, especially if
   you want to use a different Redis service than what is used for cache.
    ```
    $settings['redis_sessions'] = [
      'save_path' => 'tcp://redis:6379',
    ];
    ```
