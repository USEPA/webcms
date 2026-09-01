# Changelog

All notable changes to the EPA WebCMS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Releases are tagged on `staging` using the date-based scheme `vYYYY.MM.DD` (with
an optional `.N` sequence for multiple same-day releases), as described in the
[Release Tagging Process](docs/GIT_WORKFLOW.md#release-tagging-process).

Entry categories: **Added**, **Changed**, **Deprecated**, **Removed**, **Fixed**,
**Security**.

## [Unreleased]

### Added

- `docs/GIT_WORKFLOW.md`: dedicated, authoritative branching, merging, promotion,
  hotfix, freeze, release-tagging, automated back-merge, branch-protection, and
  roles & responsibilities reference.
- `docs/cicd-pipeline.md`: CI/CD pipeline overview covering architecture,
  branch-to-environment mapping, stages, build-once-deploy-many, skip-build,
  security scanning, pipeline variables, notifications, and a reference map.
- `SECURITY.md`: vulnerability reporting policy and scope.
- `.github/CODEOWNERS`: review routing for documentation, CI/CD, infrastructure,
  and Drupal configuration.
- `.github/ISSUE_TEMPLATE/config.yml`: disables blank GitHub issues and redirects
  reporters to the Jira `WEBCMS` project (the system of record), plus Slack,
  security, and GitLab pipeline links.
- `CHANGELOG.md`: this changelog.
- `LICENSE.md`: root-level MIT License, matching the EPA standard used across
  EPA repositories.

### Changed

- `CONTRIBUTING.md`: condensed the inline Git Workflow into a quick-reference
  summary that links to `docs/GIT_WORKFLOW.md`, expanded Additional Resources
  into a complete documentation index, and corrected the documentation and
  license references.
- `README.md`: corrected the documentation index and linked the Git Workflow and
  CI/CD pipeline docs.
- `.github/pull_request_template.md`: expanded into a structured template with
  change type, testing evidence, configuration/deployment, and a reviewer
  checklist.

### Fixed

- Removed broken documentation links (a non-existent `WARP.md` reference in
  `README.md`, and `docs/` references in `CONTRIBUTING.md` that pointed at files
  that did not exist).
