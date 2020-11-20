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
echo "TAXONOMY TERMS"
echo "========================"
echo "Starting upgrade_d7_taxonomy_term_channels"
drush migrate-import upgrade_d7_taxonomy_term_channels

echo "Starting upgrade_d7_taxonomy_term_environmental_laws_regulations_and_treaties"
drush migrate-import upgrade_d7_taxonomy_term_environmental_laws_regulations_and_treaties

echo "Starting upgrade_d7_taxonomy_term_epa_organization"
drush migrate-import upgrade_d7_taxonomy_term_epa_organization

echo "Starting upgrade_d7_taxonomy_term_event_type"
drush migrate-import upgrade_d7_taxonomy_term_event_type

echo "Starting upgrade_d7_taxonomy_term_faq_topics"
drush migrate-import upgrade_d7_taxonomy_term_faq_topics

echo "Starting upgrade_d7_taxonomy_term_geographic_locations"
drush migrate-import upgrade_d7_taxonomy_term_geographic_locations

echo "Starting upgrade_d7_taxonomy_term_press_office"
drush migrate-import upgrade_d7_taxonomy_term_press_office

echo "Starting upgrade_d7_taxonomy_term_program_or_statute"
drush migrate-import upgrade_d7_taxonomy_term_program_or_statute

echo "Starting upgrade_d7_taxonomy_term_subject"
drush migrate-import upgrade_d7_taxonomy_term_subject

echo "Starting upgrade_d7_taxonomy_term_type"
drush migrate-import upgrade_d7_taxonomy_term_type

echo "Starting upgrade_d7_taxonomy_term_type_of_proposed_action"
drush migrate-import upgrade_d7_taxonomy_term_type_of_proposed_action
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
