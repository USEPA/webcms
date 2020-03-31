<?php

// Override the endpoint used by s3fs in order to talk to Minio.
$config['s3fs.settings']['use_customhost'] = TRUE;
$config['s3fs.settings']['hostname'] = 'minio:9000';

// Override output to point to localhost:8888/drupal in order to see Minio-saved files
$config['s3fs.settings']['use_cname'] = TRUE;
$config['s3fs.settings']['domain'] = 'localhost:8888/drupal';

// Map twig cache onto shared filesystem to allow drush to clear and write twig cache for local development.
$settings['php_storage']['twig']['directory'] = '/var/www/html/web/sites/default/files/tmp/cache/twig';

// Avoid having a redis cache backend causing errors before we've had a chance to enable the module.
if ($env_state == 'build') {
  unset($settings['cache']['default']);
}

// Connect to Drupal 7 database in Vagrant.
$databases['drupal_7']['default'] = [
  'database' => 'web',
  'username' => 'web',
  'password' => 'web',
  'host' => 'host.docker.internal',
  'port' => '13306',
  'driver' => 'mysql',
];
