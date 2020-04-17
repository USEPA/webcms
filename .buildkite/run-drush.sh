#!/bin/bash

set -euo pipefail

started_by="build/$WEBCMS_IMAGE_TAG"

# This multi-line string is our Drush update script
# shellcheck disable=SC2016
script='drush --uri="$WEBCMS_SITE_URL" updb -y
drush --uri="$WEBCMS_SITE_URL" cim -y
drush --uri="$WEBCMS_SITE_URL" ib --choice safe
drush --uri="$WEBCMS_SITE_URL" cr'

# Use jq to format the container overrides in a way that plays nicely with AWS ECS'
# character count limitations on JSON input, as well as avoids potential issues with
# quoting. We pass -x to see the update script as it runs in the logs.
overrides="$(
  jq -cn --arg script "$script" '
{
  "containerOverrides": [
    {
      "name": "drush",
      "command": ["/bin/sh", "-exc", $script]
    }
  ]
}
'
)"

network_configuration="$(cat drushvpc.json)"

task_definition="webcms-drush"
cluster="webcms-cluster"

# The lines using $(jq . | sed) are doing pretty-printing with indentation:
# - jq . tells jq to just copy the input JSON object to stdout, and it will pretty-print
#   the result by default
# - the <<<"FOO" notation is bash shorthand for replacing the standard input with the
#   string "FOO" (note that this is distinct from <<EOF, which is a multi-line string).
# - sed -e s/^/.../ matches the start of the string and "replaces" it with the ... string

# Output all of the information we need to know what we've passed on to AWS
cat <<EOF
--- Running Drush
Task definition: $task_definition
Cluster: $cluster
Started by: $started_by

Task overrides:
$(jq . <<<"$overrides" | sed -e 's/^/  /')

Task networking:
$(jq . <<<"$network_configuration" | sed -e 's/^/  /')

Update script:
$script
EOF

# Run a Drush task, capturing the task's ARN for later (that's the jq line at the end)
arn="$(
  aws ecs run-task \
    --task-definition "$task_definition" \
    --cluster "$cluster" \
    --overrides "$overrides" \
    --network-configuration "$network_configuration" \
    --started-by "$started_by" |
    jq -r '.tasks[0].taskArn'
)"

echo "--- Waiting on task ARN $arn"

# Define a last_status variable for output
last_status=

while true; do
  output="$(aws ecs describe-tasks --tasks "$arn" --cluster webcms-cluster)"

  # If ECS reports any failures, bail early with the pretty-printed results
  if test "$(jq '.failures | length' <<<"$output")" -gt 0; then
    jq '.failures' <<<"$output" >&2
    exit 1
  fi

  # Check the last reported status from ECS
  status="$(jq -r '.tasks[0].lastStatus' <<<"$output")"
  case "$status" in
  STOPPING | STOPPED)
    echo "Task exited. Check logs in CloudWatch for more details."

    # If we we able to detect an error, flag the build as failing
    exit_code="$(jq '.tasks[0].containers[0].exitCode' <<<"$output")"
    if test "$exit_code" -ne 0; then
      echo "Drush exited with non-zero exit code $exit_code" >&2
      exit 1
    fi
    break
    ;;

  *)
    # To avoid spamming the console, we only output the status when we detect a change
    if test "$last_status" != "$status"; then
      echo "--- Drush status: $status"
      last_status="$status"
    fi

    sleep 5
    ;;
  esac
done
