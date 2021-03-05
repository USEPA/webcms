# WebCMS Database Initialization

## Table of Contents

- [Table of Contents](#table-of-contents)
- [About](#about)
- [Prerequisites](#prerequisites)
- [Module Inputs](#module-inputs)
  - [Variables](#variables)
  - [Parameter Store](#parameter-store)
  - [Environment Variables](#environment-variables)
- [Resources](#resources)
  - [Database Resources](#database-resources)
  - [Secrets Manager Versions](#secrets-manager-versions)
- [Module Outputs](#module-outputs)
- [How to Run](#how-to-run)
  - [Build Image](#build-image)
  - [Run Task](#run-task)
- [Post-Run Steps](#post-run-steps)

## About

This is a Terraform module that performs a special one-time initialization task to create and populate database credentials for Drupal. Due to the need to connect to Aurora directly, this module is written such that it can be packaged into a Docker image and launched into this environment's VPC.

## Prerequisites

The [infrastructure module](../infrastructure) must have been successfully deployed.

## Module Inputs

Inputs to this module are bound automatically during the definition of the initialization task in the infrastructure module. We include the documentation here more for advisory than usage purposes.

### Variables

- Provider variables
  - `aws_region`: The AWS region in which this module is running
- Database credentials
  - `mysql_endpoint`: The connection string for the Aurora cluster. This cannot be the endpoint of the RDS proxy; the proxy does not have access to the root database credentials and thus will refuse authentication attempts by this module.
  - `mysql_credentials`: The username and password for the cluster's root user. This must be an object like `{ username, password }` (the default format for credentials in Secrets Manager).
- Site data
  - `sites`: A map of `(site, language)` pairs to information about the site's credentials. The keys of the map are in the format `"<site>-<lang>"` (e.g., `"dev-es"`) and the values are:
    - `name`: the name of the site (e.g., `"dev"`)
    - `lang`: the language (either `"en"` or `"es"`)
    - `d8`: the ARN of the Secrets Manager secret for the Drupal 8 database credentials.
    - `d7`: as `d8` above, but applies to the Drupal 7 database. This is used for content migration.

### Parameter Store

This module does not read from Parameter Store.

### Environment Variables

Because Terraform does not allow variable references in backend configuration, the name of the S3 storage backend and the DynamoDB locks table are provided as environment varibles named `$BACKEND_STORAGE` and `$BACKEND_LOCKS`, respectively. See the Terraform docs on [partial configuration](https://www.terraform.io/docs/language/settings/backends/configuration.html#partial-configuration) for more.

## Resources

### Database Resources

For each entry in the `sites` variable, we create the following resources in Aurora:
- A database,
- A user, and
- An `ALL PRIVILEGES` grant to that user.

This process is applied for both the current Drupal 8 site and the previous Drupal 7 site. The latter is used to hold content for migration, and can be left empty if no migrations will be run for that site.

Passwords for the users are generated using Terraform's [`random_password` resource](https://registry.terraform.io/providers/hashicorp/random/latest/docs/resources/password). Note that these values are stored in state; access to the Terraform state bucket by anything other than automated Terraform runs should be strictly controlled.

### Secrets Manager Versions

In order to allow Drupal to access the newly-initialized users and databases, this module also writes the username and password into the relevant Secrets Manager secrets. The information is promoted to the `AWSCURRENT` version stage, making it the default version that ECS tasks will use when binding credentials. See [`aws_secrets_manager_secret_version`](https://registry.terraform.io/providers/hashicorp/aws/latest/docs/resources/secretsmanager_secret_version) in the AWS provider documentation for more information.

## Module Outputs

This module does not have any outputs outside of the resources it creates.

## How to Run

This module is designed to be run as an ECS task on Fargate. There are two steps that must be completed: a Docker build and a manual triggering of the ECS task.

### Build Image

Using the ECR repository URL from the infrastructure module, build and push the Docker image. A sample shell script is below (not that you will need to [authenticate with ECR](https://docs.aws.amazon.com/AmazonECR/latest/userguide/registry_auth.html) first):

```sh
#!/bin/bash

set -euo

# Populate this from the infrastructure module's outputs
repository_url="..."

docker build terraform/database --tag "$repository_url:latest"
docker push "$repository_url:latest"
```

### Run Task

As such, we need to use the ECS `RunTask` API to launch the task. The `RunTask` outputs a task ARN which can be used to view the task status in the ECS console or polled via the relevant APIs. (Note: the user or entity that runs the task must have `iam:PassRole` permission for both the task and execution roles associated with this module. See the [AWS documentation](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_roles_use_passrole.html) for more information.)

First, record the following outputs from the infrastructure module:

1. The cluster name
2. The task VPC configuration
3. The task definition name (also known as the definition's family)

Next, use the `RunTask` operation. We will be using the AWS CLI's [`aws ecs run-task`](https://awscli.amazonaws.com/v2/documentation/api/latest/reference/ecs/run-task.html) command in a sample shell script:

```sh
#!/bin/bash

set -euo

# Populate these from the infrastructure module's outputs
cluster_name="..."
vpc_configuration="..."
task_definition="..."

aws ecs run-task \
  --cluster "$cluster_name" \
  --task-definition "$task_definition" \
  --network-configuration "$network_configuration"
```

It is not likely to be worth the effort of automating the running of this task beyond the above shell script, as this module (and therefore its task) only (re-)initializes database passwords.

## Post-Run Steps

There are no special post-run steps for this module. It may be prudent to rotate the root database password in order to prevent that value from being stored in any Terraform module states.
