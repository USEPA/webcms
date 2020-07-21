<?php

$config = [
  // Allow logging in as admin via username/password
  'admin' => [
    'core:AdminPassword'
  ],

  'oidc' => [
    'authoauth2:OpenIDConnect',
    'issuer' => 'https://oidc.localhost:4443/',
    'clientId' => 'simplesaml',
    'clientSecret' => 'simplesamlphp_secret',
    'scopes' => 'openid email',
  ],
];
