#!/bin/bash

set -euo pipefail

# Function to build a single image from the Dockerfile in services/drupal
# Usage: build TARGET
# - TARGET: A Dockerfile target (cf. the "FROM ... AS <target>" lines)
#
# This function exploits the fact that the both the ECR repositories and Dockerfile targets
# are named according to the same scheme (the drupal target can be pushed to webcms-drupal).
function build() {
  local target="$1"

  # Full repo name
  local repo="webcms-$target-$WEBCMS_ENVIRONMENT"

  # Full tag (for docker push)
  local tag="$WEBCMS_REPO_URL/$repo:$WEBCMS_IMAGE_TAG"

  # Output status line for Buildkite
  echo "--- :docker: $target:$WEBCMS_IMAGE_TAG"

  docker build services/drupal \
    --target "$target" \
    --tag "$tag"

  docker push "$tag"
}

# Build the Drupal target first: in the Dockerfile, this will generate the contents of
# /var/www/html that we copy into the Nginx and Drush images.
build drupal

# Build nginx and Drush second. Each of these targets make heavy usage of the contents
# of Drupal's /var/www/html, so it's easier to perform this build in serial despite the
# lengthy I/O copying that occurs.
build nginx
build drush
