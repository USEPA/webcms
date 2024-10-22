<?php

/**
 * Create s3 stream wrapper for snapshots.
 */
$schemes = [
  's3-snapshot' => [
    'driver' => 's3',
    'config' => [
      'region' => getenv('WEBCMS_S3_REGION'),
      'bucket' => getenv('WEBCMS_S3_SNAPSHOT_BUCKET'),
    ],
    'cache' => TRUE,
  ],
];

$settings['flysystem'] = $schemes;

/**
 * Settings for tome.
 * 
 * - Set static directory.
 * - Prevent crawling anchors and iframes.
 * - Set paths to exclude from static generation.
 */
$settings['tome_static_directory'] = 's3-snapshot://';

$settings['tome_static_crawl'] = FALSE;

$settings['tome_static_path_exclude'] = [
  '/perspectives/search',
  '/faqs/search',
  '/publicnotices/notices-search',
  '/newsreleases/search',
  '/speeches/search',
];
