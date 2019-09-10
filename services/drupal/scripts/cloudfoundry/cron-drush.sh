#!/bin/sh

# This script is run by cron. It performs a Drush cron run and is intended to be
# run hourly.

set -euo pipefail

# Redirect stdout to stderr. This forces all cron output to be sent to the cron log
# instead of to the mail spool.
# The redirection here is inherited by child processes, so this is all we need to
exec 1>&2

if test "$CF_INSTANCE_INDEX" != 0; then
  echo Not running cron on instance "$CF_INSTANCE_INDEX".
  exit 0
fi

drush_uri="$(sh /var/www/html/scripts/cloudfoundry/drush-uri.sh)"

cd /var/www/html/web

drush --uri="$drush_uri" cron
