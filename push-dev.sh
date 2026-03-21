#!/bin/bash
# Wrapper script to push to development and automatically trigger GitLab pipeline
#
# Usage: ./push-dev.sh [options] [git push options]
# Examples:
#   ./push-dev.sh                    # Auto-detect if build is needed
#   ./push-dev.sh --skip-build       # Force deploy only (reuse existing images)
#   ./push-dev.sh --force-build      # Force full build even if not detected as necessary
#   ./push-dev.sh --skip-build -f    # Deploy only with force push

# Parse flags
SKIP_BUILD_FLAG="auto"
FORCE_BUILD_FLAG="false"

# Parse all flags before git push args
while [[ $1 == --* ]]; do
  case "$1" in
    --skip-build)
      SKIP_BUILD_FLAG="true"
      shift
      ;;
    --force-build)
      FORCE_BUILD_FLAG="true"
      shift
      ;;
    *)
      echo "❌ Unknown option: $1"
      echo "Valid options: --skip-build, --force-build"
      exit 1
      ;;
  esac
done

# Check if we're on the development branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$CURRENT_BRANCH" != "development" ]; then
  echo "❌ Error: You must be on the development branch to use this script"
  echo "Current branch: $CURRENT_BRANCH"
  echo ""
  echo "To push other branches, use: git push"
  exit 1
fi

# Function to check if files requiring a build have changed
check_build_required() {
  # Get the list of changed files in this push
  # Compare local HEAD with remote origin/development
  local remote_ref="origin/development"
  
  # Check if remote ref exists
  if ! git rev-parse "$remote_ref" >/dev/null 2>&1; then
    echo "⚠️  Warning: Cannot find $remote_ref - assuming build is required"
    return 0
  fi
  
  # Get changed files
  local changed_files=$(git diff --name-only "$remote_ref"...HEAD)
  
  if [ -z "$changed_files" ]; then
    echo "⚠️  No file changes detected - assuming build is required"
    return 0
  fi
  
  echo "📋 Changed files:"
  echo "$changed_files" | sed 's/^/   /'
  echo ""
  
  # Define patterns that require a full build
  # These are files that affect Composer dependencies, npm builds, or Docker images
  local build_patterns=(
    "^composer\.json$"
    "^composer\.lock$"
    "^composer\.patches\.json$"
    "^services/drupal/composer\.json$"
    "^services/drupal/composer\.lock$"
    "^services/drupal/composer\.patches\.json$"
    "^services/drupal/Dockerfile$"
    "^services/.*/Dockerfile$"
    "^services/drupal/patches/"
    "^services/drupal/scripts/"
    "^services/drupal/web/themes/epa_theme/package\.json$"
    "^services/drupal/web/themes/epa_theme/package-lock\.json$"
    "^services/drupal/web/themes/epa_theme/.*\.(scss|js)$"
    "^services/drupal/web/themes/epa_theme/source/"
    "^services/drupal/web/themes/epa_theme/gulp"
    "^services/drupal/web/themes/epa_claro/"
    "^docker-compose"
    "^\.gitlab-ci\.yml$"
  )
  
  # Check each changed file against build patterns
  while IFS= read -r file; do
    for pattern in "${build_patterns[@]}"; do
      if echo "$file" | grep -qE "$pattern"; then
        echo "🔨 Build REQUIRED - detected change in: $file"
        echo "   (matches pattern: $pattern)"
        return 0
      fi
    done
  done <<< "$changed_files"
  
  # If we get here, no build-requiring files were changed
  echo "✅ Build NOT required - changes are deployment-only"
  echo "   (custom modules, config, templates, etc.)"
  return 1
}

# Determine if build is needed
BUILD_REQUIRED="true"

if [ "$FORCE_BUILD_FLAG" == "true" ]; then
  echo "🔨 FORCE BUILD mode - will perform full build regardless of changes"
  BUILD_REQUIRED="true"
elif [ "$SKIP_BUILD_FLAG" == "true" ]; then
  echo "⚡ SKIP BUILD mode (forced) - will deploy without rebuilding Docker images"
  echo "   (reusing existing :development-latest images)"
  BUILD_REQUIRED="false"
elif [ "$SKIP_BUILD_FLAG" == "auto" ]; then
  echo "🔍 Auto-detecting if build is required..."
  echo ""
  
  if check_build_required; then
    BUILD_REQUIRED="true"
  else
    BUILD_REQUIRED="false"
  fi
fi

echo ""
echo "📤 Pushing development branch to GitHub..."
echo ""

# Push to GitHub with any provided arguments
if git push "$@"; then
  echo ""
  echo "✅ Push successful!"
  echo ""
  
  # Trigger the GitLab pipeline with SKIP_BUILD variable if needed
  if [ -f "./trigger-pipeline.sh" ]; then
    if [ "$BUILD_REQUIRED" == "false" ]; then
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
