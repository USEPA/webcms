#!/bin/bash

set -euo pipefail

# Create a Kaniko configuration file. This is needed for Kaniko to auto-push to ECR; see
# https://github.com/GoogleContainerTools/kaniko#pushing-to-amazon-ecr for more details.
echo '{"credsStore":"ecr-login"}' >kaniko-config.json

# We build three targets from the services/drupal directory. This order mimics the order
# of stages in the Dockerfile there.
readonly -a drupal_targets=(
  drupal
  nginx
  drush
)

# For each target, perform a Kaniko-powered build. A few notes:
#
# 1. We bind two volumes to the Kaniko container: services/drupal is bound to the
#    workspace, and we share the JSON configuration file from
#
# 2. We forward AWS authentication values to the running container. When combined with the
#    configuration file, this allows Kaniko to communicate with ECR without needing a
#    Docker login on the host.
#
# 3. The Kaniko build uses a few parameters to speed up builds:
#    1. Stages not relevant to a target are skipped.
#    2. Caching is enabled. Intermediate layers are pushed to the cache repository.
#       Subsequent builds can query the cache repo instead of re-executing expensive build
#       steps.
#    3. The snapshot mode is set to "redo" instead of "full". This uses cheaper but less
#       accurate metadata for FS snapshots. We assume this won't cause any issues for us,
#       as any changes in composer.json will have an outsized change in the contents of
#       the vendor and contrib directories, which should sufficiently disrupt the redo
#       snapshot information.
#    4. We use Kaniko's (experimental) system to determine if a RUN command needs to be
#       re-run. Allegedly this improves build performance by a significant degree.
#
# See the Kaniko README for more details:
# https://github.com/GoogleContainerTools/kaniko#readme
for target in "${drupal_targets[@]}"; do
  echo "--- :docker: Build $target"

  docker run \
      --rm \
      --tty \
      --interactive \
      --volume "$PWD/services/drupal:/workspace" \
      --volume "$PWD/kaniko-config.json:/kaniko/.docker/config.json" \
      --env AWS_ACCESS_KEY_ID \
      --env AWS_SECRET_ACCESS_KEY \
      --env AWS_SESSION_TOKEN \
    gcr.io/kaniko-project/executor:latest \
      --context=/workspace \
      --cache \
      --cache-repo="$WEBCMS_REPO_URL/webcms-$WEBCMS_ENVIRONMENT-cache" \
      --skip-unused-stages \
      --snapshotMode=redo \
      --use-new-run \
      --target="$target" \
      --destination="$WEBCMS_REPO_URL/webcms-$WEBCMS_ENVIRONMENT-$WEBCMS_SITE-$target:$WEBCMS_IMAGE_TAG"
done

# Additionally, we perform a build of the metrics sidecar in services/metrics. The build
# here is much less involved (it's just Alpine plus some scripts), so we use less
# aggressive cache settings for Kaniko. See the previous comment block for the flags
# passed to Docker and Kaniko.

echo "--- :docker: Build fpm-metrics"

docker run \
    --rm \
    --tty \
    --interactive \
    --volume "$PWD/services/metrics:/workspace" \
    --volume "$PWD/kaniko-config.json:/kaniko/.docker/config.json" \
    --env AWS_ACCESS_KEY_ID \
    --env AWS_SECRET_ACCESS_KEY \
    --env AWS_SESSION_TOKEN \
  gcr.io/kaniko-project/executor:latest \
    --context=/workspace \
    --destination="$WEBCMS_REPO_URL/webcms-$WEBCMS_ENVIRONMENT-$WEBCMS_SITE-fpm-metrics:$WEBCMS_IMAGE_TAG"
