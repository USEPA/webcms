set -exuo pipefail

started_by="bschumacher"
memory=8

# shellcheck disable=SC2016
script="$(
  cat <<'SCRIPT'
set -euo

apk add bash

exec bash -e - <<'EOF'
# Remove this for round 4; covered by paragraphs.
echo "========================"
echo "PARAGRAPHS - WEBFORM HTML"
echo "========================"
echo "Starting upgrade_d7_node_webform_paragraph_html"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_node_webform_paragraph_html --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_webform_paragraph_html";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_webform_paragraph_html --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_webform_paragraph_html items imported.";
  else
    echo "Not all upgrade_d7_node_webform_paragraph_html items were imported. Stopping the migration.";
    exit 1;
  fi

# Remove this for round 4; covered by paragraph revisions.
echo "========================"
echo "PARAGRAPHS - WEBFORM REVISION HTML"
echo "========================"
echo "Starting upgrade_d7_node_revision_webform_paragraph_html"
for ((i = 0; i < 8; i++))
  do
    drush migrate-import upgrade_d7_node_revision_webform_paragraph_html --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_webform_paragraph_html";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_webform_paragraph_html --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_revision_webform_paragraph_html items imported.";
  else
    echo "Not all upgrade_d7_node_revision_webform_paragraph_html items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "NODES - WEBFORM"
echo "========================"
echo "Starting upgrade_d7_node_webform"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_node_webform --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_webform";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_webform --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_webform items imported.";
  else
    echo "Not all upgrade_d7_node_webform items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "NODE REVISIONS - WEBFORM"
echo "========================"
echo "Starting upgrade_d7_node_revision_webform"
for ((i = 0; i < 8; i++))
  do
    drush migrate-import upgrade_d7_node_revision_webform --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_webform";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_webform --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_revision_webform items imported.";
  else
    echo "Not all upgrade_d7_node_revision_webform items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "WEBFORM SUBMISSIONS"
echo "========================"
echo "Starting upgrade_d7_webform_submission"
for ((i = 0; i < 41; i++))
  do
    drush migrate-import upgrade_d7_webform_submission --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_webform_submission";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_webform_submission --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_webform_submission items imported.";
  else
    echo "Not all upgrade_d7_webform_submission items were imported. Stopping the migration.";
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
