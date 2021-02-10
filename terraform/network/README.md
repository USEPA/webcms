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

## Security Groups

## SSM Parameters
