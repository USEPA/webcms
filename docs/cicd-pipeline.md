# CI/CD Pipeline (GitLab)

## Overview

The GitLab CI/CD pipeline automates building, testing, and deploying the WebCMS application using a branch-based workflow.

## Branch Strategy

| Branch | Purpose | Deployment | Environment |
|--------|---------|------------|-------------|
| `development` | Development/CI | Automatic | Dev environment |
| `live` | Preproduction infra + staging templates | Manual | Infrastructure + Stage templates |
| `main` | Validation/conditional applies | Conditional | Varies |

## Pipeline Jobs

### Development Branch (`development`)

#### Docker Build Jobs

```yaml
build:drupal:
  rules: [development branch]
  parallel:
    matrix:
      - WEBCMS_TARGET: [drupal, nginx, drush]
        WEBCMS_SITE: [dev]

build:metrics:dev:
  rules: [development branch]
  builds: FPM metrics sidecar container
```

#### Deployment Jobs

```yaml
deploy:dev:init:en:
  stage: deploy:dev:init:en
  variables:
    WEBCMS_LANG: en
    WEBCMS_SITE: dev
    WEBCMS_ENVIRONMENT: preproduction

deploy:dev:validate:en:
  stage: deploy:dev:validate:en

deploy:dev:plan:en:
  stage: deploy:dev:plan:en

deploy:dev:apply:en:
  stage: deploy:dev:apply:en
  when: on_success  # Automatic deployment

update:dev:en:
  stage: update:en
  runs: Drush database updates
```

### Live Branch (`live`)

#### Infrastructure Jobs

```yaml
infrastructure:preproduction:*:
  rules: [live branch]
  when: manual
  manages: AWS infrastructure resources
```

#### Image Mirror/Supporting Jobs

```yaml
.copy:cloudwatch:dev:
  mirrors: Amazon CloudWatch agent

.build:traefik:dev:  
  builds: Custom Traefik image
```

## Pipeline Flow

### Development Deployment Flow

```
1. Push to development branch
         ↓
2. build:drupal (3 images: drupal, nginx, drush)
         ↓
3. build:metrics:dev
         ↓
4. deploy:dev:init:en
         ↓
5. deploy:dev:validate:en
         ↓
6. deploy:dev:plan:en
         ↓
7. deploy:dev:apply:en (automatic)
         ↓
8. update:dev:en (Drush updates)
```

### Infrastructure Deployment Flow

```
1. Push to live branch
         ↓
2. infrastructure:preproduction:init
         ↓
3. infrastructure:preproduction:validate
         ↓
4. infrastructure:preproduction:plan
         ↓
5. infrastructure:preproduction:apply (manual approval)
```

## Templates and Conventions

### `.terraform` Template

- Base template for all Terraform jobs
- Sets up Terraform environment and variables
- Handles backend configuration and tfvars

### `.kaniko` Template  

- Base template for Docker builds
- Configures Kaniko executor with ECR credentials
- Provides caching optimization

### `.deploy` Template

- Extends `.terraform` for deployment jobs
- Sets `TF_MODULE: webcms`
- Configures state addressing and resource_group to serialize per site/lang

## Rules and Triggers

### Automatic Deployment Rules

```yaml
.apply:
  rules:
    - if: >-
        $TF_MODULE == "webcms" &&
        ($CI_COMMIT_BRANCH == "development" || 
         $CI_COMMIT_BRANCH == "main" || 
         $CI_COMMIT_BRANCH == "live")
      when: on_success
```

### Manual Deployment Rules  

```yaml
.apply:
  rules:
    - if: >-
        $TF_MODULE == "infrastructure" &&
        ($CI_COMMIT_BRANCH == "main" || $CI_COMMIT_BRANCH == "live")
      when: manual
```

## Environments and Concurrency

- GitLab Environments track infra (infra/preproduction) and sites (site/dev-en)
- `resource_group` keys (e.g., site/dev-en) prevent overlapping Terraform applies per site/lang

## Security and Scanning

- SAST, Dependency Scanning, and Secret Detection templates included
- IAM permissions scoped via AssumeRole providers per module

## Artifacts and State

- Terraform plan.json is published as a GitLab artifact
- State stored in S3 with DynamoDB locks; backends and providers injected during job setup
