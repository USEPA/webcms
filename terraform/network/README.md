# WebCMS VPC Reference Architecture

## Table of Contents

- [Table of Contents](#table-of-contents)
- [Variables](#variables)
- [Subnets](#subnets)
- [Security Groups](#security-groups)
- [SSM Parameters](#ssm-parameters)

## Variables

- `aws_region` - The AWS region in which we are building. Used to initialize the AWS provider.
- `environment` - Since this VPC can cover multiple deployments of the WebCMS, we use `environment` as the differentiator. For example, a preproduction VPC could host both development and staging sites.
- `tags` - Any key/value tags to apply to AWS resources created by this template (e.g., for cost tracking purposes).
- `az_count` - Number of availability zones this VPC spans

## Subnets

This module assumes two sets of subnets: private and public. The private subnets are used to launch almost all resources (RDS, Elasticsearch, Elasticache, and Fargate containers), and the public subnets are used only to deploy load balancers.

## Security Groups

This module creates a number of security groups for the various resources in the VPC. A summary is below; see [security.tf](security.tf) for full details.

- The Aurora cluster permits ingress from the RDS proxy and the Terraform startup task.
- Elasticsearch, RDS proxy, and Elasticache permit ingress from Drupal tasks on their well-known ports (respectively: 443, 3306, and 11211).
- The Drupal task permits ingress from the Traefik router service.
- Drupal is permitted egress on ports 80 and 443, as well as outbound SMTP. This reference module uses port 587, but the actual port may differ in other environments.
- The Traefik router permits ingress from public subnets. Since network load balancers don't support security groups, this is the best we can do.

## SSM Parameters

In [parameters.tf](parameters.tf) we list the Parameter Store parameters that other Terraform runs will reference.

Under `/webcms/${var.environment}/vpc`, we store the VPC-related parameters (VPC ID, public and private subnets, and public and private CIDRs). Under `/webcms/${var.environment}/security-groups`, we store the security group IDs for each named security group.
