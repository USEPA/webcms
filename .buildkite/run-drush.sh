#!/bin/bash

set -euo pipefail

started_by="build/$WEBCMS_IMAGE_TAG"

# This multi-line string is our Drush update script
# shellcheck disable=SC2016
script='drush --uri="$WEBCMS_SITE_URL" updb -y
drush --uri="$WEBCMS_SITE_URL" cr
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

task_definition="webcms-drush-$WEBCMS_ENVIRONMENT"
cluster="webcms-cluster-$WEBCMS_ENVIRONMENT"

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
$(sed -e 's/^/  /' <<<"$script")
EOF

# Run a Drush task, capturing the task's ARN for later (that's the jq line at the end)
arn="$(
  aws ecs run-task \
    --task-definition "$task_definition" \
    --cluster "$cluster" \
    --overrides "$overrides" \
    --network-configuration "$network_configuration" \
    --capacity-provider capacityProvider=FARGATE,weight=1,base=1 \
    --started-by "$started_by" |
    jq -r '.tasks[0].taskArn'
)"

echo "--- Waiting on task ARN $arn"

# Define a last_status variable for output
last_status=

while true; do
  output="$(aws ecs describe-tasks --tasks "$arn" --cluster "$cluster")"

  # If ECS reports any failures, bail early with the pretty-printed results
  if test "$(jq '.failures | length' <<<"$output")" -gt 0; then
    jq '.failures' <<<"$output" >&2
    exit 1
  fi

  # Check the last reported status from ECS
  status="$(jq -r '.tasks[0].lastStatus' <<<"$output")"

  # To avoid spamming the console, we only output the status when we detect a change
  if test "$last_status" != "$status"; then
    echo "--- Drush status: $status"
    last_status="$status"
  fi

  # If the container is still running, sleep 5 seconds and re-run the loop
  if test "$status" != STOPPED; then
    sleep 5
    continue
  fi

  # Now that we know the task has stopped, it's time to report task stop information. The
  # most normal case for this is the stop reason "Essential container in task exited",
  # which just means that Drush exited.
  #
  # NB. The "| values" in the JQ filters just means "don't report null". This lets us use
  # test -n and test -z for null output, which is the usual shell convention.
  task="$(jq .tasks[0] <<<"$output")"
  stop_code="$(jq -r ".stopCode | values" <<<"$task")"
  stop_reason="$(jq -r ".stoppedReason | values" <<<"$task")"

  # Formats of this block:
  #   Stop information: <code> (<reason>)
  #   Stop information: <reason>
  #   Stop information: Unavailable
  echo -n "Stop information: "
  if test -n "$stop_code"; then
    if test -n "$stop_reason"; then
      echo "$stop_code ($stop_reason)"
    else
      echo "$stop_code"
    fi
  elif test -n "$stop_reason"; then
    echo "$stop_reason"
  else
    echo "Unavailable"
  fi
  echo

  # Determine the container exit information, if any is available
  container="$(jq ".containers[0]" <<<"$task")"
  exit_code="$(jq -r ".exitCode | values" <<<"$container")"
  exit_reason="$(jq -r ".reason | values" <<<"$container")"
  id="$(jq ".runtimeId | values" <<<"$container")"

  # Tracks if we need to exit with 1 or 0
  failure=

  # Formats of this block:
  #   Drush exit: <code> (<reason>)
  #   Drush exit: <code>
  #   Drush exit: <reason>
  #   Drush exit: Unavailable
  echo -n "Drush exit: "
  if test -n "$exit_code"; then
    if test -n "$exit_reason"; then
      echo "$exit_code ($exit_reason)"
    else
      echo "$exit_code"
    fi

    # Mark non-zero exits as a failure
    if test "$exit_code" -ne 0; then
      failure=1
    fi
  elif test -n "$exit_reason"; then
    # If a container exited without a code, it could mean that the container failed to
    # start. Mark this as a failure.
    echo "$exit_reason"
    failure=1
  else
    # If the API gave us nothing, mark that as a failure too.
    echo "Unavailable"
    failure=1
  fi
  echo

  # Formats of this block:
  #   Logs URL: <link>
  #   Logs URL: Unavailable
  echo -n "Logs: "
  if test -n "$id"; then
    # Construct a direct link to the CloudWatch logs.
    url="https://console.aws.amazon.com/cloudwatch/home?region=us-east-1#logsV2:log-groups/log-group/\$252Fwebcms-$WEBCMS_ENVIRONMENT\$252Fapp-drush/log-events/$id"

    # Generate a nicely-formatted URL for the Buildkite logs
    # cf. https://buildkite.com/docs/pipelines/links-and-images-in-log-output#links
    printf '\033]1339;%s\a\n' "url='$url';content='$id'"
  else
    echo "Unavailable"
    failure=1
  fi
  echo

  # Now that we've output all the information we know about, we can exit
  if test -n "$failure"; then
    # If we saw any failures, open the log output by default
    echo "^^^ +++"
    exit 1
  else
    exit 0
  fi
done
