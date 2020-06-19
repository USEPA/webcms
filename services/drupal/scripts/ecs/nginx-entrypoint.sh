#!/bin/sh

set -e

# Replace runtime parameters (see cluster.tf)
sed -i \
  -e "s/WEBCMS_S3_DOMAIN/$WEBCMS_S3_DOMAIN/" \
  -e "s/WEBCMS_DOMAIN/$WEBCMS_DOMAIN/" \
  /etc/nginx/conf.d/default.conf

exec nginx -g 'daemon off;'
