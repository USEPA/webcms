set -exuo pipefail

started_by="bschumacher"
memory=8

# shellcheck disable=SC2016
script="$(
  cat <<'SCRIPT'
set -euo

apk add bash

exec bash -e - <<'EOF'
echo "========================"
echo "GROUP CONTENT - BASIC PAGE"
echo "========================"
echo "Starting upgrade_d7_group_content_node_page"
for ((i = 0; i < 32; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_page --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_page";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_page --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_page items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_page items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - DOCUMENT"
echo "========================"
echo "Starting upgrade_d7_group_content_node_page"
for ((i = 0; i < 50; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_document --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_document";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_document --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_document items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_document items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - EVENT"
echo "========================"
echo "Starting upgrade_d7_group_content_node_event"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_event --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_event";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_event --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_event items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_event items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - FAQ"
echo "========================"
echo "Starting upgrade_d7_group_content_node_faq"
for ((i = 0; i < 3; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_faq --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_faq";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_faq --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_faq items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_faq items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - FILE"
echo "========================"
echo "Starting upgrade_d7_group_content_file"
for ((i = 0; i < 53; i++))
  do
    drush migrate-import upgrade_d7_group_content_file --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_file";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_file --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_file items imported.";
  else
    echo "Not all upgrade_d7_group_content_file items were imported. Stopping the migration.";
    exit 1;
  fi

EOF
SCRIPT
)"

# Use jq to format the container overrides in a way that plays nicely with
# AWS ECS' character count limitations on JSON input, as well as avoids
# potential issues with quoting
overrides="$(
  jq -cn --arg script "$script" --arg memory "$memory" '
{
  "containerOverrides": [
    {
      "name": "drush",
      "command": ["/bin/sh", "-ec", $script],
    }
  ],
  "cpu": "1024",
  "memory": "\(($memory | tonumber) * 1024)"
}
'
)"

# Run a Drush task, capturing the task's ARN for later (that's the jq line at the end)
arn="$(
  aws ecs run-task \
    --capacity-provider capacityProvider=FARGATE,weight=1,base=1 \
    --task-definition webcms-drush-stage \
    --cluster webcms-cluster-stage \
    --overrides "$overrides" \
    --network-configuration "$(cat drushvpc-stage.json)" \
    --started-by "$started_by" |
    jq -r '.tasks[0].taskArn'
)"
