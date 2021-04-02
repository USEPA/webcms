#!/bin/sh

set -eu

# This script runs the terraform fmt command in each module directory under "terraform" in
# the repository. We run this in a loop because it is not particularly expensive to run
# these diffs, and spawning multiple Buildkite agent jobs would just be a waste of
# resources.

# Sentinel variable to determine if any formatting checks failed.
failed=

# Loop over each module in the Terraform directory
for dir in terraform/*; do
  # Skip non-directory entries (for example, this skips terraform/README.md).
  if ! test -d "$dir"; then
    continue
  fi

  # Section output by module. Use basename to display just the module name ("foo" instead
  # of "terraform/foo").
  #
  # cf. https://buildkite.com/docs/pipelines/managing-log-output#collapsing-output
  echo "--- :terraform: $(basename "$dir")"

  if ! terraform -chdir="$dir" fmt -diff -check; then
    # If a formatting check failed, open the collapsed section and indicate this job
    # should fail. We don't exit automatically because we want to check modules that may
    # be behind us in line, and it's better to output all errors instead of only some.
    echo "^^^ +++"
    failed=1
  fi
done

# If $failed is a non-empty value, that means an fmt check failed and we should fail this
# job.
if test -n "$failed"; then
  exit 1
fi
