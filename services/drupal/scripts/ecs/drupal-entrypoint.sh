#!/bin/sh

set -eu

newrelic_ini_path="$(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini"

# If we have a non-default license key in this environment, configure New Relic.
if test -n "${WEBCMS_NEW_RELIC_LICENSE:-}" && test "${WEBCMS_NEW_RELIC_LICENSE}" != .; then
  sed -i \
    -e "s/REPLACE_WITH_REAL_KEY/${WEBCMS_NEW_RELIC_LICENSE}/" \
    -e "s!newrelic.appname[[:space:]]=[[:space:]].*!newrelic.appname=\"${WEBCMS_NEW_RELIC_APPNAME}\"!" \
    "$newrelic_ini_path"

  status=enabled
else
  # Otherwise, disable the extension.
  echo 'newrelic.enabled=false' >> "$newrelic_ini_path"

  status=disabled
fi

{
  echo 'newrelic.distributed_tracing_enabled=true'
  echo 'newrelic.daemon.address="newrelic-php-daemon:31339"'
  echo 'newrelic.daemon.app_connect_timeout=15s'
  echo 'newrelic.daemon.start_timeout=5s'
} >> "$newrelic_ini_path"

echo "Status of New Relic in $newrelic_ini_path: $status"

# Forward to the original image entrypoint
exec docker-php-entrypoint "$@"
