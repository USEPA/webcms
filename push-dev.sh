#!/bin/bash
# Wrapper script to push to development and automatically trigger GitLab pipeline
#
# Usage: ./push-dev.sh [options] [git push options]
# Examples:
#   ./push-dev.sh                    # Full build + deploy
#   ./push-dev.sh --skip-build       # Deploy only (reuse existing images)
#   ./push-dev.sh --skip-build -f    # Deploy only with force push

# Parse --skip-build flag
SKIP_BUILD_FLAG="false"
if [ "$1" == "--skip-build" ]; then
  SKIP_BUILD_FLAG="true"
  shift  # Remove --skip-build from arguments
fi

# Check if we're on the development branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$CURRENT_BRANCH" != "development" ]; then
  echo "❌ Error: You must be on the development branch to use this script"
  echo "Current branch: $CURRENT_BRANCH"
  echo ""
  echo "To push other branches, use: git push"
  exit 1
fi

if [ "$SKIP_BUILD_FLAG" == "true" ]; then
  echo "⚡ SKIP_BUILD mode enabled - will deploy without rebuilding Docker images"
  echo "   (reusing existing :development-latest images)"
  echo ""
fi

echo "📤 Pushing development branch to GitHub..."
echo ""

# Push to GitHub with any provided arguments
if git push "$@"; then
  echo ""
  echo "✅ Push successful!"
  echo ""
  
  # Trigger the GitLab pipeline with SKIP_BUILD variable if needed
  if [ -f "./trigger-pipeline.sh" ]; then
    if [ "$SKIP_BUILD_FLAG" == "true" ]; then
      echo "🚀 Triggering DEPLOY-ONLY pipeline (skipping Docker builds)..."
      SKIP_BUILD=true bash ./trigger-pipeline.sh "development"
    else
      echo "🚀 Triggering FULL BUILD pipeline..."
      bash ./trigger-pipeline.sh "development"
    fi
  else
    echo "⚠️  Warning: trigger-pipeline.sh not found in current directory"
  fi
else
  echo ""
  echo "❌ Push failed - not triggering pipeline"
  exit 1
fi
