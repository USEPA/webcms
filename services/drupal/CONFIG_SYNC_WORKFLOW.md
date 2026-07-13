# Drupal Config Sync and Module Removal Workflow

I wrote this down because I do not want us to repeat the deployment failure we hit when a module was removed from Composer but the Drupal configuration was not updated first.

In this project, config and code have to move together. If I remove a module package from the codebase but leave Drupal thinking that module is still installed, deployment can fail even though the code change itself looks small.

## The Failure Mode I Want to Avoid

The bad sequence looks like this:

1. Remove a contrib module from `services/drupal/composer.json` or `composer.lock`.
2. Commit the Composer change.
3. Deploy.

What happens next is predictable:

- the build creates a new image without that module's code
- the target environment still has active config and database state that expect the module to exist
- the deployment update step runs `drush deploy -y`
- Drupal bootstrap, config import, or deployment hooks can fail because the module was never cleanly uninstalled and exported

For this repo, that is especially important because the deployment pipeline ultimately runs Drupal maintenance commands after the container is updated. The code and the exported config must agree before I push the change.

## Rule of Thumb

If I am removing a module, I do **not** start with Composer.

I start by uninstalling the module in Drupal, then I export config, then I remove the package or custom code, then I validate locally, and only then do I commit and push.

## Important Paths in This Repo

- Active sync directory: `services/drupal/config/sync/`
- The installed module list is exported in: `services/drupal/config/sync/core.extension.yml`
- Drupal app root: `services/drupal/`

When a module is uninstalled correctly, I expect to see `core.extension.yml` change and I often expect to see related config files deleted from `services/drupal/config/sync/`.

## First Principle: Do Not Hand-Edit `core.extension.yml`

I do not manually remove a module from `core.extension.yml`.

I let Drupal do that by uninstalling the module properly, then I export config with Drush. This matters because uninstall can trigger cleanup that a manual file edit will never do.

The same rule applies to config overrides in `settings.php`: I do not try to force module state there either.

## What “Disable” Means Here

In casual conversation we say “disable the module,” but for deployment-safe Drupal config management what I actually want is a clean **uninstall**.

That is what removes the module from active configuration and lets the export capture the real state of the site.

## Safe Workflow for Removing a Contrib Module

Use this when the module was installed through Composer, for example `drupal/module_name`.

### 1. Start from current code and current config

From `services/drupal/`:

```bash
ddev start
ddev composer install
ddev drush cim -y
```

I want my local codebase and active config aligned before I start removing anything.

### 2. Uninstall the Drupal module first

Use the Drupal machine name, not the Composer package name:

```bash
ddev drush pm:uninstall module_name -y
```

If Drupal reports dependencies, I resolve those first. I do not force the removal by editing YAML or deleting code.

### 3. Export configuration immediately

```bash
ddev drush cex -y
```

At this point I expect to see:

- `services/drupal/config/sync/core.extension.yml` updated
- config owned by that module removed from `services/drupal/config/sync/`
- possibly related config changes if other modules depended on it

### 4. Remove the package from Composer

Only after uninstall and export:

```bash
ddev composer remove drupal/module_name
```

This updates:

- `services/drupal/composer.json`
- `services/drupal/composer.lock`

### 5. Validate locally

At minimum I run:

```bash
ddev drush cr
ddev drush deploy -y
ddev drush config:status
```

What I want:

- `drush deploy -y` completes successfully
- `drush config:status` shows no unexpected drift

If the site does not come back cleanly here, I do not push yet.

### 6. Review the diff before committing

For a clean module removal, I expect the diff to include the whole change set together:

- exported config changes under `services/drupal/config/sync/`
- `services/drupal/composer.json`
- `services/drupal/composer.lock`

If the Composer files changed but `core.extension.yml` did not, that is a red flag. I stop and fix the config before pushing.

### 7. Commit and push the full change set together

I commit the Composer changes and the exported config in the same branch/PR so deployment receives a consistent state.

## Safe Workflow for Removing a Custom Module

Use this when the module lives in the repo, usually under `services/drupal/web/modules/custom/`.

### 1. Uninstall the module

```bash
ddev drush pm:uninstall module_name -y
```

### 2. Export config

```bash
ddev drush cex -y
```

### 3. Remove the custom module code

Delete the module only after Drupal no longer considers it installed.

### 4. Validate locally

```bash
ddev drush cr
ddev drush deploy -y
ddev drush config:status
```

### 5. Commit config and code removal together

For custom modules, the commit should include both:

- config export changes
- deleted module code

## Safe Workflow for Removing a Core Module

For a core module, there is usually no Composer removal step because the code still ships with Drupal core.

The safe workflow is:

1. uninstall the module with Drush
2. export config
3. validate locally
4. commit and push the exported config

The most important file is still `services/drupal/config/sync/core.extension.yml`.

## What I Expect to See in the Diff

For a correct module removal, the diff usually tells the story clearly.

I expect some combination of:

- `services/drupal/config/sync/core.extension.yml`
- deleted config files the module owned
- config changes in entities that depended on the module
- `services/drupal/composer.json`
- `services/drupal/composer.lock`
- deleted custom module files, if it was a custom module

I do **not** want to see:

- only Composer changes with no config export
- manual edits to `core.extension.yml`
- removal of module code before uninstall/export

## Deployment Implications in This Repo

Two repo-specific points matter here:

1. **Composer changes require a full build**
   - This repo already treats `composer.json` and `composer.lock` changes as build-required changes.
   - If I remove a contrib module, I should not use a skip-build deployment path.

2. **Deployment runs Drupal maintenance commands after rollout**
   - The pipeline update step runs `drush deploy -y`.
   - That means the target environment has to receive code and config that agree with each other.

If I push Composer changes without the config export, I am creating the exact mismatch that caused the earlier deployment failure.

## Quick Review Checklist

Before I push a module removal, I want to be able to say yes to all of these:

- Did I uninstall the module in Drupal first?
- Did I export config with `ddev drush cex -y`?
- Did `services/drupal/config/sync/core.extension.yml` change?
- Did I commit the config export together with the Composer/code removal?
- Did I run `ddev drush deploy -y` locally?
- Did I confirm `ddev drush config:status` is clean?
- If Composer changed, am I using a full-build deploy path rather than `--skip-build`?

## Bottom Line

For this project, removing a module is not just a Composer change and it is not just a config change. It is both.

The safe sequence is:

1. uninstall the module in Drupal
2. export config
3. remove the package or code
4. validate locally
5. commit and push the full change together

That is the workflow I want us to follow so we do not break deployments by shipping code that no longer matches Drupal's exported configuration.
