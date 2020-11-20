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
echo "NODE REVISIONS - BASIC PAGE"
echo "========================"
echo "Starting upgrade_d7_node_revision_page"
for ((i = 0; i < 199; i++))
  do
    drush migrate-import upgrade_d7_node_revision_page --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_page";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_page --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "9000" ]
  then
    echo "All expected upgrade_d7_node_revision_page items imported.";
  else
    echo "Not all upgrade_d7_node_revision_page items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PANELIZER NODE REVISIONS - BASIC PAGE"
echo "========================"
echo "Starting upgrade_d7_node_revision_page_panelizer"
for ((i = 0; i < 81; i++))
  do
    drush migrate-import upgrade_d7_node_revision_page_panelizer --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_page_panelizer";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_page_panelizer --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_revision_page_panelizer items imported.";
  else
    echo "Not all upgrade_d7_node_revision_page_panelizer items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "SIX PACK NODE REVISIONS - BASIC PAGE"
echo "========================"
echo "Starting upgrade_d7_node_revision_page_sixpack"
for ((i = 0; i < 81; i++))
  do
    drush migrate-import upgrade_d7_node_revision_page_sixpack --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_page_sixpack";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_page_sixpack --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_revision_page_sixpack items imported.";
  else
    echo "Not all upgrade_d7_node_revision_page_sixpack items were imported. Stopping the migration.";
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
