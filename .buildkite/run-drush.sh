#!/bin/bash

set -euo pipefail

task_definition="webcms-drush-$WEBCMS_ENVIRONMENT"
cluster="webcms-cluster-$WEBCMS_ENVIRONMENT"
started_by="build/$WEBCMS_IMAGE_TAG"

# This multi-line string is our Drush update script
# shellcheck disable=SC2016
script='drush --debug --uri="$WEBCMS_SITE_URL" sset system.maintenance_mode 1 --input-format=integer
drush --debug --uri="$WEBCMS_SITE_URL" updb -y
drush --debug --uri="$WEBCMS_SITE_URL" cr
drush --debug --uri="$WEBCMS_SITE_URL" cim -y
drush --uri="$WEBCMS_SITE_URL" ib --choice safe
drush --debug --uri="$WEBCMS_SITE_URL" sset system.maintenance_mode 0 --input-format=integer
drush --debug --uri="$WEBCMS_SITE_URL" cr'

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

# First, stop the running Drupal tasks. We do this here to avoid an issue where requests
# to containers running old versions of the Drupal task family may inadvertently cause
# cache pollution during the Drush run. By stopping all tasks now, we can guarantee that
# the Drupal service will start new tasks running the most up-to-date version of the task
# definition.

echo "--- Stopping Drupal Tasks"

# The list-tasks command produces a JSON object like {taskArns: ["task", "task"]}, but the
# stop-task API only accepts one task. This pipeline uses jq to print task ARNs line by line.
task_list="$(
  aws ecs list-tasks --cluster "$cluster" --family "webcms-drupal-$WEBCMS_ENVIRONMENT" |
  jq -r '.taskArns[]'
)"

# If there are currently running tasks, then remove them. (This conditional prevents xargs
# from iterating over an empy list, causing issues with aws ecs stop-task)
if test -n "$task_list"; then
  # Stop tasks two at a time. Here, -L1 means process one line at a time, and -P2 asks
  # xargs to use up to two child processes at once. This lets us parallelize the otherwise
  # sequential job of stopping ECS tasks. (The task ARN is prepended by xargs, so we don't
  # need to specify a template string.)
  xargs -L1 -P2 aws ecs stop-task --cluster "$cluster" --query 'task.taskArn' --task <<<"$task_list"
fi

echo "--- Running Drush"

# The lines using $(jq . | sed) are doing pretty-printing with indentation:
# - jq . tells jq to just copy the input JSON object to stdout, and it will pretty-print
#   the result by default
# - the <<<"FOO" notation is bash shorthand for replacing the standard input with the
#   string "FOO" (note that this is distinct from <<EOF, which is a multi-line string).
# - sed -e s/^/.../ matches the start of the string and "replaces" it with the ... string

# Output all of the information we need to know what we've passed on to AWS
cat <<EOF
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

# Run a Drush task, capturing the result for inspection
result="$(
  aws ecs run-task \
    --task-definition "$task_definition" \
    --cluster "$cluster" \
    --overrides "$overrides" \
    --network-configuration "$network_configuration" \
    --capacity-provider capacityProvider=FARGATE,weight=1,base=1 \
    --started-by "$started_by"
)"

# The RunTask API returns failures as an array of { reason: string; failure?: string }
# objects. First, check to see if we received any from the API.
failure_count="$(jq '.failures | length' <<<"$result")"
if test "$failure_count" -gt 0; then
  # Write to stderr (we'll be exiting at the end of this block, so it's safe to do this
  # redirection).
  exec >&2

  echo
  echo "Failed to run task. Errors follow:"

  # Since the error messages may be an array of arbitrary length, we use jq to reformat
  # the array into a bulleted list for Buildkite.
  jq -r '
    .failures
    | map(if .detail then "* \(.reason) (\(.detail))" else "* \(.reason)" end)
    | join("\n")
  ' <<<"$result"

  # Force Buildkite to expand this output
  echo "^^^ +++"

  # Fail
  exit 1
fi

arn="$(jq -r '.tasks[0].taskArn' <<<"$result")"

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

    # Did Drush exit due to a signal?
    if test "$exit_code" -gt 128; then
      # Find the signal number and try to match up a name if we can
      signal=$((exit_code - 128))

      echo -n "  WARNING: Drush exited with signal $signal"

      # If the signal corresponded to a known signal name, print that out too
      if signal_name="$(kill -l "$signal" 2>/dev/null)"; then
        echo " ($signal_name)"
      else
        echo
      fi
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
  echo -n "Logs: "

  # Construct a direct link to the CloudWatch logs.
  IFS=/ read -ra parts <<<"$arn"
  log_group="/webcms-$WEBCMS_ENVIRONMENT/app-drush"
  log_stream="drush/drush/${parts[-1]}"
  url="https://console.aws.amazon.com/cloudwatch/home?region=us-east-1#logsV2:log-groups/log-group/${log_group//\//\$252F}/log-events/${log_stream//\//\$252F}"

  # Generate a nicely-formatted URL for the Buildkite logs
  # cf. https://buildkite.com/docs/pipelines/links-and-images-in-log-output#links
  printf '\033]1339;%s\a\n' "url='$url';content='Task ${parts[-1]}'"
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
