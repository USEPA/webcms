# Security Policy

The EPA WebCMS is the public-facing content management system for EPA.gov. We
take the security of this codebase and the data it handles seriously. This
document explains how to report a vulnerability and what to expect in response.

## Reporting a Vulnerability

**Do not report security vulnerabilities through public GitHub issues, pull
requests, or the #webcms-dev Slack channel.** Public disclosure before a fix is
available puts the platform and its users at risk.

Instead, report suspected vulnerabilities privately through one of the following:

- **EPA vulnerability disclosure:** Follow the U.S. EPA Vulnerability Disclosure
  Policy at <https://www.epa.gov/vulnerability-disclosure-policy>. This is the
  preferred channel for anyone outside the WebCMS team.
- **WebCMS team (internal reporters):** Email the WebCMS team at
  `webcms-team@epa.gov` with the subject line prefixed `[SECURITY]`. Do not
  include exploit details in Slack.

When reporting, please include as much of the following as you can:

- A description of the vulnerability and its potential impact.
- The affected component, URL, or file path.
- Step-by-step instructions to reproduce the issue.
- Any proof-of-concept code, request/response captures, or screenshots.
- Suggested remediation, if you have one.

Please **do not** include live secrets, production credentials, or real user
personal data in your report. Redact sensitive values and describe them instead.

## What to Expect

- **Acknowledgement:** We aim to acknowledge a report within a few business days.
- **Assessment:** We triage by severity and confirm whether the issue is
  reproducible.
- **Remediation:** Confirmed issues are tracked privately, fixed, and deployed
  following the [Git Workflow](docs/GIT_WORKFLOW.md) — typically via the
  [hotfix flow](docs/GIT_WORKFLOW.md#hotfix-flow) when the severity warrants it.
- **Coordinated disclosure:** We will coordinate timing of any public disclosure
  with the reporter where appropriate.

## Scope

This policy covers the source code in this repository (`USEPA/webcms`): the
Drupal application in `services/drupal/`, custom modules and themes, the CI/CD
pipeline configuration, and the Terraform infrastructure code.

For production-site issues that are not specific to this source code (for
example, content problems or live-site availability), use the EPA channels at
<https://www.epa.gov> rather than this repository.

## Supported Versions

This repository follows a rolling-release model — the `main` branch reflects what
is currently in production. Security fixes are applied to `main` (and back-merged
to `staging` and `development`); there are no separately maintained older release
branches.

## Dependency and Code Security

The pipeline and tooling include several automated safeguards:

- **Dependency updates** via Dependabot (see [`.github/dependabot.yml`](.github/dependabot.yml)).
- **SAST, dependency scanning, and secret detection** on the `staging` branch
  (see [CI/CD Pipeline → Security Scanning](docs/cicd-pipeline.md#security-scanning)).
- **Container image scanning** (Prisma Cloud) for stage-bound images.

Contributors are expected to follow the security guidance in
[CONTRIBUTING.md](CONTRIBUTING.md#security-best-practices): never commit secrets
or credentials, use environment variables for sensitive data, sanitize user
input, and follow
[Drupal security best practices](https://www.drupal.org/docs/security-in-drupal).
