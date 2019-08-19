#!/bin/bash

set -euo pipefail

CF_DOCKER_PASSWORD="$(aws --region=us-east-1 ecr get-authorization-token --output=text --query='authorizationData[].authorizationToken' | base64 --decode)"
export CF_DOCKER_PASSWORD

set -x

tag="$BUILDKITE_BRANCH-$BUILDKITE_BUILD_NUMBER"
image_name="$DOCKER_REPOSITORY:$tag"

curl -Ls 'https://packages.cloudfoundry.org/stable?release=linux64-binary&source=github' > \
  cf-cli.tgz

tar xzf cf-cli.tgz cf

./cf api 'https://api.fr.cloud.gov'
./cf target -o epa-prototyping -s webcms-proto
./cf auth

./cf push webcms-proto --docker-username AWS --docker-image "$image_name"

./cf logout