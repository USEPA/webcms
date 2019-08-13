<?php

// Override the endpoint used by s3fs in order to talk to Minio.
$config['s3fs.settings']['use_customhost'] = TRUE;
$config['s3fs.settings']['hostname'] = 'minio:9000';

// Uncomment out the below line for first-time installation (in order to avoid
// not having a redis cache backend causing weird installation errors).
// unset($settings['cache']['default']);
