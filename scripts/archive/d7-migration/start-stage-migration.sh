#!/bin/bash

# Local script to kick off the ECS task to run the drush migration script [1] on
# the webcms-cluster-stage cluster.
#
# Requirements:
#   - drushvpc-stage.json must be in cwd
#   - AWS_PROFILE is set appropriately
#
# USAGE: bash scripts/start-stage-migration.sh
#
# if you need to stop this, use scripts/halt-stage-migration.sh
#
# [1] See /services/drupal/scripts/ecs/drush-migrate.sh

set -euo pipefail

started_by="bschumacher"
script='drush-migrate'

# Use jq to format the container overrides in a way that plays nicely with
# AWS ECS' character count limitations on JSON input, as well as avoids
# potential issues with quoting
overrides="$(
  jq -cn --arg script "$script" '
{
  "containerOverrides": [
    {
      "name": "drush",
      "command": ["/bin/sh", "-ec", $script],
    }
  ],
  "memory": "8192"
}
'
)"

# Run a Drush task, capturing the task's ARN for later (that's the jq line at the end)
arn="$(
  aws ecs run-task \
    --task-definition webcms-drush-stage \
    --cluster webcms-cluster-stage \
    --overrides "$overrides" \
    --capacity-provider-strategy "capacityProvider=FARGATE,weight=1,base=1" \
    --network-configuration "$(cat drushvpc-stage.json)" \
    --started-by "$started_by" |
    jq -r '.tasks[0].taskArn'
)"

echo "Task ARN: $arn"
echo "Find it here: https://console.aws.amazon.com/ecs/home?region=us-east-1#/clusters/webcms-cluster-stage/tasks"
