#!/bin/sh

set -e

# Replace runtime parameters
sed -i \
  -e "s/WEBCMS_S3_DOMAIN/$WEBCMS_S3_DOMAIN/" \
  /etc/nginx/conf.d/default.conf

exec nginx -g 'daemon off;'
