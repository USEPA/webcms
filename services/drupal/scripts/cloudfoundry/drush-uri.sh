#!/bin/sh

set -euo pipefail

env="${ENV_NAME:-}"

# Assume an unset environment is the production environment
if test -z "$env" || test "$env" == prod; then
  echo www.epa.gov
else
  # For non-production environments, trust the CloudFoundry-generated routes since we're
  # not behind a CDN.
  echo "$VCAP_APPLICATION" | jq -r '.application_uris[0]'
fi
