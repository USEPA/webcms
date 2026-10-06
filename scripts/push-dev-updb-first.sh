#!/bin/bash
# Wrapper script for deployments with UPDB before config import
#
# Usage: ./push-dev-updb-first.sh [branch] [git push options]
# Examples:
#   ./push-dev-updb-first.sh                           # Current branch
#   ./push-dev-updb-first.sh development               # Specific branch
#   ./push-dev-updb-first.sh WEBCMS-81-group-update    # Feature branch
#   ./push-dev-updb-first.sh development -f            # Force push
#
# This script deploys with custom drush command ordering:
# 1. Enable maintenance mode
# 2. drush updb -y         (database updates FIRST)
# 3. drush cim -y          (config import SECOND)
# 4. drush cr              (cache rebuild)
# 5. Disable maintenance mode
#
# Instead of the default `drush deploy` which runs config import before updb.

# Parse branch argument (optional first argument)
if [[ $1 != -* ]] && [ -n "$1" ]; then
  TARGET_BRANCH="$1"
  shift  # Remove branch from arguments
else
  # Use current branch if not specified
  TARGET_BRANCH=$(git rev-parse --abbrev-ref HEAD)
fi

# Verify we're on the target branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$CURRENT_BRANCH" != "$TARGET_BRANCH" ]; then
  echo "⚠️  Warning: You are on branch '$CURRENT_BRANCH' but targeting '$TARGET_BRANCH'"
  echo ""
  read -p "Do you want to checkout '$TARGET_BRANCH' first? (y/n) " -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    git checkout "$TARGET_BRANCH" || exit 1
  else
    echo "❌ Aborted. Please checkout the correct branch first."
    exit 1
  fi
fi

echo "⚠️  CUSTOM DRUSH ORDER DEPLOYMENT"
echo "=================================="
echo "Branch: $TARGET_BRANCH"
echo ""
echo "This deployment will run drush commands in custom order:"
echo "  1. Enable maintenance mode"
echo "  2. drush updb -y  (FIRST - database updates)"
echo "  3. drush cim -y   (SECOND - config import)"
echo "  4. drush cr       (cache rebuild)"
echo "  5. Disable maintenance mode"
echo ""
echo "📤 Pushing $TARGET_BRANCH branch to GitHub..."
echo ""

# Push to GitHub with any provided arguments
if git push "$@"; then
  echo ""
  echo "✅ Push successful!"
  echo ""
  
  # Trigger the GitLab pipeline with custom DRUSH_ORDER variable
  if [ -f "./scripts/trigger-pipeline.sh" ]; then
    echo "🚀 Triggering pipeline for branch '$TARGET_BRANCH' with DRUSH_ORDER=updb-first..."
    DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh "$TARGET_BRANCH"
  else
    echo "⚠️  Warning: scripts/trigger-pipeline.sh not found"
    echo ""
    echo "To manually trigger with custom drush order, run:"
    echo "  DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh $TARGET_BRANCH"
  fi
else
  echo ""
  echo "❌ Push failed - not triggering pipeline"
  exit 1
fi
