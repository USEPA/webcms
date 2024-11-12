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
    - [Upload Site Deployment Pipeline](#upload-site-deployment-pipeline)
  - [`feature.yml`](#featureyml)
    - [Build Images](#build-images)
    - [Terraform Formatting](#terraform-formatting)
    - [Module Validation: Network](#module-validation-network)
    - [Module Validation: Infrastructure](#module-validation-infrastructure)
    - [Module Validation: WebCMS](#module-validation-webcms)
  - [`infrastructure.yml`](#infrastructureyml)
    - [Terraform Apply: Network](#terraform-apply-network)
    - [Terraform Apply: Infrastructure](#terraform-apply-infrastructure)
  - [`webcms.yml`](#webcmsyml)
    - [Build Images](#build-images-1)
    - [Deploy Site: English](#deploy-site-english)
    - [Deploy Site: Spanish](#deploy-site-spanish)
    - [Drush Updates: English](#drush-updates-english)
    - [Drush Updates: Spanish](#drush-updates-spanish)
  - [`terraform.plan.yml`](#terraformplanyml)
  - [`terraform.apply.yml`](#terraformapplyyml)
- [Scripts](#scripts)
  - [`terraform-fmt.sh`](#terraform-fmtsh)
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
- **Branch limits:** Runs _unless_ the branch is `integration`, `main`, `live` or `release`.
- **Prerequisites:** _(none)_
- **Permissions:** Documented in the pipeline.

This step simply uploads the feature pipeline. The steps from that pipeline are appended to the running Buildkite job.

#### Upload Infrastructure Pipeline

- **Pipeline step:** `label: ":pipeline: Infrastructure`
- **Uploaded pipeline:** [`infrastructure.yml`](#infrastructureyml)
- **Branch limits:** Runs _only_ if the branch is `main` and this is not a pull request.
- **Prerequisites:** _(none)_

This step uploads the infrastructure pipeline.

#### Upload Site Deployment Pipeline

- **Pipeline step:** `label: ":pipeline: WebCMS (${BUILDKITE_BRANCH})"`
- **Uploaded pipeline:** [`webcms.yml`](#webcmsyml)
- **Branch limits:** Runs _only_ if a) the branch is `integration`, `main`, `release`, or `live` and b) this is not a pull request.
- **Prerequisites:** If triggered, the infrastructure pipeline and its steps must all have completed successfully.
- **Permissions:** Documented in the pipeline.

This step uploads the site deployment steps for the environments matching the given branch.

### `feature.yml`

#### Build Images

- **Pipeline step:** `group: ":docker: Build images"`
- **Uploaded pipeline:** _(none)_
- **Permissions:** Push access to ECR.

The steps in this group build the WebCMS' custom images using [Kaniko](https://github.com/GoogleContainerTools/kaniko), which is a tool that can build OCI images from in a container without resorting to Docker-in-Docker or sharing the host's Docker socket. For feature branches, they save cached layers but not the final image. This allows priming the cache for incoming changes but avoids wasting ECR storage space for final images.

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

#### Terraform Apply: Network

- **Pipeline step:** `label: ":terraform: WebCMS (${WEBCMS_SITE}-en)"`
- **Supporting code:** [terraform/network](../terraform/network/)
- **Uploaded pipeline:** [`terraform.apply.yml`](#terraformapplyyml)
- **Prerequisites:** _(none)_
- **Permissions:** Read/write access to DynamoDB and S3 for Terraform, read/write access to VPC and Parameter Store APIs for creating VPC resources and recording their IDs.

This step uploads the Terraform application pipeline to run Terraform on a basic VPC. This VPC is _not_ used in production; it exists largely to demonstrate the bare minimum requirements for security groups and availability zone/subnet layout.

#### Terraform Apply: Infrastructure

- **Pipeline step:** `label: ":terraform: WebCMS (${WEBCMS_SITE}-en)"`
- **Supporting code:** [terraform/infrastructure](../terraform/infrastructure/)
- **Uploaded pipeline:** [`terraform.apply.yml`](#terraformapplyyml)
- **Prerequisites:** The prior network pipeline must have completed successfully.
- **Permissions:** Read/write access to most conventional AWS resources: ECS, ELB, Parameter Store, ECR, etc., for creating container cluster resources.

This step uploads the Terraform application pipeline to run Terraform on the WebCMS' infrastructure.

### `webcms.yml`

#### Build Images

- **Pipeline step:** `group: ":docker: Build images"`
- **Uploaded pipeline:** _(none)_
- **Permissions:** Push access to ECR.

This step is identical to the build images step in the feature pipeline: it runs a Kaniko-powered image build for the WebCMS' custom images. Unlike the feature pipeline, this step asks Kaniko to upload the resulting image to ECR.

#### Deploy Site: English

- **Pipeline step:** `label: ":terraform: WebCMS (${WEBCMS_SITE}-en)"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.apply.yml`](#terraformapplyyml)
- **Prerequisites:** The image build step must have completed successfully.
- **Permissions:** Read/write access to ECS, read/write access to S3 state and DynamoDB locks.

This step uploads the Terraform apply pipeline, targeting the [`webcms module`](../terraform/webcms) where `WEBCMS_LANG` is set to the code `"en"`.

This step and the Spanish step below are executed in parallel since the two Terraform deployments are kept separate from each other.

#### Deploy Site: Spanish

- **Pipeline step:** `label: ":terraform: WebCMS (${WEBCMS_SITE}-es)"`
- **Supporting code:** _(none)_
- **Uploaded pipeline:** [`terraform.apply.yml`](#terraformapplyyml)
- **Prerequisites:** The image build step must have completed successfully.
- **Permissions:** Read/write access to ECS, read/write access to S3 state and DynamoDB locks.

This is almost identical to the English site's steps, save that it executes the module for the Spanish site.

This step and the English step above are executed in parallel since the two Terraform deployments are kept separate from each other.

#### Drush Updates: English

- **Pipeline step:** `label: ":ecs: Drush (${WEBCMS_SITE}-en)"`
- **Supporting code:** [`run-drush.sh`](#run-drushsh)
- **Uploaded pipeline:** _(none)_
- **Prerequisites:** Both site deployments must have completed successfully.
- **Permissions:** ECS RunTask and DescribeTaskDefinition API calls.

This step dispatches an ECS task to run any pending updates (DB schema changes, configuration synchronization, etc.) due to changes in modules. The step waits until it has confirmed that Drush has exited. If Drush did not exit cleanly, the step records an error.

This step and the Spanish step below are executed in parallel since the two Terraform deployments are kept separate from each other.

#### Drush Updates: Spanish

- **Pipeline step:** `label: ":ecs: Drush (${WEBCMS_SITE}-es)"`
- **Supporting code:** [`run-drush.sh`](#run-drushsh)
- **Uploaded pipeline:** _(none)_
- **Prerequisites:** Both site deployments must have completed successfully.
- **Permissions:** ECS RunTask and DescribeTaskDefinition API calls.

This is almost identical to the English Drush step, save that it targets the Spanish site.

This step and the English step above are executed in parallel since the two Terraform deployments are kept separate from each other.

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

### `terraform-fmt.sh`

This script uses `terraform fmt`'s ability to do checks in order to ensure that all Terraform module code meets the community's formatting standards. While this could have been written as `terraform fmt -diff -check` lines in the step's command, we choose a shell script for two reasons reasons:

1. The script can iterate over a directory listing, ensuring that we don't forget to check new modules.
2. As we iterate, we can make a note of failure instead of exiting immediately at the first one. This lets us see the status of all modules in a single pass.

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
- [jq](https://stedolan.github.io/jq/)
- [kaniko](https://github.com/GoogleContainerTools/kaniko)
