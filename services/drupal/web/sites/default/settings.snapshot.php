<?php

/**
 * Create s3 stream wrapper for snapshots.
 *
 * Bail out early when no snapshot bucket is configured. When
 * WEBCMS_S3_SNAPSHOT_BUCKET is unset, getenv() returns FALSE, which the
 * flysystem_s3 adapter would otherwise pass to the AWS SDK HeadBucket call as
 * Bucket => false. That fails input validation and breaks both the Flysystem
 * cron job and the site status report on every environment that does not run
 * snapshots.
 */
if (empty(getenv('WEBCMS_S3_SNAPSHOT_BUCKET'))) {
  return;
}

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
