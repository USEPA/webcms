set -exuo pipefail

started_by="bschumacher"
memory=128

# shellcheck disable=SC2016
script="$(
  cat <<'SCRIPT'
set -euo

apk add bash

exec bash -e - <<'EOF'

echo "RUNNING ON webcms-migration-mutual-haddock CAPACITY PROVIDER"

# echo "========================"
# echo "NODE REVISIONS - WEB AREA"
# echo "========================"
# echo "Starting upgrade_d7_node_revision_web_area"
# for ((i = 0; i < 30; i++))
#   do
#     drush migrate-import upgrade_d7_node_revision_web_area --limit=1000 --continue-on-failure;
#     echo "Re-starting upgrade_d7_node_revision_web_area";
#   done

# # Check if unproccessed items is <= 0
# drush_output=$(drush ms upgrade_d7_node_revision_web_area --format string);
# output=( $drush_output )
# if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "29270" ]
#   then
#     echo "All expected upgrade_d7_node_revision_web_area items imported.";
#   else
#     echo "Not all upgrade_d7_node_revision_web_area items were imported. Stopping the migration.";
#     exit 1;
#   fi

echo "========================"
echo "PANELIZER NODE REVISIONS - WEB AREA"
echo "========================"
echo "Starting upgrade_d7_node_revision_web_area_panelizer"
for ((i = 0; i < 30; i++))
  do
    drush migrate-import upgrade_d7_node_revision_web_area_panelizer --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_web_area_panelizer";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_web_area_panelizer --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "2" ]
  then
    echo "All expected upgrade_d7_node_revision_web_area_panelizer items imported.";
  else
    echo "Not all upgrade_d7_node_revision_web_area_panelizer items were imported. Stopping the migration.";
    exit 1;
  fi

# echo "========================"
# echo "NODE REVISIONS - REGULATION"
# echo "========================"
# echo "Starting upgrade_d7_node_revision_regulation"
# for ((i = 0; i < 1; i++))
#   do
#     drush migrate-import upgrade_d7_node_revision_regulation --limit=1000 --continue-on-failure;
#     echo "Re-starting upgrade_d7_node_revision_regulation";
#   done

# # Check if unproccessed items is <= 0
# drush_output=$(drush ms upgrade_d7_node_revision_regulation --format string);
# output=( $drush_output )
# if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
#   then
#     echo "All upgrade_d7_node_revision_regulation items imported.";
#   else
#     echo "Not all upgrade_d7_node_revision_regulation items were imported. Stopping the migration.";
#     exit 1;
#   fi

echo "========================"
echo "NODE REVISIONS - PUBLIC NOTICE"
echo "========================"
echo "Starting upgrade_d7_node_revision_public_notice"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_node_revision_public_notice --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_public_notice";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_public_notice --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_node_revision_public_notice items imported.";
  else
    echo "Not all upgrade_d7_node_revision_public_notice items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "NODE REVISIONS - NEWS RELEASE"
echo "========================"
echo "Starting upgrade_d7_node_revision_news_release"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_node_revision_news_release --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_news_release";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_news_release --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_node_revision_news_release items imported.";
  else
    echo "Not all upgrade_d7_node_revision_news_release items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "NODE REVISIONS - FAQ"
echo "========================"
echo "Starting upgrade_d7_node_revision_faq"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_node_revision_faq --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_faq";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_faq --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_node_revision_faq items imported.";
  else
    echo "Not all upgrade_d7_node_revision_faq items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "NODE REVISIONS - EVENT"
echo "========================"
echo "Starting upgrade_d7_node_revision_event"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_node_revision_event --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_event";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_event --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_node_revision_event items imported.";
  else
    echo "Not all upgrade_d7_node_revision_event items were imported. Stopping the migration.";
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
    --capacity-provider capacityProvider=webcms-migration-mutual-haddock,weight=1,base=1 \
    --task-definition webcms-drush-stage \
    --cluster webcms-cluster-stage \
    --overrides "$overrides" \
    --network-configuration "$(cat drushvpc-stage.json)" \
    --started-by "$started_by" |
    jq -r '.tasks[0].taskArn'
)"
