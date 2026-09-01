# CI/CD Pipeline

This document explains how code becomes a running environment for the EPA
WebCMS. It is an overview and index; the **authoritative configuration** is
[`.gitlab-ci.yml`](../.gitlab-ci.yml) and the includes under
[`.gitlab/`](../.gitlab). Where this document and the pipeline configuration
disagree, the configuration wins — update this document to match.

For the branching and promotion rules that drive the pipeline, see
[docs/GIT_WORKFLOW.md](GIT_WORKFLOW.md). For the day-to-day deploy commands, see
[CONTRIBUTING.md](../CONTRIBUTING.md#deployment-guide).

## Table of Contents

- [Architecture](#architecture)
- [Branch-to-Environment Mapping](#branch-to-environment-mapping)
- [Pipeline Stages](#pipeline-stages)
- [How a Pipeline Is Triggered](#how-a-pipeline-is-triggered)
- [Build Once, Deploy Many](#build-once-deploy-many)
- [Skip-Build & Build Detection](#skip-build--build-detection)
- [Security Scanning](#security-scanning)
- [Advanced Pipeline Variables](#advanced-pipeline-variables)
- [Notifications](#notifications)
- [Automated Back-Merge](#automated-back-merge)
- [Performance Optimizations](#performance-optimizations)
- [Troubleshooting](#troubleshooting)
- [Reference Map](#reference-map)

## Architecture

Code is hosted on GitHub and deployed through a GitLab mirror into AWS ECS:

```
GitHub (USEPA/webcms)
    │  mirror sync (every ~30 min, or manual "Update now")
    ▼
GitLab (drupalcloud/drupalclouddeployment)
    │  CI/CD pipeline (.gitlab-ci.yml)
    ▼
AWS ECS (Fargate) — dev, stage, and production services
```

- **GitHub** is the source of truth: all development, review, and version
  history live here.
- **GitLab** holds the infrastructure runners and orchestrates builds and
  deployments. It pulls from the GitHub mirror.
- **AWS ECS** runs the application. Each site/language pair (English and Spanish)
  is its own ECS service; deployments are rolling and zero-downtime.

Because the GitLab mirror does not auto-trigger pipelines (a deliberate
permission restriction), deployments are triggered explicitly — by
[`push-dev.sh`](../push-dev.sh), [`trigger-pipeline.sh`](../trigger-pipeline.sh),
or the GitLab pipeline UI. See
[`.gitlab/DEPLOYMENT_WORKFLOW.md`](../.gitlab/DEPLOYMENT_WORKFLOW.md) for the
step-by-step manual flow.

## Branch-to-Environment Mapping

| Branch | Environment | Trigger | Security scans | Typical duration |
|--------|-------------|---------|----------------|------------------|
| `development` | Dev | Automatic via `push-dev.sh` | No (kept fast) | ~10–15 min full build; ~3–5 min skip-build |
| `staging` | Stage | Push to `staging` (manual promotion) | Yes (SAST, dependency, secret, image) | ~25–35 min |
| `main` | Production | Manual pipeline trigger on GitLab | Promotes stage-tested images (no rebuild) | ~5–10 min |

The `development` branch trades security scanning for speed; the `staging` branch
runs the full security suite before anything is eligible for production. See
[Security Scanning](#security-scanning).

## Pipeline Stages

The pipeline defines the following stages (from
[`.gitlab-ci.yml`](../.gitlab-ci.yml)). Which stages actually run depends on the
branch and on pipeline variables.

| Stage | Purpose | Runs on |
|-------|---------|---------|
| `Build` | Build the `drupal`, `nginx`, and `drush` Docker images (Kaniko) | All branches (skippable — see [Skip-Build](#skip-build--build-detection)) |
| `Test` | SAST, dependency scanning, secret detection | `staging` only |
| `Scan` | Prisma Cloud image vulnerability scanning | `staging` only |
| `Infrastructure:Preprod:Init/Validate/Plan/Apply` | Provision/adjust shared preproduction infrastructure via Terraform (apply requires manual approval) | `staging` |
| `Deploy:Dev:Init/Validate/Plan/Apply` | Terraform deploy to the dev ECS services | `development`; or `staging` when `DEPLOY_TO_DEV=true` (manual) |
| `Deploy:Stage:Init/Validate/Plan/Apply` | Terraform deploy to the stage ECS services (English + Spanish, parallel) | `staging` |
| `Update` | Drush database updates and config import via ECS tasks (parallel by site/language) | After a successful deploy |
| `Artifacts` | Create deployment artifacts | After deploy |
| `Release` | Create GitLab releases | After deploy |

The deploy stages follow the standard Terraform lifecycle — **Init → Validate →
Plan → Apply** — so every infrastructure change is planned before it is applied.
The `Update` stage runs `drush deploy` semantics (database updates, config
import, cache rebuild) against the freshly deployed containers; see
[`ci/README.md`](../ci/README.md) for the ECS Drush automation that drives it.

## How a Pipeline Is Triggered

Pipelines are created only for the sources allowed by the `workflow:` rules in
[`.gitlab-ci.yml`](../.gitlab-ci.yml): `push` (includes mirror updates), `web`
(manual UI run), `api`, `trigger`, `schedule`, and tag creation. Everything else
is rejected.

The typical paths:

- **Dev:** `./push-dev.sh` from your machine. It pushes to GitHub, helps sync the
  mirror, and triggers the `development` pipeline. It also auto-detects whether a
  full build is needed (see below).
- **Stage:** push to the `staging` branch; the stage pipeline runs automatically.
- **Production:** sync the mirror and trigger the pipeline manually on the `main`
  branch in the GitLab UI.

## Build Once, Deploy Many

A core safety property of this pipeline: **the images deployed to production are
the same images that were built and tested on stage.**

1. The `staging` pipeline builds the Docker images and runs the full security
   suite against them.
2. The release is tagged on `staging` (see
   [Release Tagging Process](GIT_WORKFLOW.md#release-tagging-process)).
3. The `main` (production) pipeline **promotes** those tagged images — it does
   not rebuild. This guarantees production runs exactly what stage validated.

## Skip-Build & Build Detection

For the `development` branch, rebuilding Docker images is often unnecessary —
many changes (custom modules, config, Twig templates, docs) do not affect the
image. `push-dev.sh` analyzes the changed files and chooses automatically:

- **Full build** when build-affecting files change: `composer.json` /
  `composer.lock` / `composer.patches.json`, theme source (`*.scss`, `*.js`,
  `source/`, Gulp config), `package.json` / `package-lock.json`, any
  `Dockerfile`, build scripts, `docker-compose*`, or `.gitlab-ci.yml`.
- **Deploy-only** (reuse `:development-latest` images) for everything else:
  custom modules, `config/`, Drush commands, non-compiled Twig/PHP templates,
  and documentation.

Manual overrides: `./push-dev.sh --force-build` and `./push-dev.sh --skip-build`.
You can also set `SKIP_BUILD=true` directly when triggering a pipeline from the
GitLab UI. Full detail and examples live in
[CONTRIBUTING.md](../CONTRIBUTING.md#automatic-build-detection--skip-build-deployments).

> Removing a Composer module is always a full build, and must follow the
> [Config Sync Workflow](../services/drupal/CONFIG_SYNC_WORKFLOW.md) — never
> skip-build a module removal.

## Security Scanning

Security scanning runs on the **`staging` branch only**, so that everything bound
for production is scanned, while the dev branch stays fast.

- **Test stage:** SAST (Semgrep), Dependency Scanning (Gemnasium), Secret
  Detection — included from GitLab's built-in templates.
- **Scan stage:** Prisma Cloud image scanning for the `drupal`, `nginx`, and
  `drush` containers.

GitLab's security templates expose **configuration-only** parent jobs (`sast`,
`dependency_scanning`, `secret_detection`) that must not be given executable
`rules`, plus executable analyzer jobs (`semgrep-sast`,
`gemnasium-dependency_scanning`, `secret_detection`) where branch rules belong.
Getting this wrong produces *"job is used for configuration only"* errors. The
full root-cause analysis and the safe override pattern are documented in
[`.gitlab/SECURITY_SCANNING_TROUBLESHOOTING.md`](../.gitlab/SECURITY_SCANNING_TROUBLESHOOTING.md).

## Advanced Pipeline Variables

Set these as CI/CD variables when triggering a pipeline from the GitLab UI
(Pipelines → Run pipeline).

| Variable | Default | Effect |
|----------|---------|--------|
| `SKIP_BUILD` | `false` | Skip the `Build` stage and reuse `:development-latest` images. |
| `DEPLOY_TO_DEV` | `false` | On the `staging` branch, also build for and deploy to dev (the dev deploy job stays **manual**). Ignored on `development`. |
| `WEBCMS_SITE_FILTER` | unset | On the `staging` branch, restrict deployment to a single site: `stage` (stage English + Spanish) or `dev` (dev only, with `DEPLOY_TO_DEV=true`). Ignored on `development`. |

`DEPLOY_TO_DEV` and `WEBCMS_SITE_FILTER` are documented with worked examples in
[README.md → Advanced Pipeline Variables](../README.md#advanced-pipeline-variables)
and [CONTRIBUTING.md → Advanced Pipeline Variables](../CONTRIBUTING.md#advanced-pipeline-variables).

## Notifications

The pipeline posts Microsoft Teams notifications for major events (pipeline
started, build success/failure, manual approval required, deployment lifecycle,
security scan issues, pipeline completed). Notifications are driven by the
`TEAMS_WEBHOOK_URL` CI/CD variable and the jobs in
[`.gitlab/teams-notifications.yml`](../.gitlab/teams-notifications.yml). Setup and
troubleshooting: [`.gitlab/TEAMS_NOTIFICATIONS.md`](../.gitlab/TEAMS_NOTIFICATIONS.md).

## Automated Back-Merge

After any push to `main`, the `open-backmerge-pr` job opens a GitHub PR from
`main` → `development` so the integration branch never drifts behind production.
It is idempotent (skips if an open back-merge PR already exists). See
[docs/GIT_WORKFLOW.md → Automated Back-Merge](GIT_WORKFLOW.md#automated-back-merge)
for the job definition and required `GITHUB_TOKEN` setup.

## Performance Optimizations

The development path is tuned to be substantially faster than stage by building
fewer images, skipping security scanning, parallelizing Terraform validation,
and reusing cached layers (Kaniko `--cache-ttl=168h`). The dev pipeline runs in
roughly 10–15 minutes versus 25–35 for stage. The full list of optimizations,
their locations in `.gitlab-ci.yml`, and the safety rationale are in
[`.gitlab/PIPELINE_OPTIMIZATIONS.md`](../.gitlab/PIPELINE_OPTIMIZATIONS.md).

## Troubleshooting

| Symptom | Where to look |
|---------|---------------|
| Pipeline did not start after a push | Mirror not synced yet — sync manually, then re-trigger. See [`.gitlab/DEPLOYMENT_WORKFLOW.md`](../.gitlab/DEPLOYMENT_WORKFLOW.md). |
| `push-dev.sh` runs but no pipeline appears | GitLab token expired/invalid, or mirror not synced. See [CONTRIBUTING.md → Troubleshooting](../CONTRIBUTING.md#gitlab-pipeline-fails-to-trigger). |
| Pipeline rejected before any job runs | GitLab CI config merge issue (often a security-template override). See [`.gitlab/SECURITY_SCANNING_TROUBLESHOOTING.md`](../.gitlab/SECURITY_SCANNING_TROUBLESHOOTING.md). |
| Skip-build deploy fails with "image not found" | No `:development-latest` image yet — run a full build first (`./push-dev.sh`). |
| Changes deployed but not visible | Browser or Drupal cache, or wrong image deployed. See [CONTRIBUTING.md → Troubleshooting](../CONTRIBUTING.md#changes-not-visible-after-deployment). |
| Deployment failed mid-pipeline | Open the failed job's logs; common causes: AWS credentials expired, Terraform state locked, Docker build error. |

## Reference Map

| Topic | Authoritative source |
|-------|----------------------|
| Full pipeline definition | [`.gitlab-ci.yml`](../.gitlab-ci.yml) |
| Docker build jobs | [`.gitlab/docker.yml`](../.gitlab/docker.yml) |
| Terraform jobs | [`.gitlab/terraform.yml`](../.gitlab/terraform.yml) |
| Teams notifications | [`.gitlab/teams-notifications.yml`](../.gitlab/teams-notifications.yml), [`.gitlab/TEAMS_NOTIFICATIONS.md`](../.gitlab/TEAMS_NOTIFICATIONS.md) |
| Manual deploy flow | [`.gitlab/DEPLOYMENT_WORKFLOW.md`](../.gitlab/DEPLOYMENT_WORKFLOW.md) |
| Pipeline optimizations | [`.gitlab/PIPELINE_OPTIMIZATIONS.md`](../.gitlab/PIPELINE_OPTIMIZATIONS.md) |
| Security scanning gotchas | [`.gitlab/SECURITY_SCANNING_TROUBLESHOOTING.md`](../.gitlab/SECURITY_SCANNING_TROUBLESHOOTING.md) |
| ECS Drush update automation | [`ci/README.md`](../ci/README.md) |
| Branching & releases | [docs/GIT_WORKFLOW.md](GIT_WORKFLOW.md) |
| Infrastructure (Terraform) | [terraform/infrastructure/README.md](../terraform/infrastructure/README.md), [terraform/webcms/README.md](../terraform/webcms/README.md) |
