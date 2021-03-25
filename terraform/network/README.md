# WebCMS VPC Reference Architecture

## Table of Contents

- [Table of Contents](#table-of-contents)
- [About](#about)
- [Prerequisites](#prerequisites)
  - [Terraform Backend](#terraform-backend)
- [Module Inputs](#module-inputs)
  - [Variables](#variables)
  - [Parameter Store](#parameter-store)
- [Resources](#resources)
  - [Subnets](#subnets)
  - [Security Groups](#security-groups)
- [Module Outputs](#module-outputs)
- [How to Run](#how-to-run)
- [Post-Run Steps](#post-run-steps)

## About

This module serves as a light "reference architecture" for the WebCMS' VPC needs. It is oriented towards a commercial AWS with no special considerations.

The intention of this module is not necessarily to be run, but instead to document the WebCMS' minimum requirements for subnetting, security groups, and Parameter Store parameters.

## Prerequisites

### Terraform Backend

See the [parent directory's README](../) for instructions on using a backend for remote state and locking.

## Module Inputs

### Variables

- `aws_region` - The AWS region in which we are building. Used to initialize the AWS provider.
- `environment` - Since this VPC can cover multiple deployments of the WebCMS, we use `environment` as the differentiator. For example, a preproduction VPC could host both development and staging sites.
- `tags` - Any key/value tags to apply to AWS resources created by this template (e.g., for cost tracking purposes).
- `az_count` - Number of availability zones this VPC spans

### Parameter Store

This module does not read Parameter Store.

## Resources

### Subnets

This module assumes two sets of subnets: private and public. The private subnets are used to launch almost all resources (RDS, Elasticsearch, Elasticache, and Fargate containers), and the public subnets are used only to deploy load balancers.

### Security Groups

This module creates a number of security groups for the various resources in the VPC. A summary is below; see [security.tf](security.tf) for full details.

- The Aurora cluster permits ingress from the RDS proxy and the Terraform startup task.
- Elasticsearch, RDS proxy, and Elasticache permit ingress from Drupal tasks on their well-known ports (respectively: 443, 3306, and 11211).
- The Drupal task permits ingress from the Traefik router service.
- Drupal is permitted egress on ports 80 and 443, as well as outbound SMTP. This reference module uses port 587, but the actual port may differ in other environments.
- The Traefik router permits ingress from public subnets. Since network load balancers don't support security groups, this is the best we can do.

## Module Outputs

In [parameters.tf](parameters.tf) we list the Parameter Store parameters that other Terraform runs will reference. The Terraform module in [infrastructure](../infrastructure) expects to be able to read these values. We use Parameter store for two reasons:

1. The values are machine-readable identifiers and thus prone to typos if curated by hand, and
2. These identifiers are unlikely to change (if they ever do).

The parameters are stored under the Parameter Store path `/webcms/${var.environment}/`, and this module creates the following parameters:

- VPC parameters:
  - `/webcms/${var.environment}/vpc/id`: The VPC ID
  - `/webcms/${var.environment}/vpc/public-subnets`: A comma-separated list of IDs for the public subnets
  - `/webcms/${var.environment}/vpc/private-subnets`: As above, but for the private subnets
  - `/webcms/${var.environment}/vpc/public-cidrs`: A comma-separated list of the public subnets' CIDR ranges
  - `/webcms/${var.environment}/vpc/private-cidrs`: As above, but for the private subnets
- Security group parameters:
  - `/webcms/${var.environment}/security-groups/database`: ID of the RDS security group
  - `/webcms/${var.environment}/security-groups/proxy`: ID of the RDS proxy security group
  - `/webcms/${var.environment}/security-groups/elasticsearch`: ID of the Elasticsearch security group
  - `/webcms/${var.environment}/security-groups/memcached`: ID of the ElastiCache security group
  - `/webcms/${var.environment}/security-groups/drupal`: ID of the Drupal task security group
  - `/webcms/${var.environment}/security-groups/traefik`: ID of the Traefik router security group
  - `/webcms/${var.environment}/security-groups/terraform-database`: ID of the database intialization security group

## How to Run

This module can be run in any environment provided that the entity running the module has permission to create or modify VPC resources in the relevant AWS account.

## Post-Run Steps

There are no special post-run steps for this module.
