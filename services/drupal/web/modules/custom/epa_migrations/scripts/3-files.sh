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
echo "FILES"
echo "========================"
echo "Starting upgrade_d7_file"
for ((i = 0; i < 190; i++))
  do
    drush migrate-import upgrade_d7_file --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_file";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_file --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "1000" ]
  then
    echo "Less than 1000 upgrade_d7_file items left unprocessed. Continuing.";
  else
    echo "More than 1000 upgrade_d7_file items were left unprocessed. Stopping the migration.";
    exit 1;
  fi

echo "========================"
echo "MEDIA ENTITIES - AUDIO"
echo "========================"
echo "Starting upgrade_d7_file_entity_audio"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_file_entity_audio --limit=1000;
    echo "Re-starting upgrade_d7_file_entity_audio";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_file_entity_audio --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_file_entity_audio items imported.";
  else
    echo "Not all upgrade_d7_file_entity_audio items were imported. Continuing since this script doesn't depend on these entities.";
  fi

echo "========================"
echo "MEDIA ENTITIES - DOCUMENT"
echo "========================"
echo "Starting upgrade_d7_file_entity_document"
for ((i = 0; i < 127; i++))
  do
    drush migrate-import upgrade_d7_file_entity_document --limit=1000  --continue-on-failure;
    echo "Re-starting upgrade_d7_file_entity_document";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_file_entity_document --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_file_entity_document items imported.";
  else
    echo "Not all upgrade_d7_file_entity_document items were imported. Continuing since this script doesn't depend on these entities.";
  fi

echo "========================"
echo "MEDIA ENTITIES - IMAGE"
echo "========================"
echo "Starting upgrade_d7_file_entity_image"
for ((i = 0; i < 59; i++))
  do
    drush migrate-import upgrade_d7_file_entity_image --limit=1000  --continue-on-failure;
    echo "Re-starting upgrade_d7_file_entity_image";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_file_entity_image --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_file_entity_image items imported.";
  else
    echo "Not all upgrade_d7_file_entity_image items were imported. Continuing since this script doesn't depend on these entities.";
  fi

echo "========================"
echo "MEDIA ENTITIES - OTHER"
echo "========================"
echo "Starting upgrade_d7_file_entity_other"
for ((i = 0; i < 40; i++))
  do
    drush migrate-import upgrade_d7_file_entity_other --limit=1000  --continue-on-failure;
    echo "Re-starting upgrade_d7_file_entity_other";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_file_entity_other --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_file_entity_other items imported.";
  else
    echo "Not all upgrade_d7_file_entity_other items were imported. Continuing since this script doesn't depend on these entities.";
  fi

echo "========================"
echo "MEDIA ENTITIES - VIDEO"
echo "========================"
echo "Starting upgrade_d7_file_entity_video"
for ((i = 0; i < 1; i++))
  do
    drush migrate-import upgrade_d7_file_entity_video --limit=1000 --continue-on-failure;
    echo "Re-starting upgrade_d7_file_entity_video";
  done

# Check if unproccessed items is <= 0
drush_output=$(drush ms upgrade_d7_file_entity_video --format string);
output=( $drush_output )
if [ "${output[9]}" == "0" ] || [ "${output[9]}" -lt "0" ]
  then
    echo "All upgrade_d7_file_entity_video items imported.";
  else
    echo "Not all upgrade_d7_file_entity_video items were imported. Continuing since this script doesn't depend on these entities.";
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
