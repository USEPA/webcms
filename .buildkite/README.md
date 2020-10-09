# Buildkite Build Process

## Table of Contents

- [Table of Contents](#table-of-contents)
- [Layout](#layout)
- [Build Pipelines](#build-pipelines)
  - [`pipeline.yml`](#pipelineyml)
    - [Upload Feature Pipeline](#upload-feature-pipeline)
    - [Upload Dev Deploy Pipeline](#upload-dev-deploy-pipeline)
    - [Upload Stage Deploy Pipeline](#upload-stage-deploy-pipeline)
    - [Upload Spanish Deploy Pipeline](#upload-spanish-deploy-pipeline)
  - [`feature.yml`](#featureyml)
    - [Docker Image Build](#docker-image-build)
    - [Terraform Formatting Check](#terraform-formatting-check)
    - [Terraform Plan](#terraform-plan)
  - [`deploy.yml`](#deployyml)
    - [Docker Image Push](#docker-image-push)
    - [Terraform Apply](#terraform-apply)
    - [Site Updates via Drush](#site-updates-via-drush)
- [Plugins](#plugins)
  - [`aws-parameters`](#aws-parameters)
- [External Links](#external-links)

## Layout

- `.buildkite/`: Buildkite configuration and supporting code
  - `pipeline.yml` - Buildkite pipeline file to dispatch other pipelines
  - `feature.yml` - Feature branch pipeline
  - `deploy.yml` - Deployment pipeline
  - `*.sh` - Scripts supporting Buildkite steps
  - `plugins/` - Custom plugins
    - `aws-parameters/` - Custom plugin to download specific Parameter Store values

## Build Pipelines

### `pipeline.yml`

#### Upload Feature Pipeline

- **Pipeline step:** `label: ":pipeline: Feature"`
- **Supporting code:** _(none)_
- **Branch limitations:** Runs on any branch that is _not_ `main` or `integration`
- **Next pipeline:** [`feature.yml`](#featureyml)

This step simply uploads the `feature.yml` pipeline file for feature branches. For the `terraform plan` output, the workspace is set to the staging environment, as it is assumed that most feature branches target `main`.

#### Upload Dev Deploy Pipeline

- **Pipeline step:** `label: ":pipeline: Dev"`
- **Supporting code:** _(none)_
- **Branch limitations:** Only runs on the `integration` branch.
- **Next pipeline:** [`deploy.yml`](#deployyml)

This step uploads the `deploy.yml` pipeline file for the `integration` branch. The Terraform workspace is set to the development environment.

#### Upload Stage Deploy Pipeline

- **Pipeline step:** `label: ":pipeline: Stage"`
- **Supporting code:** _(none)_
- **Branch limitations:** Only runs on the `main` branch.
- **Next pipeline:** [`deploy.yml`](#deployyml)

This step uploads the `deploy.yml` pipeline file for the `main` branch. The Terraform workspace is set to the staging environment.

#### Upload Spanish Deploy Pipeline

- **Pipeline step:** `label: ":pipeline: Stage"`
- **Supporting code:** _(none)_
- **Branch limitations:** Only runs on the `main` branch.
- **Next pipeline:** [`deploy.yml`](#deployyml)

This step uploads the `deploy.yml` pipeline file for the `main` branch. The Terraform workspace is set to the Spanish-language environment.

### `feature.yml`

#### Docker Image Build

- **Pipeline step:** `label: ":docker: Build images"`
- **Supporting code:** `.buildkite/docker-build.sh`
- **Requirements:**
  - Permissions to read/write to ECR

For feature branches, we perform Docker image builds on the Buildkite agent servers. This serves two purposes:

1. It acts as a sanity check of that branch's Docker build, and
2. It ensures the agent's Docker layer cache is up to date.

The concern over the layer cache stems almost entirely from the time needed to build the [AWS ElastiCache PHP extension](https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php/). The WebCMS' containers are Alpine-based, so they can't use the prebuilt `.so` files that are provided.

#### Terraform Formatting Check

- **Pipeline step:** `label: ":terraform: Formatting`
- **Supporting code:** _(none)_
- **Requirements:** _(none)_

We use [`terraform fmt`](https://www.terraform.io/docs/commands/fmt.html) to do a simple formatting check. Formatting violations are output as a diff, which can be fixed either manually or by running `terraform fmt` locally.

#### Terraform Plan

- **Pipeline step:** `label: ":terraform: Plan"`
- **Supporting code:** _(none)_
- **Requirements:**
  - Permissions to read and write to this project's AWS resources (writes are needed to create locks in DynamoDB)
  - Parameters from Parameter Store (see the [`aws-parameters`](#aws-parameters) plugin)

Every push to this repository results in a Terraform plan being executed in Buildkite. We do this in order to validate a few assumptions:

1. The plan is syntactically and semantically valid, and
2. A human is able to review the changes to determine if it may result in downtime or other disruption.

We do not assume that Buildkite servers have the `terraform` tool installed, so we perform this build in a Docker image provided by Hashicorp (see [`hashicorp/terraform`](https://hub.docker.com/r/hashicorp/terraform)).

### `deploy.yml`

#### Docker Image Push

- **Pipeline step:** `label: ":docker:" Build images"`
- **Supporting code:** `.buildkite/docker-build.sh`
- **Requirements:**
  - Permission to read/write to ECR

This step performs a Docker build and push to this environment's ECR repositories. Since all feature branches also perform Docker builds, it is assumed that this step is relatively efficient, re-using the layer cache to avoid having to rebuild the AWS ElastiCache extension.

#### Terraform Apply

- **Pipeline step:** `label: ":terraform: Apply"`
- **Supporting code:** _(none)_
- **Requirements:**
  - Permission to read/write to this project's AWS resources
  - Parameters from Parameter Store (see the [`aws-parameters`](#aws-parameters) plugin)

This step performs a Terraform plan and then immediately applies it. The output of `terraform plan` is used as the changelog for this environment's infrastructure. Like the [Terraform Plan](#terraform-plan) step, this uses [`hashicorp/terraform`](https://hub.docker.com/r/hashicorp/terraform) to use the `terraform` command.

#### Site Updates via Drush

- **Pipeline step:** `label: ":ecs: Run Drush updates"`
- **Supporting code:** `.buildkite/run-drush.sh`
- **Requirements:**
  - Permission to communicate with the AWS ECS
  - The [Docker Image Push](#docker-image-push) and [Terraform Apply](#terraform-apply) steps must have completed successfully.
  - The Drush AWSVPC configuration
  - The `jq` tool

This step performs the Drush updates necessary to apply the new WebCMS configuration. Conceptually, the step is relatively simple, but the `run-drush.sh` script includes some extra code to present the task's status to the user.

The steps necessary are below:

1. Create an inline shell script for the Drush updates:
   ```sh
   drush --uri="$WEBCMS_SITE_URL" updb -y
   drush --uri="$WEBCMS_SITE_URL" cr
   drush --uri="$WEBCMS_SITE_URL" cim -y
   drush --uri="$WEBCMS_SITE_URL" ib --choice safe
   drush --uri="$WEBCMS_SITE_URL" cr
   ```
2. Create a JSON object corresponding to the AWS ECS task overrides. We use the `jq` tool to preserve proper JSON syntax rather than relying on manual quoting.
3. Spawn a Drush task against the WebCMS cluster.
4. Optionally, use the spawned task's ARN to wait on the task to exit. This way, builds can be failed if the task exits with a non-successful code.

Note: Drush logs are not returned by this step; instead, they are stored in CloudWatch.

## Plugins

### `aws-parameters`

This is a custom plugin that supports our use of Parameter Store as the source of truth for Terraform configuration. Since these templates are in use in multiple environments by multiple users, our variables are not tracked in Git.

This plugin provides a `pre-command` script file. As the name implies, Buildkite executes this script as a hook before the pipeline step's command is run. In this particular plugins' case, we download the shared Terraform backend configuration (see [Partial Configuration](https://www.terraform.io/docs/backends/config.html#partial-configuration) in the Terraform docs) and the variables applicable to the current workspace.

## External Links

- [Buildkite](https://buildkite.com/)
- [Buildkite docs](https://buildkite.com/docs)
  - [Pipelines](https://buildkite.com/docs/pipelines)
  - [Plugins](https://buildkite.com/docs/plugins)
- [Terraform CLI](https://www.terraform.io/docs/cli-index.html)
- [AWS CLI](https://docs.aws.amazon.com/cli/latest/reference/)
  - [`aws ecs run-task`](https://docs.aws.amazon.com/cli/latest/reference/ecs/run-task.html)
- The [RunTask API](https://docs.aws.amazon.com/AmazonECS/latest/APIReference/API_RunTask.html)
  - The [`networkConfiguration` parameter](https://docs.aws.amazon.com/AmazonECS/latest/APIReference/API_RunTask.html#ECS-RunTask-request-networkConfiguration)
  - The [`overrides` parameter](https://docs.aws.amazon.com/AmazonECS/latest/APIReference/API_RunTask.html#ECS-RunTask-request-overrides)
- [jq](https://stedolan.github.io/jq/)
