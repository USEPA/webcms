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
echo "GROUP CONTENT - NEWS RELEASE"
echo "========================"
echo "Starting upgrade_d7_group_content_node_news_release"
for ((i = 0; i < 4; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_news_release --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_news_release";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_news_release --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_news_release items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_news_release items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - PUBLIC NOTICE"
echo "========================"
echo "Starting upgrade_d7_group_content_node_public_notice"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_public_notice --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_public_notice";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_public_notice --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_public_notice items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_public_notice items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - REGULATION"
echo "========================"
echo "Starting upgrade_d7_group_content_node_regulation"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_regulation --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_regulation";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_regulation --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_regulation items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_regulation items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - WEB AREA"
echo "========================"
echo "Starting upgrade_d7_group_content_node_web_area"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_web_area --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_web_area";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_web_area --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_web_area items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_web_area items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "GROUP CONTENT - WEBFORM"
echo "========================"
echo "Starting upgrade_d7_group_content_node_webform"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_group_content_node_webform --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_group_content_node_webform";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_group_content_node_webform --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_group_content_node_webform items imported.";
  else
    echo "Not all upgrade_d7_group_content_node_webform items were imported. Stopping the migration.";
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
