# WebCMS Deployment Scripts Guide

This guide covers the deployment scripts available for pushing code changes to the development environment and triggering CI/CD pipelines.

## Quick Reference

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `push-dev.sh` | Standard deployment with auto-detection | Most deployments |
| `push-dev-updb-first.sh` | Custom drush order (updb before cim) | Schema/entity issues |
| `trigger-pipeline.sh` | Manual pipeline trigger | After manual git push |

## Standard Deployment: `push-dev.sh`

This is the go-to script for most development deployments. It automatically detects whether a full Docker build is needed based on the files you changed.

### Basic Usage

```bash
./scripts/push-dev.sh
```

This will:
1. Check that you're on the `development` branch
2. Analyze changed files to determine if a build is needed
3. Push your changes to GitHub
4. Trigger the GitLab pipeline

### Options

**Skip Build (Deploy Only)**
```bash
./scripts/push-dev.sh --skip-build
```
Use this when you only changed Drupal code/config but didn't modify Dockerfiles, composer dependencies, or theme assets. Reuses the existing Docker images.

**Force Build**
```bash
./scripts/push-dev.sh --force-build
```
Forces a full rebuild even if the script thinks it's not needed. Useful when you want to ensure everything is fresh.

**Combined with Git Options**
```bash
./scripts/push-dev.sh --skip-build -f  # Deploy-only with force push
```

### What Triggers a Build?

The script automatically detects builds are needed when you change:
- Dockerfiles
- composer.json/composer.lock files
- Theme package.json/package-lock files
- Theme source files (SCSS, JS)
- .gitlab-ci.yml

If you only changed custom modules, config, or templates, it skips the build.

## Custom Drush Order: `push-dev-updb-first.sh`

This script runs database updates **before** configuration import, which is the reverse of the default order.

### When to Use This

You need this when:
- Database schema changes must happen before config can be imported
- You're getting entity/schema errors during deployment
- A module update requires DB updates before config changes
- You're experiencing `TypeError` or `FieldDefinitionInterface` errors

### Usage

```bash
# Deploy current branch
./scripts/push-dev-updb-first.sh

# Deploy a specific branch
./scripts/push-dev-updb-first.sh development

# Deploy a feature branch
./scripts/push-dev-updb-first.sh feature/WMADC-5-my-fix
```

### What It Does

Instead of the default `drush deploy` order, it runs:
1. Enable maintenance mode
2. `drush updb -y` ← Database updates FIRST
3. `drush cim -y` ← Config import SECOND
4. `drush cr` ← Cache rebuild
5. Disable maintenance mode

The default `drush deploy` does config import before database updates, which can fail when config depends on schema changes.

## Manual Trigger: `trigger-pipeline.sh`

Use this when you've already pushed to GitHub manually and just need to trigger the pipeline.

### First-Time Setup

1. Create a GitLab Personal Access Token:
   - Go to https://gitlab.epa.gov/-/user_settings/personal_access_tokens
   - Token name: "WebCMS Pipeline Trigger"
   - Scopes: Check "api"
   - Copy the token

2. Add to your shell profile:
   ```bash
   # Add to ~/.bashrc or ~/.zshrc
   export GITLAB_TOKEN="glpat-xxxxxxxxxxxx"
   ```

3. Reload your shell or run:
   ```bash
   source ~/.bashrc
   ```

### Usage

```bash
# Trigger for current branch
./scripts/trigger-pipeline.sh

# Trigger for development
./scripts/trigger-pipeline.sh development

# Trigger for a feature branch
./scripts/trigger-pipeline.sh feature/WMADC-5-my-fix
```

### What It Does

1. Looks up the GitLab project ID
2. Syncs the GitHub mirror to GitLab
3. Waits for your commit to appear in GitLab
4. Triggers the pipeline for your branch

## Environment Variables

These can be set before running the scripts to customize behavior.

### SKIP_BUILD

Skip Docker image builds and deploy existing images only.

```bash
SKIP_BUILD=true bash ./scripts/trigger-pipeline.sh development
```

### DRUSH_ORDER

Run database updates before config import.

```bash
DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh development
```

You'll see this in the pipeline output:
```
⚠️  Using custom drush order: UPDB BEFORE CONFIG IMPORT
```

### GITLAB_TOKEN

Your GitLab Personal Access Token for API access.

```bash
export GITLAB_TOKEN="glpat-xxxxxxxxxxxx"
```

### Combining Variables

```bash
# Deploy-only with custom drush order
SKIP_BUILD=true DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh development
```

## Common Workflows

### Standard Code Change
```bash
git checkout development
git pull
# Make your changes
git add .
git commit -m "Fix: Something important"
./scripts/push-dev.sh
```

### Config-Only Change (No Build Needed)
```bash
# Make config changes
git add .
git commit -m "Update: Site configuration"
./scripts/push-dev.sh --skip-build
```

### Database Schema Change
```bash
# Make module/schema changes
git add .
git commit -m "Update: Add new entity field"
./scripts/push-dev-updb-first.sh
```

### Feature Branch Testing
```bash
git checkout feature/WMADC-5-my-feature
git push origin feature/WMADC-5-my-feature
DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh feature/WMADC-5-my-feature
```

### Already Pushed to GitHub
```bash
# Just trigger the pipeline
./scripts/trigger-pipeline.sh development

# Or with custom options
DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh development
```

## Troubleshooting

### "Must be on the development branch"

The `push-dev.sh` script only works on the `development` branch. For other branches, use `trigger-pipeline.sh` directly:

```bash
git push origin my-branch
bash ./scripts/trigger-pipeline.sh my-branch
```

### "GitLab token not provided"

You need to set up your `GITLAB_TOKEN` environment variable:

```bash
export GITLAB_TOKEN="your-token-here"
# Or add to ~/.bashrc for persistence
```

### "Permission denied"

Make the scripts executable:

```bash
chmod +x scripts/*.sh
```

### Pipeline Not Recognizing DRUSH_ORDER

Make sure you've pulled the latest changes that include the `ci/drush.js` updates. Check the pipeline logs for the warning message.

### Mirror Sync Taking Too Long

The script waits up to 5 minutes for the GitHub→GitLab mirror to sync. If it's stuck, you can manually sync at:
https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository

## Technical Details

### Build Detection Logic

The `push-dev.sh` script compares your local `HEAD` with `origin/development` to see what changed. It looks for patterns that indicate a build is needed:

- `composer.json`, `composer.lock`, `composer.patches.json`
- `services/drupal/Dockerfile`
- Theme `package.json`, `package-lock.json`
- Theme source files (`.scss`, `.js`)
- `.gitlab-ci.yml`

If none of these changed, it assumes a deploy-only is safe.

### Pipeline Variables

The scripts pass variables to the GitLab pipeline via the API:

- `SKIP_BUILD=true` → Skip Docker builds
- `DRUSH_ORDER=updb-first` → Custom drush command order

These are processed by `ci/drush.js` during the deployment.

### Script Locations

All scripts live in `scripts/`:
- `scripts/push-dev.sh`
- `scripts/push-dev-updb-first.sh`
- `scripts/trigger-pipeline.sh`

They expect to be run from the repository root.

## Related Documentation

- [Custom Drush Order Details](docs/CUSTOM_DRUSH_ORDER.md)
- [CI/CD Pipeline Documentation](ci/README.md)
- [Contributing Guidelines](CONTRIBUTING.md)
