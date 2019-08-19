#!/bin/bash

set -exuo pipefail

eval "$(aws --debug ecr get-login --region=us-east-1 --no-include-email)"

tag="$BUILDKITE_BRANCH-$BUILDKITE_BUILD_NUMBER"
image_name="$DOCKER_REPOSITORY:$tag"

docker build -t "$image_name" services/drupal

if test "$BUILDKITE_PULL_REQUEST" != "false"; then
  docker push "$image_name"
fi