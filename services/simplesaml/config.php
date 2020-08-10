<?php

$config = [
  'baseurlpath' => 'http://localhost:5000/simplesaml/',

  // Send mail to MailHog (localhost:8025 to see UI)
  'mail.transport.method' => 'smtp',
  'mail.transport.options' => [
    'host' => 'mailhog',
    'port' => 1025,
  ],

  'debug' => [
    'saml' => true,
    'backtraces' => true,
    'validatexml' => false,
  ],

  'timezone' => 'America/New_York',

  // It's not the default, so SimpleSAMLphp should consider it good enough
  'secretsalt' => 'randomsalt',

  // SSHA256 of the string 'admin'
  'auth.adminpassword' => '$2y$10$B0rTzJnZUzbiq7OTRWhPx.mYNRiM3DdLXIxBaPashshV25DBTTp1C',

  'admin.checkforupdates' => false,

  // Disables the ability to send a report via email to the technical contact - not useful
  // for local dev
  'errorreporting' => false,

  // Redirect SimpleSAMLphp logs to container stdout
  'logging.level' => SimpleSAML\Logger::DEBUG,
  'logging.handler' => 'file',
  'logging.logfile' => '/dev/console',

  // Enable SAML 2.0 IdP functionality
  'enable.saml20-idp' => true,

  // Enable exampleAuth module - this gives us username/password authentication
  'module.enable' => [
    'exampleauth' => true,
  ],

  'tempdir' => '/tmp',
];
