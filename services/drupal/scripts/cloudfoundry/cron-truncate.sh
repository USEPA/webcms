#!/bin/sh

# This script is run by cron. It truncates log files to avoid excessive disk space usage
# caused by long-running containers. Note that this does not cause a loss of data;
# any output written to these files has already been fed by the tail command into the
# CloudFoundry logs.

set -euo pipefail

# Redirect stdout to stderr. This forces all cron output to be sent to the cron log
# instead of to the mail spool.
exec 1>&2

for log_file in \
    /var/log/nginx/access.log \
    /var/log/nginx/error.log \
    /var/log/php-fpm/access.log \
    /var/log/php-fpm/error.log \
    /var/log/cron.log
do
  truncate "$log_file"
done
