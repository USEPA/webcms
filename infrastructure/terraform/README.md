# Terraform Documentation

## Table of Contents

- [Table of Contents](#table-of-contents)
- [Prerequisites](#prerequisites)
- [Special Variables](#special-variables)
  - [`site-env-name`](#site-env-name)
  - [`site-env-state`](#site-env-state)
- [First-Time Setup](#first-time-setup)
  - [Cluster Infrastructure](#cluster-infrastructure)
  - [Secrets](#secrets)
    - [MySQL Root User](#mysql-root-user)
    - [MySQL WebCMS Users](#mysql-webcms-users)
    - [Mail Password](#mail-password)
    - [Hash Salt](#hash-salt)
- [Initial Image Builds](#initial-image-builds)
- [Drupal Installation](#drupal-installation)
- [Changing the Environment State](#changing-the-environment-state)
- [Terraform Overrides](#terraform-overrides)
  - [Drupal Egress Rules](#drupal-egress-rules)

## Prerequisites

Ensure that you have created a service-linked role for Elasticsearch in AWS IAM (see the [AWS docs](https://docs.aws.amazon.com/elasticsearch-service/latest/developerguide/slr-es.html)).

This can be done with the following Terraform snippet:

```terraform
resource "aws_iam_service_linked_role" "es" {
  aws_service_name = "es.amazonaws.com"
}
```

## Special Variables

Most variables are documented in `variables.tf`, but we call out a few specially here.

### `site-env-name`

This is an environment name such as `dev`, `stage`, or `prod`. Due to the fact that the environment name is used to generate resources in Terraform, there cannot be two clusters with the same environment name.

### `site-env-state`

Due to a quirk in how Drupal's settings work, the Drupal installation needs to be bootstrapped before it can utilize the Redis cache.

When building out the cluster infrastruture for the first time, the variable `site-env-state` must be the value `"build"`. This value should change to `"run"` only after Drupal has been installed.

## First-Time Setup

### Cluster Infrastructure

First, `terraform apply` the templates _without_ setting any of these variables: `image-tag-nginx`, `image-tag-drupal`, or `image-tag-drush`. If any of these variables are present, the templates will perform a deployment and fail: there are no ECR repositories or image tags to pull from.

### Secrets

#### MySQL Root User

Create a new (secure) password and save it to the `/webcms-<env>-<suffix>/db_root/password` secret.

Next, log into the utility server using Session Manager. Using the Aurora endpoint from the RDS console, log in to MySQL and run this query:

```sql
SET PASSWORD = PASSWORD('<new password>');
```

Log out and run `truncate --size=0 ~/.mysql_history` to remove the password from the history file.

#### MySQL WebCMS Users

First, the Drupal 8 user. Create a new secure password using the same method as before. Save it to the `/webcms-<env>-<suffix>/db_app/password` secret.

Next, run this query against Aurora:

```sql
CREATE USER 'webcms'@'%' IDENTIFIED BY '<password>';
GRANT ALL ON webcms.* TO 'webcms'@'%';
```

Now, the Drupal 7 user. This user (and database) is used for the Drupal 7->Drupal 8 migration.

Create a new secure password and save it to the `/webcms-<env>-<suffix>/db_app_d7/password` secret.

Run this query against Aurora:

```sql
CREATE DATABASE webcms_d7;
CREATE USER 'webcms_d7'@'%' IDENTIFIED BY '<password>';
GRANT ALL ON webcms_d7.* TO 'webcms_d7'@'%';
```

After completing these queries, remember to run `truncate --size=0 ~/.mysql_history` to remove the passwords from the history file.

#### Mail Password

Obtain the mail password for the email user (cf. the `email-auth-user` variable) specified in the template. Save it to the `/webcms-<env>-<suffix>/mail/password` secret.

#### Hash Salt

Create a random value and save it to the `/webcms-<env>-<suffix>/drupal/hash_salt` secret.

## Initial Image Builds

Log in to ECR using [`aws ecr get-login`](https://docs.aws.amazon.com/cli/latest/reference/ecr/get-login.html) (AWS CLI v1) or [`aws ecr get-login-password`](https://docs.aws.amazon.com/cli/latest/reference/ecr/get-login-password.html) (AWS CLI v2).

Next, run a script like the one below to populate the image repositories with an initial image (see also the `.buildkite/docker-build.sh` script in this repository):

```bash
#!/bin/bash

set -euo pipefail

# This tag name isn't meaningful; future deployments should be done with a CI/CD server
# that tags based on the branch name and/or build number.
tag="initial"

for target in drupal nginx drush; do
  output="$(terraform output "ecr-$target")"

  docker build services/drupal \
    --target "$target" \
    --tag "$output:$tag"

  docker push "$output:$tag"
done
```

You can now set the `image-tag-drupal`, `image-tag-nginx`, and `image-tag-drush` variables in your Terraform variables file, using the `tag` variable from the script above. Perform another `terraform apply` to capture the updates.

NOTE: Once this is done, you will begin to see a large number of errors from the load balancer. Since Drupal has not yet been installed, it performs a redirect to `install.php`, which the ALB assumes is a failure and restarts the service. Do not panic; this is expected. We will be addressing this in the next section.

## Drupal Installation

First, create a new admin password. Save this password somewhere secure.

Next, we'll use ECS to dispatch an installation task. There is a Drush task definition that is able to access all of the Drupal resources, and we'll use it to run an installation process.

```sh
#!/bin/bash

set -euo pipefail

# NB. The single quotes around the script are intentional; $WEBCMS_SITE_URL is defined in
# the ECS task configuration
script='
  drush --uri="$WEBCMS_SITE_URL" \
    site-install \
    --account-name=<admin user> \
    --account-mail=<admin mail> \
    --account-pass=<admin pass> \
    --existing-config \
    --yes

  drush --uri="$WEBCMS_SITE_URL" \
    import-blocks \
    --choice=safe

  drush --uri="$WEBCMS_SITE_URL" \
    cache-rebuild
'

# Overrides to pass to `aws ecs run-task'
overrides="$(
  jq -cn --arg script "$script" '{
  "containerOverrides": [
    {
      "name": "drush",
      "command": ["/bin/sh", "-ec", $script]
    }
  ]
}'
)"

family="webcms-drush-<env>"
cluster="webcms-cluster-<env>"

# Load Drush configuration from Terraform
config="$(terraform output drush-task-config)"

aws ecs run-task \
  --task-definition "$family" \
  --cluster "$cluster" \
  --network-configuration "$config" \
  --overrides "$overrides"
```

The task created by this script is run asynchronously. You can follow progress along in the CloudWatch logs, or poll the task by using `aws ecs describe-tasks` on the returned ARN. For an example, see `.buildkite/run-drush.sh`.

## Changing the Environment State

Now that Drupal has been installed, it is safe to change the `site-env-state` variable to `"run"`. This will activate the Memcached cache backend.

## Terraform Overrides

Most of the Terraform resources are easy to override (see [Override Files](https://www.terraform.io/docs/configuration/override.html) in the documentation). Some notes are included below.

### Drupal Egress Rules

The Drupal task's VPC security egress rules have been separated into individual rules. As a result, each rule can be overridden individually.

Here is an example, overriding the SMTP egress rule to point to a prefix list instead of the default CIDR range of `0.0.0.0/0`:

```terraform
# In security_override.tf

resource "aws_security_group_rule" "drupal_smtp_egress" {
  cidr_blocks     = null
  prefix_list_ids = ["pfx-12345-678"]
}
```
