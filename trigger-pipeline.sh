#!/bin/bash
# Script to manually trigger GitLab pipeline for WebCMS development deployments
#
# Usage after pushing to GitHub:
# 1. Go to GitLab: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository
# 2. Find "Mirroring repositories" section
# 3. Click "Update now" button next to the GitHub mirror
# 4. Run this script: ./trigger-pipeline.sh
#
# One-time Setup:
# 1. Create a Personal Access Token in GitLab:
#    - Go to: https://gitlab.epa.gov/-/user_settings/personal_access_tokens
#    - Token name: "WebCMS Pipeline Trigger"
#    - Scopes: Select "api" (full API access)
#    - Click "Create personal access token"
#    - Copy the token (you won't see it again!)
#
# 2. Set your token as an environment variable (add to ~/.bashrc for persistence):
#    export GITLAB_TOKEN="your-token-here"

# Default to development branch (hardcoded for safety)
BRANCH="${1:-development}"
GITLAB_TOKEN="${2:-$GITLAB_TOKEN}"

# Only allow development branch
if [ "$BRANCH" != "development" ]; then
  echo "❌ Error: This script only triggers pipelines for the development branch"
  echo "Current branch: $BRANCH"
  exit 1
fi

# EPA GitLab Configuration
GITLAB_URL="https://gitlab.epa.gov"
PROJECT_PATH="drupalcloud/drupalclouddeployment"

if [ -z "$GITLAB_TOKEN" ]; then
  echo "❌ Error: GitLab token not provided"
  echo ""
  echo "Usage: $0 [gitlab-token]"
  echo ""
  echo "Setup Instructions:"
  echo "1. Create a Personal Access Token:"
  echo "   https://gitlab.epa.gov/-/user_settings/personal_access_tokens"
  echo ""
  echo "2. Set as environment variable:"
  echo "   export GITLAB_TOKEN='your-token-here'"
  echo ""
  echo "3. Sync the GitLab mirror manually:"
  echo "   https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository"
  echo "   Click 'Update now' next to the GitHub mirror"
  echo ""
  echo "4. Run this script:"
  echo "   ./trigger-pipeline.sh"
  exit 1
fi

echo "🚀 Triggering GitLab Pipeline for Development"
echo "============================================="
echo "Project: $PROJECT_PATH"
echo "Branch: $BRANCH (development only)"
echo "GitLab: $GITLAB_URL"
echo ""
echo "⚠️  Make sure you synced the mirror first!"
echo "   https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository"
echo ""

# Step 1: Get project ID from project path
echo "📡 Looking up project ID..."
PROJECT_INFO=$(curl -s --header "PRIVATE-TOKEN: ${GITLAB_TOKEN}" \
  "${GITLAB_URL}/api/v4/projects/$(echo $PROJECT_PATH | sed 's/\//%2F/g')")

PROJECT_ID=$(echo "$PROJECT_INFO" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$PROJECT_ID" ]; then
  echo "❌ Failed to find project: $PROJECT_PATH"
  echo ""
  echo "Possible issues:"
  echo "  - Invalid token or insufficient permissions"
  echo "  - Project path is incorrect"
  echo "  - You don't have access to this project"
  echo ""
  echo "Response from GitLab:"
  echo "$PROJECT_INFO" | head -n 5
  exit 1
fi

echo "✓ Found project ID: $PROJECT_ID"
echo ""

# Step 2: Trigger the pipeline
echo "🔨 Triggering pipeline..."
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  "${GITLAB_URL}/api/v4/projects/${PROJECT_ID}/pipeline?ref=${BRANCH}" \
  --header "PRIVATE-TOKEN: ${GITLAB_TOKEN}")

HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "201" ]; then
  echo "✅ Pipeline triggered successfully!"
  echo ""
  PIPELINE_ID=$(echo "$BODY" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
  PIPELINE_URL=$(echo "$BODY" | grep -o '"web_url":"[^"]*"' | cut -d'"' -f4)
  
  echo "Pipeline ID: $PIPELINE_ID"
  echo "Pipeline URL: $PIPELINE_URL"
  echo ""
  echo "🔍 View pipeline at:"
  echo "   $PIPELINE_URL"
elif [ "$HTTP_CODE" = "400" ]; then
  echo "❌ Failed to trigger pipeline (HTTP $HTTP_CODE)"
  echo ""
  echo "Possible issues:"
  echo "  - Branch '$BRANCH' doesn't exist"
  echo "  - Pipeline is already running for this branch"
  echo "  - CI/CD is disabled for this project"
  echo ""
  echo "Response:"
  echo "$BODY"
  exit 1
elif [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "403" ]; then
  echo "❌ Authentication failed (HTTP $HTTP_CODE)"
  echo ""
  echo "Your token may be:"
  echo "  - Invalid or expired"
  echo "  - Missing 'api' scope"
  echo "  - Not authorized for this project"
  echo ""
  echo "Create a new token at:"
  echo "  https://gitlab.epa.gov/-/user_settings/personal_access_tokens"
  exit 1
else
  echo "❌ Failed to trigger pipeline (HTTP $HTTP_CODE)"
  echo ""
  echo "Response:"
  echo "$BODY"
  exit 1
fi
