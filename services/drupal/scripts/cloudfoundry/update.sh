#!/bin/sh

# This script is run after a successful CloudFoundry deployment. See the cf-push.sh script
# in the .buildkite directory.

set -exuo pipefail

drush_uri="$(sh /var/www/html/scripts/cloudfoundry/drush-uri.sh)"

cd /var/www/html/web

drush --uri="$drush_uri" updb -y
drush --uri="$drush_uri" cim -y
drush --uri="$drush_uri" ib --choice safe
drush --uri="$drush_uri" cr
