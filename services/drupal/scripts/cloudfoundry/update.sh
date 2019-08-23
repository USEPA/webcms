#!/bin/sh

# This script is run after a successful CloudFoundry deployment. See the cf-push.sh script
# in the .buildkite directory.

set -exuo pipefail

export PATH="$PATH:/var/www/html/vendor/bin"

cd /var/www/html/web

drush updb -y
drush cim -y
drush cr
