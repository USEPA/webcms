Closes [WEBCMS-XXXX](https://jira.epa.gov/browse/WEBCMS-XXXX)

<!-- Every PR references its Jira issue (project key WEBCMS) — Jira is the
     system of record for tracked work. Replace WEBCMS-XXXX above with the real
     issue key so it links automatically. -->


## Description

A clear and concise description of the PR.

Use this section for review hints, explanations, or discussion points/todos.

- Summary of changes
- Reasoning / higher-level goal
- Additional context

## Type of Change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Configuration change (content types, fields, permissions, workflow)
- [ ] Theme / front-end change
- [ ] CI/CD, infrastructure, or deployment change
- [ ] Documentation update
- [ ] Breaking change (fix or feature that would change existing behavior)

## Target Branch

- [ ] `development` (feature/bugfix work)
- [ ] `main` (hotfix only — see [Git Workflow](../docs/GIT_WORKFLOW.md#hotfix-flow))

## Testing & Evidence

Describe how you validated this change. A reviewer should be able to see that the
work was tested before it reached them.

- How it was manually tested:
- Commands run (e.g. `ddev drush deploy -y`, `ddev drush config:status`):

## Screenshots

Screenshots or a screen recording of the visual changes associated with this PR.

(Feel free to delete this section for non-visual changes.)

## Configuration & Deployment

- [ ] Configuration was exported (`ddev drush cex`) and committed with the code
- [ ] Module add/remove followed the [Config Sync Workflow](../services/drupal/CONFIG_SYNC_WORKFLOW.md)
- [ ] This change requires a full build (Composer/theme/Docker/CI changes) — if so, note it here
- [ ] No secrets, credentials, or `.env` files are committed

## Docs

Add any notes that help to document the feature/changes: just a few words and/or
code snippets.

## Ready?

Did you do any of the following? If not, no worries, but if you can it's really
helpful.

- [ ] Documented what's new
- [ ] Added in-code documentation (wherever needed)
- [ ] Wrote tests for new components/features
- [ ] Ran the linter (`ddev composer phpcs`, `ddev gesso lint`) to ensure style guidelines were followed
- [ ] Ran static analysis (`ddev composer phpstan`)
- [ ] Created a demo
- [ ] Read and validated this description and the diff myself
