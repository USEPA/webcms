# Git Workflow

This is the authoritative branching, merging, and release reference for the EPA
WebCMS. It applies to everyone who contributes code — EPA staff, contractors,
and middleware engineers alike. [CONTRIBUTING.md](../CONTRIBUTING.md) links here
for the full workflow; this document is the single source of truth for how
changes move from a developer's machine to production.

Our process adapts **GitHub Flow** to the EPA WebCMS multi-environment
deployment model. Code lives on GitHub (`USEPA/webcms`); deployment runs through
a GitLab mirror into AWS ECS. See [docs/cicd-pipeline.md](cicd-pipeline.md) for
how each branch maps onto the pipeline.

## Table of Contents

- [Goals](#goals)
- [Branch Roles](#branch-roles)
- [The Promotion Path at a Glance](#the-promotion-path-at-a-glance)
- [Issue Tracking (Jira)](#issue-tracking-jira)
- [Branch Naming](#branch-naming)
- [Standard Feature Flow](#standard-feature-flow)
- [Hotfix Flow](#hotfix-flow)
- [Why Branch From `development`?](#why-branch-from-development)
- [Merge Strategy](#merge-strategy)
- [Commit Message Convention](#commit-message-convention)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Release Cadence & Coordination](#release-cadence--coordination)
- [Release Tagging Process](#release-tagging-process)
- [Stage-to-Production Promotion Checklist](#stage-to-production-promotion-checklist)
- [Freeze Protocol](#freeze-protocol)
- [Automated Back-Merge](#automated-back-merge)
- [Branch Protection Configuration](#branch-protection-configuration)
- [Roles & Responsibilities](#roles--responsibilities)
- [FAQs](#faqs)

## Goals

1. Keep `main` always releasable and aligned with production.
2. Ensure every change is validated in the dev environment (`development`
   branch) before moving to stage or production.
3. Provide a lightweight, repeatable promotion path:
   **feature → development → staging → main**.
4. Support urgent hotfixes without blocking regular feature work.
5. Preserve a clear audit trail: any commit in production can be traced back to
   the review, the stage validation, and the release tag that shipped it.

## Branch Roles

| Branch | Purpose | Deployment Target | Notes |
|--------|---------|-------------------|-------|
| `main` | Production source of truth | Production (manual pipeline trigger) | Locked; release merges and hotfixes only. See [Branch Protection](#branch-protection-configuration). |
| `staging` | Stage/staging code | Stage environment | Mirrors upcoming production; runs full security scans. |
| `development` | Active integration branch | Dev environment | All feature work branches from here. Triggers the standard dev pipeline (`push-dev.sh`). |
| `feature/*`, `bugfix/*` | Short-lived work branches | None directly | Always branch from `development` and open PRs back into `development`. |
| `hotfix/*` | Urgent fixes for prod | Main & staging | Created from `main`, merged back to all branches after release. |

`main`, `staging`, and `development` are **long-lived** branches that map
one-to-one onto a deployed environment. Everything else is **short-lived** and
should be deleted after merge.

## The Promotion Path at a Glance

```
  feature/* ─┐
  bugfix/*  ─┼─▶ development ──▶ staging ──▶ main
             │   (dev env)      (stage env)  (production)
  hotfix/* ──┘ (branches from main; back-merged to all)
```

- **Forward promotion** uses merge commits so each environment advance is a
  single, identifiable merge.
- **Back-merge** (`main → development`) keeps the integration branch from
  drifting behind production. It is automated; see
  [Automated Back-Merge](#automated-back-merge).

## Issue Tracking (Jira)

WebCMS work is tracked in **Jira**, not GitHub Issues. The Jira project is
**`WEBCMS`** (for example, `WEBCMS-307`), served from
`https://jira.epa.gov/browse/WEBCMS`. Jira is the **system of record**
for bugs and features; the GitHub Issues tab is disabled and redirects there (see
[`.github/ISSUE_TEMPLATE/config.yml`](../.github/ISSUE_TEMPLATE/config.yml)).

So that every change is traceable back to the work that motivated it:

- **Branch** — include the WEBCMS key in the name:
  `feature/WEBCMS-412-alert-banner`.
- **Commit** — start the subject with the key, or reference it in the footer:
  `feat(alerts): WEBCMS-412 add site-wide alert banner`.
- **Pull request** — link the Jira issue in the description (the
  [PR template](../.github/pull_request_template.md) opens with
  `Closes WEBCMS-XXXX`).

A change without a Jira key has no traceable home — create or find the WEBCMS
issue before you start. (Deployment-pipeline and CI/CD infrastructure issues are
tracked separately in GitLab; see
[CONTRIBUTING.md → Getting Help](../CONTRIBUTING.md#getting-help).)

## Branch Naming

Use a `type/` prefix and a short, hyphenated, lowercase description. Include the
WEBCMS Jira key so the branch, commits, and PR all link back to the same work
item.

| Type | Use for | Branch from | PR target | Example |
|------|---------|-------------|-----------|---------|
| `feature/` | New functionality | `development` | `development` | `feature/WEBCMS-412-alert-banner` |
| `bugfix/` | Non-urgent bug fixes | `development` | `development` | `bugfix/WEBCMS-418-menu-breakpoint` |
| `hotfix/` | Urgent production fixes | `main` | `main` | `hotfix/WEBCMS-431-xss-patch` |

Guidelines:

- Keep slugs short but descriptive — the branch name should make the intent
  obvious without opening the PR.
- One logical change per branch. If you find yourself naming a branch
  `feature/several-things`, split it.
- Delete the branch after it merges (locally and on the remote).

## Standard Feature Flow

1. **Sync `development`**
   ```bash
   git checkout development
   git pull origin development
   ```
2. **Branch from `development`**
   ```bash
   git checkout -b feature/<short-description>
   ```
   Include the ticket number in the branch name if applicable.
3. **Develop & validate locally**
   - Use the DDEV and Gesso commands documented in
     [CONTRIBUTING.md](../CONTRIBUTING.md).
   - Follow [Conventional Commits](#commit-message-convention) and keep commits
     focused.
   - Periodically sync with the latest integrated work:
     ```bash
     git fetch origin && git rebase origin/development
     ```
   - Run the local quality gates before opening a PR:
     ```bash
     ddev drush updb -y
     ddev drush cex          # export config you changed
     ddev composer phpcs
     ddev composer phpstan
     ddev gesso build        # if you touched theme source
     ```
4. **Open a PR into `development`**
   - Target branch: `development`.
   - Fill out the [pull request template](../.github/pull_request_template.md)
     completely, including the tracking issue link and manual-test evidence.
   - After merge, trigger `./push-dev.sh --skip-build` for fast validation in
     the dev environment.
   - Resolve review feedback. Merge with **squash or rebase** to keep
     `development` history clean.
5. **Promote to Stage (`staging`)** — *EPA staff only*
   - On the release cadence, update the theme library versions to match the new version string (pattern `vYYYY.MM.DD`). Do this to all libraries in `epa_theme.libraries.yml` except for the `svgxuse` library.
   -- We do this step to have more granular control over the versioning of the theme CSS & JS.
   - Merge `development` → `staging`.
   - Create a new tag with the following pattern `vYYYY.MM.DD`.
   - [Create a new Release](https://github.com/USEPA/webcms/releases), leaving the release notes as default to pull in all the commit messages merged to staging.
   -- Check the `Set as pre-release` checkbox and uncheck the `Set as the latest release` checkbox.
   - Push to `staging` to run the full stage pipeline (security scans included).
6. **Promote to Production (`main`)** — *EPA staff only*
   - After stage sign-off, follow the
     [Stage-to-Production Promotion Checklist](#stage-to-production-promotion-checklist).
   - The release manager tags the release on `staging` (see
     [Release Tagging Process](#release-tagging-process)).
   - Merge `staging` → `main` via PR (merge commit), then trigger the production
     pipeline.
   - The production pipeline **does not rebuild images** — it promotes the same
     images tested on stage (build once, deploy many).
7. **Back-merge to `development`**
   - After a production release, merge `main` → `development` using a **merge
     commit** (not rebase).
   - An automated CI job opens this PR; see
     [Automated Back-Merge](#automated-back-merge).
   - If the auto-PR has conflicts, resolve manually:
     ```bash
     git checkout development
     git pull origin development
     git merge origin/main
     # Resolve conflicts, then push
     git push origin development
     ```

## Hotfix Flow

Hotfixes bypass the normal `development → staging → main` promotion path to get
urgent fixes into production quickly. **EPA staff only** for the merge and
deploy steps.

1. **Branch from `main`**
   ```bash
   git checkout main
   git pull origin main
   git checkout -b hotfix/<issue-description>
   ```
2. **Implement and validate locally**
   - Fix the issue and test with `ddev drush deploy -y` and
     `ddev drush config:status`.
   - Follow the same code-quality checks as any other PR.
3. **Open a PR targeting `main`**
   - Requires at least one maintainer approval.
   - The PR description should explain the urgency and exactly what is broken in
     production.
4. **Merge into `main`** (merge commit, do not squash).
5. **Tag and deploy to production**
   - The release manager tags the hotfix on `main` (see
     [Release Tagging Process](#release-tagging-process)).
   - Sync the GitLab mirror (manual sync — do not wait for auto-sync).
   - Trigger the production pipeline manually on GitLab (`main` branch).
   - Monitor the pipeline to completion and verify the production site.
6. **Sync the fix to `staging` and `development`**
   - Cherry-pick or merge the hotfix into `staging`:
     ```bash
     git checkout staging
     git pull origin staging
     git cherry-pick <hotfix-merge-commit-sha>
     git push origin staging
     ```
   - The automated back-merge job opens a PR from `main` → `development`. If it
     does not cover the fix (e.g., due to conflicts), cherry-pick into
     `development` manually.
   - **All three long-lived branches (`main`, `staging`, `development`) must
     contain the hotfix before the next sprint cycle.**
7. **Communicate** — announce the hotfix and its deployment in #webcms-dev.

## Why Branch From `development`?

- **Continuous integration:** Features integrate in the active development
  branch, the way GitHub Flow / Git Flow intend.
- **Early conflict detection:** Features see each other immediately, catching
  integration issues during development rather than at PR time.
- **Simplified workflow:** No need for developers to manually rebase against a
  `development` that has diverged from `main`.
- **Natural integration:** Multiple features can be tested together in the dev
  environment before promotion.
- **Aligned with the deployment model:** The branch you develop in is the branch
  that deploys to dev.

## Merge Strategy

The merge method is deliberate and differs by the kind of merge:

| Merge | Method | Why |
|-------|--------|-----|
| Feature/bugfix PR → `development` | **Squash or rebase** | Keeps a clean, linear history on the integration branch. |
| `development` → `staging` | **Merge commit** | A release maps to one identifiable merge. |
| `staging` → `main` | **Merge commit** | Preserves the exact stage-tested state as a single production merge. |
| Hotfix → `main` | **Merge commit** (never squash) | Keeps the hotfix commits intact for cherry-picking to `staging`/`development`. |
| `main` → `development` (back-merge) | **Merge commit** (not rebase, not fast-forward) | Preserves commit hashes on `development` so no one's local branch is invalidated; the merge commit is the audit trail for when production state synced back. |

## Commit Message Convention

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

<body>

<footer>
```

Common types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`,
`chore`.

When a change has a tracking issue, reference it in the subject or footer so the
commit links back to the work item.

Examples:

```bash
git commit -m "feat(workflow): add approval step for content editors"
git commit -m "fix(theme): correct responsive menu breakpoint"
git commit -m "docs: update deployment guide with skip-build instructions"
```

Write commit messages and PR descriptions in your own words, and read anything
you are about to submit before you submit it. A description is a request for
someone else's time — make sure it is accurate and that you have validated every
claim in it.

## Pull Request Guidelines

- **Target branches:** Feature/bugfix work → `development`; hotfixes → `main`.
- **Use the template:** Fill out the
  [pull request template](../.github/pull_request_template.md), including the
  tracking issue link.
- **Keep PRs reviewable** (roughly less than a day of work). Several small PRs
  beat one large one. Use draft PRs for early feedback.
- **Run the local gates first:** `ddev drush updb`, `ddev drush cex`,
  `ddev composer phpcs`, `ddev composer phpstan`, theme builds, and relevant
  tests before requesting review.
- **Provide evidence:** Include notes on how you manually tested, rationale for
  specific implementation choices, and screenshots or a screen recording for
  visual changes. The reviewer should be able to see that the work was validated
  before it reached them.
- **Require at least one maintainer approval.**
- **Do not bundle unrelated module updates** into one PR, unless they are
  genuinely coupled (for example, `metatag` and `metatag_schema`).
- **Configuration changes travel with code.** Never commit configuration in code
  and database simultaneously, and follow the
  [Config Sync Workflow](../services/drupal/CONFIG_SYNC_WORKFLOW.md) when adding
  or removing modules.

## Release Cadence & Coordination

- **Daily dev deploys:** Merge PRs into `development` as they are approved. Use
  skip-build for quick iterations; run one full build per day or after any
  dependency change.
- **Stage deploys:** At least once per sprint (or as needed). Merge
  `development` → `staging`, let the stage pipeline run, and perform QA.
- **Production deploys:** After stage QA sign-off, follow the
  [Stage-to-Production Promotion Checklist](#stage-to-production-promotion-checklist).
  The release manager tags the release on `staging` and triggers the production
  pipeline on GitLab after merging to `main`.
- **Back-merge:** After each production release, immediately merge
  `main` → `development` (merge commit). An automated CI job opens this PR.

## Release Tagging Process

The release manager (Michael Hessling) creates the release tag on `staging`
**before** merging to `main`. The tag marks the exact commit whose Docker images
were built and tested on stage — this is the audit trail linking what was tested
to what gets deployed.

**Tag format:** `vYYYY.MM.DD` (for example, `v2026.04.09`). For multiple
same-day releases, append a sequence number: `v2026.04.09.2`.

**Steps:**

1. Confirm stage QA is complete and sign-off is received.
2. Tag the release on `staging`:
   ```bash
   git checkout staging
   git pull origin staging
   git tag -a vYYYY.MM.DD -m "Release vYYYY.MM.DD: <brief summary>"
   git push origin vYYYY.MM.DD
   ```
3. Merge `staging` → `main` via PR (merge commit).
4. Sync the GitLab mirror and trigger the production pipeline on GitLab (`main`
   branch). The production pipeline promotes the images built from the tagged
   staging commit — no rebuild.

## Stage-to-Production Promotion Checklist

Follow this checklist when promoting from stage to production. **All steps are
EPA staff only.**

**Pre-Promotion:**

- [ ] Stage QA is complete
- [ ] Stakeholder sign-off received
- [ ] No outstanding critical bugs on stage
- [ ] If a freeze is needed, initiate the [Freeze Protocol](#freeze-protocol)
- [ ] Announce the planned production deployment in #webcms-dev

**Promotion:**

- [ ] Release manager tags the release on `staging` (see
      [Release Tagging Process](#release-tagging-process))
- [ ] Create PR: `staging` → `main` on GitHub
- [ ] Review the PR — verify it contains only the expected changes
- [ ] Merge the PR using a merge commit (do not squash)
- [ ] Sync the GitLab mirror
- [ ] Trigger the production pipeline manually on GitLab (`main` branch)
- [ ] Monitor the pipeline to completion

**Post-Promotion:**

- [ ] Verify the production site is healthy (smoke test key pages and workflows)
- [ ] Confirm the ECS service updated in the AWS Console
- [ ] Merge the automated back-merge PR (`main` → `development`); resolve
      conflicts if any
- [ ] Announce deployment complete in #webcms-dev
- [ ] If a freeze was in effect, lift it

## Freeze Protocol

A code freeze restricts merges to stabilize the codebase before a production
release.

**Initiating a freeze:**

1. The release manager (Michael Hessling) announces the freeze in #webcms-dev
   with the expected duration and target release date.
2. During the freeze, **no new PRs are merged into `development`** except:
   - Critical bug fixes required for the release
   - Documentation-only changes
3. In-progress feature branches should be rebased on `development` but held until
   the freeze lifts.

**During the freeze:**

1. QA and validation happen on the `staging` (stage) environment.
2. Critical fixes follow the [hotfix flow](#hotfix-flow): branch from `main` or
   `staging`, fix, PR, merge.
3. All hotfixes merged during the freeze must be communicated in #webcms-dev.

**Lifting the freeze:**

1. The production release is deployed and verified.
2. Back-merge `main` → `development` (merge commit) to incorporate freeze-period
   hotfixes.
3. The release manager announces the freeze is lifted in #webcms-dev.
4. Held PRs can resume merging into `development`.

## Automated Back-Merge

To prevent `development` from drifting behind `main`, a GitLab CI job
automatically opens a GitHub PR from `main` → `development` after any push to
`main`.

**How it works:**

1. When the GitLab mirror syncs a new commit on `main`, the `open-backmerge-pr`
   CI job runs.
2. The job checks for an existing open back-merge PR to avoid duplicates.
3. If none exists, it creates a PR from `main` → `development` via the GitHub
   API.
4. The team reviews and merges the PR. If there are conflicts, a developer
   resolves them manually.

**GitLab CI job** (implemented in `.gitlab-ci.yml`):

```yaml
open-backmerge-pr:
  stage: .post
  image: alpine:latest
  rules:
    - if: '$CI_COMMIT_BRANCH == "main"'
  before_script:
    - apk add --no-cache curl jq
  script:
    - |
      EXISTING=$(curl -s -H "Authorization: token $GITHUB_TOKEN" \
        "https://api.github.com/repos/USEPA/webcms/pulls?head=USEPA:main&base=development&state=open" \
        | jq length)
      if [ "$EXISTING" -gt 0 ]; then
        echo "Back-merge PR already exists, skipping."
        exit 0
      fi
      curl -s -X POST -H "Authorization: token $GITHUB_TOKEN" \
        -H "Content-Type: application/json" \
        "https://api.github.com/repos/USEPA/webcms/pulls" \
        -d '{
          "title": "chore: back-merge main into development",
          "body": "Automated back-merge to sync production state into development after release.",
          "head": "main",
          "base": "development"
        }'
  allow_failure: true
```

**Required setup:**

- Add `GITHUB_TOKEN` as a protected, masked CI/CD variable in GitLab
  (Settings → CI/CD → Variables) with `repo` scope.

## Branch Protection Configuration

GitHub branch protection rules for this repository. Audit these periodically to
ensure they remain in effect.

**To configure:** GitHub → Repository Settings → Branches → Branch protection
rules.

### `main` (production)

- Require a pull request before merging
  - Required approvals: **1** (maintainer)
  - Dismiss stale pull request approvals when new commits are pushed
- Require status checks to pass before merging
- Require branches to be up to date before merging
- Do not allow force pushes
- Do not allow deletions
- Restrict who can push: EPA staff / repository maintainers only

### `staging` (stage)

- Require a pull request before merging
  - Required approvals: **1** (maintainer)
- Require status checks to pass before merging
- Do not allow force pushes
- Do not allow deletions
- Restrict who can push: EPA staff / repository maintainers only

### `development` (dev)

- Require a pull request before merging
  - Required approvals: **1**
- Do not allow force pushes

### Feature branches (`feature/*`, `bugfix/*`, `hotfix/*`)

- No branch protection rules required
- Developers manage their own branches
- Delete branches after merge

## Roles & Responsibilities

| Role | Who | Responsibilities |
|------|-----|------------------|
| Contributor | All developers and middleware engineers | Branch from `development`, open well-evidenced PRs, respond to review, keep configuration and code in sync. |
| Maintainer / Reviewer | Repository maintainers | Review and approve PRs, enforce the merge strategy, ensure branch protection holds. |
| Release manager | Michael Hessling | Tag releases, run the stage-to-production promotion, declare and lift freezes, trigger production pipelines. |

Promotion to `staging` and `main`, production pipeline triggers, and release
tagging are **EPA staff only**. Contractors and middleware engineers contribute
through `development` and PRs; they do not merge to or deploy from `staging` or
`main`.

## FAQs

- **Why not branch from `main` directly?** Branching from `main` causes feature
  branches to diverge from active development work. By the time a feature is
  ready, it is missing weeks of changes merged to `development`, leading to
  late-stage conflicts.
- **Can I deploy from a feature branch?** No. Only `development` (dev) and
  `staging` (stage) trigger automatic deployments. Production deploys from `main`
  via a manual pipeline trigger.
- **What if a feature spans multiple sprints?** Periodically rebase on
  `development` to stay current with integrated work. Use feature flags for
  incomplete functionality.
- **How do freeze periods work?** See the [Freeze Protocol](#freeze-protocol) for
  the full process.
- **Who can tag a release or deploy to production?** The release manager, and
  EPA staff only. See [Roles & Responsibilities](#roles--responsibilities).
