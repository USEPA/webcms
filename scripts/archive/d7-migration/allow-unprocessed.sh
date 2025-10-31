#!/bin/bash

# Local script to set expected unprocessed counts for the migration.
#
# Requirements:
#   - drushvpc-stage.json must be in cwd
#   - AWS_PROFILE is set appropriately
#
# USAGE: bash scripts/allow-unprocessed.sh

set -euo pipefail

started_by="bschumacher"

# e.g. drush state-set epa.allowed_unprocessed.upgrade_d7_node_revision_document 1

script=$(cat <<EOF

EOF
)

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
  ]
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
