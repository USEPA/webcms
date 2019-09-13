#!/bin/sh

# This is the entry point script for the Drupal container. It initializes settings
# based on the $VCAP_SERVICES variable (needed for proxying to the S3 bucket), starts
# the needed daemon processes, and watches all log files.

set -e

S3_BUCKET="$(echo "$VCAP_SERVICES" | jq -r '.s3[0].credentials.bucket')"
S3_REGION="$(echo "$VCAP_SERVICES" | jq -r '.s3[0].credentials.region')"

sed -i \
    -e s/S3_BUCKET/"$S3_BUCKET"/ \
    -e s/S3_REGION/"$S3_REGION"/ \
    -e s/PORT/"$PORT"/ \
    /etc/nginx/conf.d/default.conf

crond -b -L /var/log/cron.log
php-fpm -D
nginx

# Tail each daemon's log files instead of having them write to stdout/stderr. This avoids
# some issues trying to get them all to write to /dev/stderr or /proc/self/fd/2.
exec tail -f \
  /var/log/nginx/access.log \
  /var/log/nginx/error.log \
  /var/log/php-fpm/access.log \
  /var/log/php-fpm/error.log \
  /var/log/cron.log
