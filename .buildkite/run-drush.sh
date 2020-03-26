#!/bin/bash

set -euo pipefail

started_by="build/$WEBCMS_IMAGE_TAG"

# This multi-line string is our Drush update script
# shellcheck disable=SC2016
script='drush --uri="$WEBCMS_SITE_URL" updb -y
drush --uri="$WEBCMS_SITE_URL" cim -y
drush --uri="$WEBCMS_SITE_URL" cr'

# Use jq to format the container overrides in a way that plays nicely with
# AWS ECS' character count limitations on JSON input, as well as avoids
# potential issues with quoting
overrides="$(
  jq -cn --arg script "$script" '
{
  "containerOverrides": [
    {
      "name": "drush",
      "command": ["/bin/sh", $script]
    }
  ]
}
'
)"

arn="$(
  aws ecs run-task \
    --task-definition webcms-drush \
    --cluster webcms-cluster \
    --overrides "$overrides" \
    --network-configuration "$(cat drushvpc.json)" \
    --started-by "$started_by" \
    jq -r '.tasks[0].arn'
)"

while true; do
  output="$(aws ecs describe-tasks --include "$arn")"

  if "$(jq '.failures | length' <<<"$output")" -gt 0; then
    jq '.failures' <<<"$output" >&2
    exit 1
  fi

  status="$(jq '.tasks[0].lastStatus' <<<"$output")"
  case "$status" in
  STOPPING | STOPPED)
    echo "Task exited. Check logs in CloudWatch for more details."
    break
    ;;

  *)
    echo "Drush status: $status"
    sleep 5
    ;;
  esac
done
