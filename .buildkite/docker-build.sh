#!/bin/bash

set -exuo pipefail

tag="$BUILDKITE_BRANCH-$BUILDKITE_BUILD_NUMBER"
image_name="$DOCKER_REPOSITORY:$tag"

docker build -t "$image_name" services/drupal

# Only push this image if not a PR
if test "$BUILDKITE_PULL_REQUEST" = "false"; then
  docker push "$image_name"
fi