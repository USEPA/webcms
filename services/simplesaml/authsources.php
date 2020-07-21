<?php

$config = [
  // Allow logging in as admin via username/password
  'admin' => [
    'core:AdminPassword'
  ],

  'local-login' => [
    'exampleauth:UserPass',

    'user:password' => [
      'uid' => ['user'],
      'mail' => 'user@localhost.localdomain',
    ],
  ],
];
