# WebCMS Development Deployment Workflow

This document describes the complete workflow for deploying code changes to the development environment.

## Architecture Overview

```
GitHub (USEPA/webcms)
    ↓ (mirror sync every 30 min or manual)
GitLab (drupalcloud/drupalclouddeployment)
    ↓ (CI/CD pipeline)
AWS ECS (dev environment)
```

## Your Workflow: GitHub → GitLab → AWS

### Step 1: Push to GitHub (Local → GitHub)
```bash
# Make your changes locally
git add .
git commit -m "Your change description"
git push origin development
```

### Step 2: Sync GitLab Mirror (GitHub → GitLab)

#### Option A: Wait for Auto-Sync (30 minutes)
- GitLab automatically pulls from GitHub every 30 minutes
- **No action needed** - just wait

#### Option B: Manual Sync (Immediate)
1. Go to: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository
2. Scroll to **"Mirroring repositories"** section
3. Find the GitHub mirror entry
4. Click **"Update now"** button (🔄)
5. Wait for sync to complete (~10-30 seconds)

### Step 3: Trigger Pipeline (GitLab → AWS)

After the mirror is synced, trigger the deployment pipeline:

```bash
./trigger-pipeline.sh
```

**What this does:**
- Looks up the GitLab project ID
- Triggers a pipeline on the `development` branch
- Shows you the pipeline URL to track progress

### Step 4: Monitor Deployment

Click the pipeline URL from the script output, or go to:
https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines

**Expected pipeline duration:** ~10-15 minutes

**Pipeline stages:**
1. ✅ **Build** (5-8 min) - Build Docker images for dev site
2. ✅ **Deploy:Dev:Init** (30 sec) - Initialize Terraform
3. ✅ **Deploy:Dev:Validate** (30 sec) - Validate configuration
4. ✅ **Deploy:Dev:Plan** (1 min) - Plan infrastructure changes
5. ✅ **Deploy:Dev:Apply** (3-5 min) - Deploy to AWS ECS (automatic)

## One-Time Setup

### Create GitLab Personal Access Token

1. Go to: https://gitlab.epa.gov/-/user_settings/personal_access_tokens
2. Fill in:
   - **Token name:** "WebCMS Pipeline Trigger"
   - **Scopes:** ✅ `api` (full API access)
3. Click **"Create personal access token"**
4. **Copy the token** (you won't see it again!)

### Set Environment Variable

Add to your shell profile (`~/.bashrc` or `~/.bash_profile`):
```bash
export GITLAB_TOKEN="your-token-here"
```

Then reload:
```bash
source ~/.bashrc
```

Or for one-time use:
```bash
export GITLAB_TOKEN="your-token-here"
./trigger-pipeline.sh
```

## Complete Example Workflow

```bash
# 1. Make changes and push to GitHub
git add .
git commit -m "Fix: Update homepage layout"
git push origin development

# 2. Manually sync GitLab mirror
# Go to: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository
# Click "Update now" for the GitHub mirror

# 3. Trigger deployment pipeline
./trigger-pipeline.sh

# Output:
# 🚀 Triggering GitLab Pipeline for Development
# =============================================
# Project: drupalcloud/drupalclouddeployment
# Branch: development (development only)
# ...
# ✅ Pipeline triggered successfully!
# Pipeline URL: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines/12345

# 4. Monitor pipeline progress
# Click the URL or check: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines
```

## Why This Process?

### GitHub as Source of Truth
- GitHub hosts the primary repository
- All development work happens here
- Version control and history maintained

### GitLab Mirror for CI/CD
- GitLab has the infrastructure runners
- Manages deployments to AWS
- Syncs from GitHub automatically

### Manual Pipeline Trigger
- Mirror sync doesn't auto-trigger pipelines (permission restriction)
- Manual trigger gives you control over deployments
- Prevents accidental deployments from auto-syncs

## Troubleshooting

### Mirror Not Syncing
- Check if you have maintainer/owner access to GitLab project
- Verify GitHub repository is accessible
- Contact GitLab admin if sync is broken

### Pipeline Trigger Fails
**Error: Invalid token or insufficient permissions**
- Token may be expired - create a new one
- Token needs `api` scope
- Verify you have Developer+ access to GitLab project

**Error: Branch 'development' doesn't exist**
- Mirror sync may not have completed
- Verify branch exists in GitLab
- Wait a few minutes and try again

**Error: Pipeline is already running**
- Check existing pipelines: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines
- Wait for current pipeline to finish
- Cancel old pipeline if stuck

### Deployment Fails
Check the pipeline logs:
1. Go to the pipeline URL
2. Click on the failed job
3. Review error messages
4. Common issues:
   - AWS credentials expired
   - Terraform state locked
   - Docker build errors
   - Resource conflicts

## Alternative: Auto-Trigger on Mirror Update

If you get repository admin access, you can enable automatic pipeline triggers:

1. Go to: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository
2. Find "Mirroring repositories"
3. Edit the GitHub mirror
4. Enable: ✅ **"Trigger pipelines for mirror updates"**
5. Save

This will automatically trigger pipelines whenever the mirror syncs (every 30 min or on manual sync).

## Questions & Support

- **GitLab Pipeline Issues:** Check CI/CD Analytics in GitLab
- **Deployment Issues:** Review pipeline logs and Terraform output
- **Access Issues:** Contact GitLab/AWS administrators
- **Code Issues:** Review GitHub pull requests and test locally

## Quick Reference

| Action | URL |
|--------|-----|
| Push code | `git push origin development` |
| Sync mirror | https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository |
| View pipelines | https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines |
| Create token | https://gitlab.epa.gov/-/user_settings/personal_access_tokens |
| Trigger script | `./trigger-pipeline.sh` |
