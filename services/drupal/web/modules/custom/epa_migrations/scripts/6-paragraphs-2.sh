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
echo "PARAGRAPHS - DOCKET"
echo "========================"
echo "Starting upgrade_d7_paragraph_docket"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_paragraph_docket --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_paragraph_docket";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_paragraph_docket --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_paragraph_docket items imported.";
  else
    echo "Not all upgrade_d7_paragraph_docket items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PARAGRAPHS - FRC"
echo "========================"
echo "Starting upgrade_d7_paragraph_frc"
for ((i = 0; i < 3; i++))
  do
    drush migrate-import upgrade_d7_paragraph_frc --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_paragraph_frc";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_paragraph_frc --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_paragraph_frc items imported.";
  else
    echo "Not all upgrade_d7_paragraph_frc items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PARAGRAPHS - LEGAL AUTHORITIES"
echo "========================"
echo "Starting upgrade_d7_paragraph_legal_authorities"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_paragraph_legal_authorities --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_paragraph_legal_authorities";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_paragraph_legal_authorities --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_paragraph_legal_authorities items imported.";
  else
    echo "Not all upgrade_d7_paragraph_legal_authorities items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PARAGRAPHS - LOCATIONS OF PROP ACTIONS"
echo "========================"
echo "Starting upgrade_d7_paragraph_locations_of_prop_actions"
for ((i = 0; i < 2; i++))
  do
    drush migrate-import upgrade_d7_paragraph_locations_of_prop_actions --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_paragraph_locations_of_prop_actions";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_paragraph_locations_of_prop_actions --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_paragraph_locations_of_prop_actions items imported.";
  else
    echo "Not all upgrade_d7_paragraph_locations_of_prop_actions items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PARAGRAPHS - PRESS OFFICERS"
echo "========================"
echo "Starting upgrade_d7_paragraph_press_officers"
for ((i = 0; i < 4; i++))
  do
    drush migrate-import upgrade_d7_paragraph_press_officers --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_paragraph_press_officers";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_paragraph_press_officers --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_paragraph_press_officers items imported.";
  else
    echo "Not all upgrade_d7_paragraph_press_officers items were imported. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "PARAGRAPHS - WEBFORM HTML"
echo "========================"
echo "Starting upgrade_d7_node_webform_paragraph_html"
for ((i = 0; i < 1496; i++))
  do
    drush migrate-import upgrade_d7_node_webform_paragraph_html --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_node_webform_paragraph_html";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_node_webform_paragraph_html --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_node_webform_paragraph_html items imported.";
  else
    echo "Not all upgrade_d7_node_webform_paragraph_html items were imported. Stopping the migration.";
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
