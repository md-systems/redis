CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------

The Redis Sessions module creates an alternative to database storage for user
sessions. It uses a PHP Redis sessions manager and custom settings to
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

Either include the default example.services.yml from the module, which will
replace all supported backend services (check the file for the current list)
or copy the service definitions into a site specific services.yml.

    $settings['container_yamls'][] = 'modules/redis/modules/redis_sessions/example.services.yml';
