#!/bin/bash

set -euo pipefail

if test "$BUILDKITE_PULL_REQUEST" != "false"; then
  echo 'Skipping deployment on pull requests'
fi

CF_DOCKER_PASSWORD="$(aws --region=us-east-1 ecr get-authorization-token --output=text --query='authorizationData[].authorizationToken' | base64 --decode | cut -d: -f2)"
export CF_DOCKER_PASSWORD

# Only log commands after we've obtained authorization (or else the console will see the token)
set -x

tag="$BUILDKITE_BRANCH-$BUILDKITE_BUILD_NUMBER"
image_name="$DOCKER_REPOSITORY:$tag"

curl -Ls 'https://packages.cloudfoundry.org/stable?release=linux64-binary&source=github' > \
  cf-cli.tgz

tar xzf cf-cli.tgz cf

./cf api 'https://api.fr.cloud.gov'
./cf auth

./cf target -o epa-prototyping -s webcms-proto
./cf push webcms-proto --docker-username AWS --docker-image "$image_name"

# Log out to ensure authorization tokens are destroyed
./cf logout