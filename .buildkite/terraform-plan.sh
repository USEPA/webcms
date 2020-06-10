#!/bin/sh

set -eu

# This script performs a Terraform plan, outputting the plan as "out.plan" on disk.
# We have broken this script up into separate steps in order to allow non-deploy builds
# to see what (if any) Terraform changes would occur.
#
# Builds on a deployment branch follow this step with the terraform-apply.sh script.

# Enter the Terraform directory and download plugins
cd infrastructure/terraform
terraform init -input=false -backend-config=backend.config

terraform workspace select "$WEBCMS_WORKSPACE"

# Perform a plan using a file - this file can be inspected later for changes that may have
# been applied to the infrastructure.
terraform plan \
  -input=false \
  -out=out.plan \
  -var image-tag-nginx="$WEBCMS_IMAGE_TAG" \
  -var image-tag-drupal="$WEBCMS_IMAGE_TAG" \
  -var image-tag-drush="$WEBCMS_IMAGE_TAG"
