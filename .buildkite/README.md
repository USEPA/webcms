# Buildkite Build Process

## Table of Contents

* [Layout](#layout)
* [Build Steps](#build-steps)
    * [Docker Images](#docker-images)
    * [Terraform Plan](#terraform-plan)
    * [Terraform Apply](#terraform-apply)
    * [Site Updates via Drush](#site-updates-via-drush)
* [Other Links](#other-links)

## Layout

- `.buildkite/`: Buildkite configuration and supporting code
  - `pipeline.yml` - Buildkite pipeline file
  - `*.sh` - Scripts supporting Buildkite steps
  - `plugins/` - Custom plugins
    - `aws-parameters/` - Custom plugin to download specific Parameter Store values
    - `config/` - Exports shared configuration as environment variables

## Build Steps

### Docker Images

- **Pipeline step:** `label: ":docker: Build images"`
- **Supporting code:** `.buildkite/build-docker.sh`
- **Branch limitations:** _(none)_

This project uses three custom Docker images: one each for Drupal, nginx, and Drush. The images all share a base copy of the WebCMS' filesystem - this includes Drupal 8 core, third-party modules, custom code, and the compiled theme.

The Docker build step builds each of the custom images and pushes them to the current AWS account's ECR repositories. Buildkite plugins assume a role that is able to read and write to those repositories during the build.

### Terraform Plan

- **Pipeline step:** `label: ":terraform: Plan"`
- **Supporting code:** `.buildkite/terraform-plan.sh`
- **Branch limitations:** _(none)_
- **Requirements:**
  - The Terraform state backend config (`backend.config` in the scripts)
  - A terraform variables file (`terraform.tfvars`)

Every push to this repository results in a Terraform plan being executed in Buildkite. We do this in order to validate a few assumptions:

1. The plan is syntactically and semantically valid, and
2. A human is able to review the changes to determine if it may result in downtime or other disruption.

We do not assume that Buildkite servers have the `terraform` tool installed, so we perform this build in a Docker image provided by Hashicorp (see [`hashicorp/terraform`](https://hub.docker.com/r/hashicorp/terraform)).

The Terraform script requires two external configuration files:

1. `backend.config`, the configuration values for the S3-backed state file, and
2. `terraform.tfvars`, the Terraform variables file.

The plan step outputs `out.plan`, the Terraform plan snapshot. This is saved as a build artifact.

### Terraform Apply

- **Pipeline step:** `label: ":terraform: Apply"`
- **Supporting code:** `.buildkite/terraform-apply.sh`
- **Branch limitations:** Only run on pushes to these branches:
  - `master`, which affects the dev environment
  - `stable`, which affects the staging environment
- **Requirements:**
  - The Terraform state backend config (`backend.config` in the scripts)
  - The Terraform plan (`out.plan`) from the previous step

This step continues the plan started in the first step. Our Buildkite pipeline has separated these steps in order to restrict applications only to those branches which correspond to environments (e.g., the `stable` branch represents the staging environment).

The plan output, `out.plan` is used instead of Terraform variables. This prevents rebuilds from accidentally rolling back updates to the branch - the plan file will be detected as stale, and Terraform will refuse to apply the stale plan.

NB. Since this step is already using Terraform, we capture the Drush AWSVPC configuration. See `drush-vpc-config` in `outputs.tf` and the step below.

### Site Updates via Drush

- **Pipeline step:** `label: ":ecs: Run Drush updates"`
- **Supporting code:** `.buildkite/run-drush.sh`
- **Branch limitations:** Only run on pushes to these branches:
  - `master`, which affects the dev environment
  - `stable`, which affects the staging environment
- **Requirements:**
  - The Drush AWSVPC configuration (can obtain via `terraform output drush-vpc-config`)
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
3. Save the Drush AWSVPC network configuration.
4. Spawn a Drush task against the WebCMS cluster.
5. Optionally, use the spawned task's ARN to wait on the task to exit. This way, builds can be failed if the task exits with a non-successful code.

Note: Drush logs are not returned by this step; instead, they are stored in CloudWatch.

## Other Links

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
