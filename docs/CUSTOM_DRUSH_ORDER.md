# Custom Drush Command Ordering for Deployments

## Overview

By default, WebCMS deployments use `drush deploy`, which runs configuration import **before** database updates. In some cases, you may need to run database updates (`drush updb`) **before** configuration import (`drush cim`).

This document explains how to trigger a one-off deployment with custom drush command ordering.

## Default Deployment Order

The standard deployment runs:
```bash
drush sql:query "REPLACE INTO key_value ..."  # Enable maintenance mode
drush deploy -y                                # Runs: cim, updb, cache rebuild
drush sql:query "REPLACE INTO key_value ..."  # Disable maintenance mode
```

Note: `drush deploy` executes config import **first**, then database updates.

## Custom Order: UPDB First

When you need database updates to run **before** config import, you can use the custom ordering:

```bash
drush sql:query "REPLACE INTO key_value ..."  # Enable maintenance mode
drush updb -y                                  # Database updates FIRST
drush cim -y                                   # Config import SECOND
drush cr                                       # Cache rebuild
drush sql:query "REPLACE INTO key_value ..."  # Disable maintenance mode
```

## How to Deploy with Custom Order

### Option 1: Using the Helper Script (Easiest)

From the repository root:

```bash
# Deploy current branch
./scripts/push-dev-updb-first.sh

# Deploy specific branch
./scripts/push-dev-updb-first.sh development
./scripts/push-dev-updb-first.sh WEBCMS-81-group-update-0928

# Force push
./scripts/push-dev-updb-first.sh development -f
```

This script will:
1. Verify/checkout the target branch
2. Push your changes to GitHub
3. Trigger the GitLab pipeline with `DRUSH_ORDER=updb-first`

### Option 2: Manual Trigger

If you've already pushed to GitHub and just need to trigger the pipeline:

```bash
# Trigger specific branch
DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh development
DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh WEBCMS-81-group-update-0928

# Trigger current branch
DRUSH_ORDER=updb-first bash ./scripts/trigger-pipeline.sh
```

### Option 3: GitLab UI

1. Go to the GitLab project pipeline trigger page
2. Select your target branch (development, staging, feature branch, etc.)
3. Add a variable:
   - **Key**: `DRUSH_ORDER`
   - **Value**: `updb-first`
4. Click "Run pipeline"

## When to Use Custom Order

Use `updb-first` when:

- You have database schema changes that must be applied before config imports
- Config depends on new database columns/tables that will be created by updates
- You're experiencing errors during standard deployment due to config/schema mismatches
- A module update requires database updates before configuration changes

## Important Notes

⚠️ **This is for one-off deployments only**. The custom order bypasses the carefully designed `drush deploy` command, which includes additional safety checks and optimizations.

⚠️ **Use with caution**. While this feature works on any branch, use it judiciously. The standard `drush deploy` order is correct for most deployments. Only use custom ordering when you have a specific technical requirement (like the Group 2.x update).

⚠️ **Pipeline variable is temporary**. The `DRUSH_ORDER` variable only applies to the specific pipeline run you trigger. Subsequent deployments will use the default order unless you set the variable again.

⚠️ **Feature branches**. You can deploy feature branches directly without merging to development first. This is useful for testing complex updates like the Group 2.x migration on the WEBCMS-81 branch.

## Troubleshooting

### Pipeline doesn't recognize DRUSH_ORDER

- Ensure you've pulled the latest changes from the repository
- Verify the changes to `ci/drush.js` and `scripts/trigger-pipeline.sh` are present
- Check the pipeline logs for the warning: "⚠️  Using custom drush order: UPDB BEFORE CONFIG IMPORT"

### Script permission denied

Make the script executable:
```bash
chmod +x scripts/push-dev-updb-first.sh
```

### Wrong branch error

If the script detects you're on a different branch than the one you're targeting, it will prompt you to checkout the correct branch. Either:
- Checkout the branch first: `git checkout WEBCMS-81-group-update-0928`
- Or specify the branch as an argument: `./scripts/push-dev-updb-first.sh WEBCMS-81-group-update-0928`

## Technical Details

The implementation uses three components:

1. **`ci/drush.js`**: Checks for `DRUSH_ORDER` environment variable and selects the appropriate drush script
2. **`scripts/trigger-pipeline.sh`**: Passes `DRUSH_ORDER` variable to GitLab API when triggering pipelines
3. **`scripts/push-dev-updb-first.sh`**: Convenience wrapper that sets `DRUSH_ORDER=updb-first` automatically

## See Also

- [`ci/README.md`](../ci/README.md) - General CI/CD drush automation documentation
- [`scripts/push-dev.sh`](../scripts/push-dev.sh) - Standard deployment script
- [`CONTRIBUTING.md`](../CONTRIBUTING.md) - General contribution guidelines
