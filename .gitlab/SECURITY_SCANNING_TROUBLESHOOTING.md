# GitLab Security Scanning Troubleshooting

I wrote this note to document a pipeline failure I hit while tightening our GitLab security scanning behavior. At first glance it looked like a YAML issue around the security scanning section of `.gitlab-ci.yml`, but the YAML itself was not the real problem. The failure came from how GitLab merges included security templates with locally overridden jobs.

This is the class of issue that is easy to misread because GitLab can point at a line range that looks harmless, while the actual failure is caused by the merged job definition after template expansion.

## What Broke

I was trying to keep security scanning restricted to the `staging` branch while still using GitLab's built-in templates:

- `Jobs/SAST.gitlab-ci.yml`
- `Jobs/Dependency-Scanning.gitlab-ci.yml`
- `Jobs/Secret-Detection.gitlab-ci.yml`

The problematic behavior came from two closely related mistakes:

1. Treating GitLab security template parent jobs as if they were normal executable jobs.
2. Overriding a template job with the wrong job name.

In practice, this can surface in two ways:

- GitLab tries to execute a configuration-only job and fails with:
  `job is used for configuration only, and its script should not be executed`
- GitLab sees a locally defined job that has `rules:` but no `script:`, `trigger:`, or `extends:` and rejects the pipeline configuration.

## Root Cause

### 1. `sast` and `dependency_scanning` are not normal runnable jobs

The `sast` and `dependency_scanning` stanzas from GitLab's templates are effectively parent/configuration jobs. They exist so GitLab can propagate shared settings down to analyzer jobs.

That means:

- it is safe to override things like `stage` and `variables`
- it is **not** safe to use those parent stanzas as a place to define shared `rules`

If I attach `rules:` to those parent jobs, GitLab may try to execute them directly, which causes the configuration-only job error.

### 2. Secret Detection uses `secret_detection`, not `secret-detection`

Secret Detection is different from SAST and dependency scanning in one important way for this repo: the actual job name to override is `secret_detection` with an underscore.

If I write this:

```yaml
secret-detection:
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
    - when: never
```

GitLab does **not** merge that into the built-in Secret Detection job. Instead, it treats it as a brand new local job named `secret-detection`.

That new job is invalid because it has no `script:`, `trigger:`, or `extends:`.

The correct override is:

```yaml
secret_detection:
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
    - when: never
```

## Why This Looked Like a YAML Problem

The suspicious lines were in the right neighborhood, but the syntax there was fine.

This is the part that is worth remembering:

- valid YAML does **not** guarantee valid GitLab CI configuration
- GitLab includes can change the effective meaning of a block after merge
- a job name typo (`_` vs `-`) can produce an invalid CI job even when the YAML parses cleanly

So if the file "looks fine" but the pipeline still will not validate, I should stop thinking only about indentation and start thinking about merged GitLab job semantics.

## How I Debug This Class of Failure

When I hit this kind of problem again, this is the order I want to use.

### 1. Separate YAML syntax from GitLab CI semantics

First question:

- Is the YAML malformed?

Second question:

- Even if the YAML is valid, is GitLab rejecting the merged pipeline definition?

Those are different problems and need different debugging paths.

### 2. Use GitLab CI Lint / pipeline validation early

Before assuming the issue is runner-related, I validate the pipeline in GitLab:

- Pipeline Editor → Validate
- CI Lint

That catches a lot of configuration errors before a pipeline even starts.

### 3. Inspect included templates and confirm exact job names

When a job comes from a GitLab template, I verify the exact job name before overriding anything.

For this security scanning setup, the names that matter are:

- Parent/configuration jobs:
  - `sast`
  - `dependency_scanning`
- Executable template job with a direct override name:
  - `secret_detection`
- Analyzer jobs I can safely target more specifically:
  - `semgrep-sast`
  - `gemnasium-dependency_scanning`

The naming detail matters because GitLab merges by exact job name.

### 4. Check whether I accidentally created a new job

If I add a stanza and GitLab complains, I check whether I accidentally defined a brand new job instead of overriding a template job.

The most common causes are:

- underscore vs hyphen mismatch
- singular vs plural job name mismatch
- typo in the analyzer job name

If the name does not exactly match a template job, GitLab treats it as a new job.

### 5. Look for "rules-only" jobs

If a locally defined job has only:

- `rules:`
- `stage:`
- maybe `variables:`

but no:

- `script:`
- `trigger:`
- `extends:`

then it is usually invalid unless it is correctly overriding a template job that already provides the missing pieces.

In this repo, `secret_detection` is an example of a valid override target because the included template already defines the executable job. A typo like `secret-detection` loses that inheritance and becomes invalid.

This is one of the fastest sanity checks for failures like this.

### 6. Distinguish parent jobs from executable analyzer jobs

For GitLab security templates, I should assume parent jobs are special until proven otherwise.

Safe pattern:

- use parent jobs for stage/variable overrides
- use actual analyzer jobs for branch-specific `rules`

### 7. Use analyzer debug logging only for runtime failures

If the pipeline validates but an analyzer job itself fails at runtime, I can temporarily enable:

```yaml
variables:
  SECURE_LOG_LEVEL: "debug"
```

That is useful for runtime troubleshooting, but I should remove it afterward because debug output can expose sensitive information in job logs.

## Safe Mitigation Pattern for This Repo

What I want in this repo is:

- keep the parent jobs for stage overrides only
- restrict runnable analyzers to `staging`
- override Secret Detection using the real job name

This is the safe shape:

```yaml
sast:
  stage: Test

dependency_scanning:
  stage: Test

secret_detection:
  stage: Test
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
    - when: never

semgrep-sast:
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
    - when: never

gemnasium-dependency_scanning:
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
    - when: never
```

This avoids both failure modes:

- no `rules:` on the config-only `sast` or `dependency_scanning` parents
- no accidental creation of a fake `secret-detection` job

## Unsafe Patterns I Want to Avoid

### Unsafe: putting `rules` on parent jobs

```yaml
sast:
  stage: Test
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
```

```yaml
dependency_scanning:
  stage: Test
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
```

These look reasonable, but they can cause GitLab to try to run configuration-only jobs.

### Unsafe: overriding Secret Detection with the wrong name

```yaml
secret-detection:
  rules:
    - if: '$CI_COMMIT_BRANCH == "staging"'
```

This creates a new invalid job instead of overriding the built-in Secret Detection job.

### Unsafe: mixing legacy `only/except` logic into template jobs

If a GitLab template already relies on `rules`, mixing in `only:` or `except:` overrides can also break validation.

## Mitigation and Prevention Going Forward

These are the guardrails I want to keep in place:

1. **Use exact template job names**
   - especially for GitLab-managed security jobs
   - never assume hyphen/underscore variants are interchangeable

2. **Keep parent security job overrides minimal**
   - `stage`
   - `variables`
   - other clearly documented safe keys

3. **Apply branch `rules` at the executable job level**
   - analyzer jobs for SAST/dependency scanning
   - `secret_detection` using the exact built-in job name

4. **Validate in a merge request before merging**
   - use CI Lint / Pipeline Editor validation
   - avoid making security template changes directly on a branch that drives deployments

5. **Document the gotchas inline**
   - the comments in `.gitlab-ci.yml` are worth keeping because this is not obvious behavior

6. **Prefer stable built-in templates**
   - use GitLab's stable templates unless there is a strong reason to opt into latest behavior

## Quick Checklist

When this breaks again, this is the fast checklist I want to run:

- Did I change a GitLab-managed security template override?
- Did I put `rules:` on `sast` or `dependency_scanning`?
- Did I use the exact job name `secret_detection`?
- Did I accidentally create a new job with no `script:`?
- Does CI Lint fail before the pipeline even starts?
- Is this actually a GitLab config merge issue rather than a YAML indentation problem?

## References I Would Check Again

If this behavior changes after a GitLab upgrade, these are the first docs I would revisit:

- GitLab application security troubleshooting:
  `https://docs.gitlab.com/user/application_security/troubleshooting_application_security/`
- GitLab secret detection configuration:
  `https://docs.gitlab.com/user/application_security/secret_detection/pipeline/configure/`
- GitLab security configuration best practices:
  `https://docs.gitlab.com/user/application_security/configuration/`

## Bottom Line

The important lesson for me is that this was not really a YAML bug. It was a GitLab template override bug that only looked like YAML because of where the failure appeared in the file.

If I need to scope security scanning by branch in this repo, the reliable approach is:

- keep parent security jobs lightweight
- target real analyzer jobs when adding `rules`
- use `secret_detection` exactly as named
- validate the merged pipeline definition before merging

That approach is much safer than trying to force shared `rules` into GitLab's configuration-only security stanzas.
