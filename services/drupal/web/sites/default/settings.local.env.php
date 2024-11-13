<?php

// Override SMTP settings
// These specific overrides are no longer necessary if you're using the latest
// .env file but we'll leave them here to avoid breaking things for people who
// haven't added the port and protocol entries to their .env files.
$config['smtp.settings']['smtp_port'] = 1025;
$config['smtp.settings']['smtp_protocol'] = 'standard';

$config['smtp.settings']['smtp_on'] = FALSE;
$config['system.mail']['interface'] = [
  'default' => 'php_mail',
];

// Local development does not work with SMTP as sender/formatter. Update so
// local email works.
$config['mailsystem.settings']['defaults']['sender'] = 'mime_mail';
$config['mailsystem.settings']['defaults']['formatter'] = 'mime_mail';

// Don't initiate TLS sessions during local development; the MySQL certificate is
// self-signed, which OpenSSL rejects
unset($databases['default']['default']['pdo'][PDO::MYSQL_ATTR_SSL_CA]);

// Replace profile credentials with Minio-specific secrets
unset($config['s3fs.settings']['use_instance_profile']);
$settings['s3fs.access_key'] = 'minio_access';
$settings['s3fs.secret_key'] = 'minio_secret';

// Override the endpoint used by s3fs in order to talk to Minio.
$config['s3fs.settings']['use_customhost'] = TRUE;

// This relies on this patch: https://www.drupal.org/node/3203137 -- it causes
// the bucket name (drupal, in this case) to be tacked on to the end of the
// domain. We can't just add /drupal to the domain because the S3FS module
// doesn't allow that to work in the way we need if you've also specified a port,
// as we have.
$config['s3fs.settings']['use_path_style_endpoint'] = TRUE;
$config['s3fs.settings']['hostname'] = 'minio:9000';

// Override output to point to localhost:8888 in order to see Minio-saved files
$config['s3fs.settings']['use_cname'] = TRUE;
$config['s3fs.settings']['domain'] = 'localhost:8888';

// Minio doesn't support ListObjectVersions
$config['s3fs.settings']['disable_version_sync'] = TRUE;

// Unset Flysystem schemes so we can use private filesystem for tome.
unset($settings['flysystem']);

// Use private filesystem for Tome.
$settings['tome_static_directory'] = 'private://snapshot';

// Map twig cache onto shared filesystem to allow drush to clear and write twig cache for local development.
$settings['php_storage']['twig']['directory'] = '/var/www/html/web/sites/default/files/tmp/cache/twig';

$config['system.logging']['error_level'] = 'all';

// Set the base url for node export operations. Setting this to "localhost"
// instead of "localhost:8080" since this will be utilized by wget and it will
// use the internal Docker url which uses port 80.
$settings['epa_node_export.base_url'] = 'http://localhost';

// Local environments can't use AWS elasticache auto discovery
if (defined('Memcached::OPT_CLIENT_MODE')) {
  unset($settings['memcache']['options'][Memcached::OPT_CLIENT_MODE]);
}

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

$settings['container_yamls'][] = $app_root . '/' . $site_path . '/development.services.yml';

ini_set('max_execution_time', 0);

// Provide override configuration for epa_cloudwatch to redirect
// logging to Localstack (see https://github.com/localstack/localstack)
$config['epa_cloudwatch']['endpoint'] = 'http://localstack:4566';
$config['epa_cloudwatch']['credentials'] = [
  'access_key' => 'foo',
  'secret_key' => 'bar',
];
$settings['memcache']['servers'] = ['memcached' . ':' . '11211' => 'default'];
$settings['memcache']['key_prefix'] = 'epa_local';
$settings['cache']['default'] = 'cache.backend.memcache';


$config['epa_cloudwatch']['log_stream'] = 'app-drupal';

$settings['config_sync_directory'] = '../config/sync';

$databases['default']['default'] = array(
  'database' => "web",
  'username' => "web",
  'password' => "web",
  'host' => 'mysql',
  'driver' => "mysql",
  'port' => '3306',
  'prefix' => "",
  'init_commands' => [
    'isolation_level' => 'SET SESSION tx_isolation=\'READ-COMMITTED\'',
  ],
);

/**
 * Locally, we always want this set.
 *
 * If we're using mailhog we'll turn on our 'debug' flag so that content
 * moderation notifications include the user emails in the message body.
 * The reason for this is because by default content moderation notifications
 * set the users as BCC so we don't get to see who is being emailed. This
 * setting will allow us to see that for testing.
 *
 * @see \epa_workflow_content_moderation_notification_mail_data_alter()
 */
$settings['epa_content_moderation_email_debug'] = TRUE;

/**
 * Show all error messages, with backtrace information.
 *
 * In case the error level could not be fetched from the database, as for
 * example the database connection failed, we rely only on this value.
 */
$config['system.logging']['error_level'] = 'verbose';

/**
 * Disable CSS and JS aggregation.
 */
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

$settings['skip_permissions_hardening'] = TRUE;

$settings['rebuild_access'] = TRUE;
