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
echo "PARAGRAPHS - NEWS RELEASE REVISIONS"
echo "========================"
echo "Starting upgrade_d7_node_revision_news_release_paragraph_html"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_node_revision_news_release_paragraph_html --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_news_release_paragraph_html";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_news_release_paragraph_html --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All expected upgrade_d7_node_revision_news_release_paragraph_html items imported.";
  else
    echo "Not all expected upgrade_d7_node_revision_news_release_paragraph_html items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PARAGRAPHS - WEB AREA BANNER REVISIONS"
echo "========================"
echo "Starting upgrade_d7_node_revision_web_area_paragraph_banner"
for ((i = 0; i < 30; i++))
  do
    drush migrate-import upgrade_d7_node_revision_web_area_paragraph_banner --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_revision_web_area_paragraph_banner";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_revision_web_area_paragraph_banner --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_node_revision_web_area_paragraph_banner items imported.";
  else
    echo "Not all upgrade_d7_node_revision_web_area_paragraph_banner items were imported. Stopping the migration.";
    exit 1;
  fi

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
