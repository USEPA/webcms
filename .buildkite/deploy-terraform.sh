#!/bin/sh

set -eu

cd infrastructure/terraform

terraform init -input=false -backend-config=backend.config

# Perform a plan using a file - this file can be inspected later for changes that may have
# been applied to the infrastructure.
terraform plan \
  -input=false \
  -out=out.plan \
  -var image-tag-nginx="$WEBCMS_IMAGE_TAG" \
  -var image-tag-drupal="$WEBCMS_IMAGE_TAG" \
  -var image-tag-drush="$WEBCMS_IMAGE_TAG"

terraform apply -input=false out.plan

# Capture the Drush AWSVPC task configuration for the Drush update step
terraform output drush-task-config > drushvpc.json
