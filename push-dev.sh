#!/bin/bash
# Wrapper script to push to development and automatically trigger GitLab pipeline
#
# Usage: ./push-dev.sh [git push options]
# Example: ./push-dev.sh
# Example: ./push-dev.sh -f (force push)

# Check if we're on the development branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$CURRENT_BRANCH" != "development" ]; then
  echo "❌ Error: You must be on the development branch to use this script"
  echo "Current branch: $CURRENT_BRANCH"
  echo ""
  echo "To push other branches, use: git push"
  exit 1
fi

echo "📤 Pushing development branch to GitHub..."
echo ""

# Push to GitHub with any provided arguments
if git push "$@"; then
  echo ""
  echo "✅ Push successful!"
  echo ""
  
  # Trigger the GitLab pipeline
  if [ -f "./trigger-pipeline.sh" ]; then
    bash ./trigger-pipeline.sh "development"
  else
    echo "⚠️  Warning: trigger-pipeline.sh not found in current directory"
  fi
else
  echo ""
  echo "❌ Push failed - not triggering pipeline"
  exit 1
fi
