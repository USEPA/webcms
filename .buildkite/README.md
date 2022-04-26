# Buildkite Build Process

## Table of Contents

- [Table of Contents](#table-of-contents)
- [Layout](#layout)
- [Pipeline Notes and Prerequisites](#pipeline-notes-and-prerequisites)
  - [Permissions](#permissions)
  - [Configuration](#configuration)
  - [Evaluation Order](#evaluation-order)
  - [Pipeline Variables](#pipeline-variables)
- [Build Pipelines](#build-pipelines)
  - [`pipeline.yml`](#pipelineyml)
    - [Upload Feature Pipeline](#upload-feature-pipeline)
    - [Upload Infrastructure Pipeline](#upload-infrastructure-pipeline)
    - [Upload Dev Site Pipeline](#upload-dev-site-pipeline)
    - [Upload Stage Site Pipeline](#upload-stage-site-pipeline)
  - [`feature.yml`](#featureyml)
    - [Build Images](#build-images)
    - [Terraform Formatting](#terraform-formatting)
    - [Module Validation: Network](#module-validation-network)
    - [Module Validation: Infrastructure](#module-validation-infrastructure)
    - [Module Validation: WebCMS](#module-validation-webcms)
  - [`infrastructure.yml`](#infrastructureyml)
  - [`webcms.yml`](#webcmsyml)
    - [Build Images](#build-images-1)
    - [Deploy Site: English](#deploy-site-english)
    - [Deploy Site: Spanish](#deploy-site-spanish)
    - [Drush Updates: English](#drush-updates-english)
    - [Drush Updates: Spanish](#drush-updates-spanish)
  - [`terraform.plan.yml`](#terraformplanyml)
  - [`terraform.apply.yml`](#terraformapplyyml)
- [Scripts](#scripts)
  - [`build-images.sh`](#build-imagessh)
  - [`terraform-fmt.sh`](#terraform-fmtsh)
  - [`run-drush.sh`](#run-drushsh)
- [Plugins](#plugins)
  - [`aws-parameters`](#aws-parameters)
- [External Links](#external-links)

## Layout

- `.buildkite/`: Buildkite configuration and supporting code
  - `pipeline.yml` - Primary pipeline to dispatch other pipelines
  - `feature.yml` - Feature branch pipeline
  - `webcms.yml` - WebCMS deployment pipeline
  - `terraform.apply.yml` - Template pipeline for `terraform apply` runs
  - `terraform.plan.yml` - Template pipeline for `terraform plan` runs
  - `build-images.sh` - Builds Docker images using Kaniko
  - `run-rush.sh` - Script to spawn Drush in ECS, performing schema and configuration updates
  - `terraform-fmt.sh` - Script to run `terraform fmt` in each module, doing basic formatting and syntax validation
  - `plugins/` - Custom plugins
    - `aws-parameters/` - Custom plugin to download specific Parameter Store values

## Pipeline Notes and Prerequisites

The pipelines here are designed to run on [Buildkite](https://buildkite.com/) agent servers in an AWS environment. Typically, this would be provided by Buildkite's [Elastic CI Stack for AWS](https://github.com/buildkite/elastic-ci-stack-for-aws).

As such, the scripts assume an environment where agents are run directly on Linux hosts and have access to a few command line utilities:

- bash
- Docker
- AWS CLI
- [jq](https://stedolan.github.io/jq)

### Permissions

Depending on the steps being executed, agents need broad IAM permissions in order to deploy infrastructure into the target account. We accomplish this with AssumeRole-based credentials, preferring to inject the configuration via a file instead of assuming the role directly from the agent script. Each Terraform module has different permission sets, so this allows us to be granular with respect to which API sets an agent can use during the build.

We do not have a specific list of IAM permissions required, but we can provide an incomplete list of services that can be used as a starting point to generate least-privilege access:

- All Terraform modules need read/write access to the Terraform state in S3 (access can be scoped to a single object) and the DynamoDB locks table.
- The VPC module requires read/write access to VPCs, subnets, security groups, and Parameter Store.
- The infrastructure module requires read/write access to IAM (roles and policies), RDS, ECS, ECR, ElastiCache, Elastic Load Balancing v2, EventBridge, S3, Secrets Manager, DynamoDB, and Parameter Store.
- The WebCMS module needs read access to Parameter Store, permission to pass the Drupal roles (see [PassRole](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_roles_use_passrole.html) in the IAM docs), and read/write access to ECS to update tasks and services.
- Steps that run Drush only need permission to run, list, describe, and stop tasks.
- Steps that build Docker images need push access to ECR repositories.

### Configuration

Terraform configuration files are stored in Parameter store, using a hierarchy. We provide examples for a preproduction environment with two sites, dev and stage:

- `/terraform` - the root path for all modules
  - `/terraform/preproduction` - the root path for all preproduction modules
    - `/terraform/preproduction/network` - stores configuration for the network module
    - `/terraform/preproduction/infrastructure` - stores configuration for the infrastructure module
    - `/terraform/preproduction/dev/en` - stores configuration for the English dev site
    - `/terraform/preproduction/dev/es` - stores configuration for the Spansih dev site
    - `/terraform/preproduction/stage/en` - stores configuration for the English stage site
    - `/terraform/preproduction/stage/es` - stores configuration for the Spanish stage site

Under each of these paths, the Terraform pipelines expect three parameters: `backend`, which configures the S3 bucket and key for this module's state; `providers`, which configures the role to be assumed during this Terraform run; and `variables`, which contains key-value pairs in the style of a `terraform.tfvars` file.

### Evaluation Order

Buildkite pipelines are not a linear sequence of steps. Instead, Buildkite dispatches steps in parallel across available agents unless a step indicates that it has prerequisites. A build step can [upload additional pipeline files](https://buildkite.com/docs/agent/v3/cli-pipeline), which are inserted directly after the currently-executing step. That is, if a pipeline consists of the steps `A B C` and step B uploads a new pipeline, the new order is `A B D C` instead of `A B C D`.

This upload behavior is advantageous since we use [wait steps](https://buildkite.com/docs/pipelines/wait-step) to block a pipeline until prior steps have completed. A pipeline with the steps `A B wait C D` will execute A and B in parallel, wait for both to complete successfully, and then execute C and D in parallel. This allows us to upload a full build pipeline and use concurrency where we can, while still requiring a specific execution flow. For example, we execute deployments to English and Spanish sites in parallel, but neither starts until the ECR image build has succeeded.

### Pipeline Variables

We use environment variables to communicate values from one pipeline to any child pipelines uploaded during a step. The three most common variables are `WEBCMS_ENVIRONMENT`, `WEBCMS_SITE`, and `WEBCMS_LANG` - these values correspond to the infrastructure hierarchy (see [Terminology](../terraform/#terminology) in the `terraform/` directory). In addition, the pipeline sets `WEBCMS_IMAGE_TAG` to a combination of the current branch and build number, giving us unique image IDs for each build.

## Build Pipelines

### `pipeline.yml`

#### Upload Feature Pipeline

- **Pipeline step:** `label: ":pipeline: Feature"`
- **Uploaded pipeline:** [`feature.yml`](#featureyml)
- **Branch limits:** Runs _unless_ the branch is `main` or `integration`.
- **Prerequisites:** _(none)_
- **Permissions:** Documented in the pipeline.

This step simply uploads the feature pipeline. The steps from that pipeline are appended to the running Buildkite job.

#### Upload Infrastructure Pipeline

#### Upload Dev Site Pipeline

- **Pipeline step:** `label: ":pipeline: Dev"`
- **Uploaded pipeline:** [`webcms.yml`](#webcmsyml)
- **Branch limits:** Runs _only_ if the branch is `integration` and this is not a pull request.
- **Prerequisites:** _(none)_
- **Permissions:** Documented in the pipeline.

This step uploads the application deployment pipeline with settings for the dev site.

#### Upload Stage Site Pipeline

- **Pipeline step:** `label: ":pipeline: Stage"`
- **Uploaded pipeline:** [`webcms.yml`](#webcmsyml)
- **Branch limits:** Runs _only_ if the branch is `main` and this is not a pull request.
- **Prerequisites:** The network and infrastructure pipelines must have completed succesfully.
- **Permissions:** Documented in the pipeline.

As with the dev site step above, this uploads the application deployment pipeline, targeting the stage site.

### `feature.yml`

#### Build Images

- **Pipeline step:** `label: ":docker: Build images"`
- **Supporting code:** [`build-images.sh`](#build-imagessh)
- **Uploaded pipeline:** _(none)_
- **Permissions:** Push access to ECR.

This step builds the WebCMS' custom images using [Kaniko](https://github.com/GoogleContainerTools/kaniko), which is a tool that can build OCI images from in a container without resorting to Docker-in-Docker or sharing the host's Docker socket.

#### Terraform Formatting

- **Pipeline step:** `label: ":terraform: Formatting"`
- **Supporting code:** [`terraform-fmt.sh`](#terraform-fmtsh)
- **Uploaded pipeline:** _(none)_
- **Permissions:** _(none)_

This step runs `terraform fmt -diff -check` in each of the modules in the [`terraform/`](../terraform) directory.

#### Module Validation: Network

- **Pipeline step:** `label: ":pipeline: :terraform: network"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.plan.yml`](#terraformplanyml)
- **Permissions:** Read access to VPC resources, read/write access to S3 state and DynamoDB locks.

This step uploads the Terraform plan pipeline, targeting the [`network module`](../terraform/network).

#### Module Validation: Infrastructure

- **Pipeline step:** `label: ":pipeline: :terraform: infrastructure"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.plan.yml`](#terraformplanyml)
- **Permissions:** Read access to infrastructure resources, read/write access to S3 state and DynamoDB locks.

Similar to the above step, except that it targets the [`infrastructure module`](../terraform/infrastructure).

#### Module Validation: WebCMS

- **Pipeline step:** `label: ":pipeline: :terraform: webcms"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.plan.yml`](#terraformplanyml)
- **Permissions:** Read access to WebCMS resources, read/write access to S3 state and DynamoDB locks.

This is similar to the above two steps, but targets the [`webcms module`](../terraform/webcms). We choose to execute a single plan against the English stage site, as our feature branch workflow typically targets `main`.

### `infrastructure.yml`

### `webcms.yml`

#### Build Images

- **Pipeline step:** `label: ":docker: Build images"`
- **Supporting code:** [`build-images.sh`](#build-imagessh)
- **Uploaded pipeline:** _(none)_
- **Prerequisites:** _(none)_
- **Permissions:** Push access to ECR.

This step is identical to the build images step in the feature pipeline: it runs a Kaniko-powered image build for the WebCMS' custom images.

#### Deploy Site: English

- **Pipeline step:** `label: ":terraform: WebCMS (${WEBCMS_SITE}-en)"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.apply.yml`](#terraformapplyyml)
- **Prerequisites:** The image build step must have completed successfully.
- **Permissions:** Read/write access to ECS, read/write access to S3 state and DynamoDB locks.

This step uploads the Terraform apply pipeline, targeting the [`webcms module`](../terraform/webcms) where `WEBCMS_LANG` is set to the code `"en"`.

#### Deploy Site: Spanish

- **Pipeline step:** `label: ":terraform: WebCMS (${WEBCMS_SITE}-es)"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.apply.yml`](#terraformapplyyml)
- **Prerequisites:** The image build step must have completed successfully.
- **Permissions:** Read/write access to ECS, read/write access to S3 state and DynamoDB locks.

This is almost identical to the English site's steps, save that it executes the module for the Spanish site.

#### Drush Updates: English

- **Pipeline step:** `label: ":ecs: Drush (${WEBCMS_SITE}-en)"`
- **Supporting code:** [`run-drush.sh`](#run-drushsh)
- **Uploaded pipeline:** _(none)_
- **Prerequisites:** Both site deployments must have completed successfully.
- **Permissions:** ECS RunTask and DescribeTaskDefinition API calls.

This step dispatches an ECS task to run any pending updates (DB schema changes, configuration synchronization, etc.) due to changes in modules. The step waits until it has confirmed that Drush has exited. If Drush did not exit cleanly, the step records an error.

#### Drush Updates: Spanish

- **Pipeline step:** `label: ":ecs: Drush (${WEBCMS_SITE}-es)"`
- **Supporting code:** [`run-drush.sh`](#run-drushsh)
- **Uploaded pipeline:** _(none)_
- **Prerequisites:** Both site deployments must have completed successfully.
- **Permissions:** ECS RunTask and DescribeTaskDefinition API calls.

This is almost identical to the English Drush step, save that it targets the Spanish site.

### `terraform.plan.yml`

This pipeline encapsulates a single step, which arranges for a Terraform plan run using the official [`hashicorp/terraform`](https://hub.docker.com/r/hashicorp/terraform) Docker image.

The pipeline acts as a template, and as such has a few inputs. The file itself has detailed documentation, so we only include an overview here:

- `WEBCMS_TF_MODULE` (required): The name of the Terraform module being planned. This corresponds to a subdirectory under [`terraform/`](../terraform).
- `WEBCMS_SSM_NAMESPACE` (required): The AWS Parameter Store path at which the [module configuration](#configuration) can be found.
- `WEBCMS_SITE` (optional): The name of the site this module is affecting. Defaults to the empty string.
- `WEBCMS_LANG` (optional): The language of the site this module is affecting. Defaults to the empty string.

In addition, the pipeline enforces the presence of `WEBCMS_ENVIRONMENT` and `WEBCMS_IMAGE_TAG`: if one is missing, then an early error is raised. This prevents corrupting deployed resources or accidentally deploying extraneous ones.

The pipeline step has three components:

1. A Buildkite [concurrency group](https://buildkite.com/docs/pipelines/controlling-concurrency) limits Terraform executions by module, environment, site, and language. This means that the group applies across branches, which is what we want: if Terraform is allowed to modify the same module at the same time with the same targets, one run will fail due to being unable to acquire a state lock.
2. From Parameter Store, we load the backend, providers, and variables configuration. These are saved as files to be read by the Terraform CLI. This uses our custom [aws-parameters](#aws-parameters) plugin.
3. We use the official [Buildkite Docker plugin](https://github.com/buildkite-plugins/docker-buildkite-plugin) to run the Terraform container. We pass a small inline script that changes to the module directory, initializes the backend, and creates an in-memory plan.

The plan pipeline does not save any output, as we are using `terraform plan` for module validation instead of pre-planning a subsequent execution.

### `terraform.apply.yml`

This pipeline is (deliberately) a near mirror of the plan pipeline. The only difference is that the apply pipeline saves a plan file and executes it. We keep these as separate pipeline files to avoid accidentally introducing apply logic in a feature pipeline or plan logic in a deploy pipeline.

## Scripts

### `build-images.sh`

This script uses [Kaniko](https://github.com/GoogleContainerTools/kaniko) to build the WebCMS' custom images. While we assume that Buildkite agents have access to Docker, production deployments will use a Docker-based runner and won't be using a system like Docker-in-Docker or bind mounting the Docker daemon socket. By performing Kaniko builds here, we ensure consistency and will be able to catch any issues before they impede production deployments.

There are four custom images that are built for the WebCMS: three are built from [`services/drupal`](../services/drupal) because they need Drupal-related assets, and a fourth is built from [`services/metrics`](../services/metrics). We build all of them from a single script (instead of running them in parallel) since the builds are more IO-bound than CPU-bound and benefit from having a local cache of shared layers instead of needing to pull them from a registry.

The builds of the `services/drupal`-derived images (Drupal, Drush, and nginx) are built using both a filesystem-based cache and a registry-based one. By storing intermediate layers in a registry, builds on a new agent server can download cached layers instead of rebuild them. This avoids the especially expensive initial build that occurs without a cache: we have to build a memcached extension from source in order to take advantage of AWS ElastiCache's auto-discovery mechanism. This layer does not change often, so we can make aggressive use of the cached layer across servers.

By comparison, the metrics image's build is much lighter: it is an Alpine-based image that installs some `apk` packages and copies two files (an entrypoint and a transformation script). The build is therefore not cached as we assume querying remote caches would be more expensive than simply executing the build each time.

Note that while Kaniko does have native support for authenticating with AWS ECR, it can only do so with the default AWS credentials chain. Our builds target repositories in another account, and so we use a Buildkite plugin to assume a cross-account role and simply forward the credentials variables to the Kaniko image.

### `terraform-fmt.sh`

This script uses `terraform fmt`'s ability to do checks in order to ensure that all Terraform module code meets the community's formatting standards. While this could have been written as `terraform fmt -diff -check` lines in the step's command, we choose a shell script for two reasons reasons:

1. The script can iterate over a directory listing, ensuring that we don't forget to check new modules.
2. As we iterate, we can make a note of failure instead of exiting immediately at the first one. This lets us see the status of all modules in a single pass.

### `run-drush.sh`

This script appears lengthy, but its steps are easy to break down. We begin with an overview:

1. The script begins by validating and loading configuration. Errors are issued early for missing environment variables, and Parameter Store is consulted for stored configuration values.
2. Before we can launch a Drush task, we force ECS to stop all running Drupal tasks. Since this script is run immediately after a Terraform deployment completes, ECS may not have launched Drupal tasks using the newly-built images. When code (not configuration) changes between deployments, this can cause some cache corruption: web requests can cache the old code, which Drush will then read. As a stopgap, we simply interrupt all running tasks and let ECS relaunch new ones. The downtime that results (about 1-3 minutes) is acceptable for dev sites and much faster than waiting for old tasks to gracefully stop.
3. We use the AWS CLI to spawn a Drush task on Fargate. The command's overrides parameter includes the Drush script needed to update the site, listed below. If the launch failed, the script prints any failure information it received and then fails, ensuring that the Buildkite job as a whole is marked as a failure.
4. Since the RunTask API is asynchronous (it succeeds as soon as the request was received by ECS), the script captures the launched task's ARN and waits on its status. Every 5 seconds, it uses the [DescribeTasks](https://docs.aws.amazon.com/AmazonECS/latest/APIReference/API_DescribeTasks.html) API to query the status. Most of the code in this loop is used to verify that Drush exited cleanly: the "hot" part of the loop simply checks if it should report a new task state (such as moving from PROVISIONING to PENDING) or if the status is STOPPED. The loop continues if the task is still running.

   Once the task has reached the STOPPED state, the script outputs all of the stop information it can gather. This includes the stop code and reason (where available): this will indicate if the container exited or if there was an API error. If the container has an exit code, it is inspected to determine if it exited cleanly (exit code 0), or with an error. An exit code above 128 indicates exiting due to a signal, and we print the signal name if we can identify it. For example, if the container ran afoul of its memory limits, it would have been terminated by the SIGKILL signal.

   Finally, regardless of exit status, the script prints a clickable link to the logs.

The update script run inside the Drush container has a few steps:

1. Put the site into maintenance mode. Drupal responds early to requests while in this mode, preventing another potential source of cache corruption.
2. Apply any pending database schema updates with [`drush updb`](https://www.drush.org/latest/commands/updatedb/).
3. Rebuild the cache.
4. Apply any pending configuration changes with [`drush cim`](https://www.drush.org/latest/commands/config_import/).
5. Disable maintenance mode.
6. Perform a final cache rebuild.

## Plugins

### `aws-parameters`

This is a custom plugin that supports our use of Parameter Store as the source of truth for Terraform configuration. Since these templates are in use in multiple environments by multiple users, our variables are not tracked in Git.

This plugin provides a `pre-command` script file. As the name implies, Buildkite executes this script as a hook before the pipeline step's command is run. This script reads its configuration from the environment and copies Parameter Store keys to files in the agent's working directory (a checkout of the repository). This satisfies our need to provide files for the Terraform CLI to read.

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
