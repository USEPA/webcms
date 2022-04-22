# WebCMS Terraform Modules

## Table of Contents

- [Table of Contents](#table-of-contents)
- [About](#about)
- [Conventions](#conventions)
  - [Terraform Backends](#terraform-backends)
  - [Terminology](#terminology)
  - [Documentation Structure](#documentation-structure)
  - [File Layout](#file-layout)
- [Modules](#modules)
  - [Network Reference Architecture](#network-reference-architecture)
  - [Infrastructure](#infrastructure)
  - [Database Initialization](#database-initialization)
  - [WebCMS Application Deployment](#webcms-application-deployment)

## About

This directory houses a number of modules that are used to deploy the Drupal 8 WebCMS. The modules are organized by a combination of function and security boundary; due to the nature of the security contexts in which the WebCMS' templates are executed, we must divide different pieces up to be run at separate times.

## Conventions

Each module follows a few common conventions.

### Terraform Backends

Different teams deploy these modules with different backends, so users of these modules must provide their own backend configuration. A file declaring the Terraform backend must be injected by the CI/CD system to configure this. For example, you may store the configuration in S3 or AWS Parameter Store and copy them to the directory, or use GitLab Enterprise's [environment variables](https://docs.gitlab.com/ee/ci/variables/#custom-cicd-variables-of-type-file) functionality to provide this.

Note that the database module is an exception to this rule. See its [README](database/README.md) for more.

### Terminology

These modules assume a three-level hierarchy of resources and deployments: environments, sites, and languages.

In these modules, the environment is the broadest scope. In the vast majority of cases, environment names are likely to be either production or pre-production. A single environment expects to be able to be deployed to its own VPC and not contend with other applications. In order to save costs, one environment will also only ever have one of each AWS service used by the WebCMS (e.g., one RDS or Elasticache cluster per enrivonment). Service-level permissions and namespacing will differentiate different users and sites from each other.

An environment has one or more sites. As an example, a pre-production environment can have both development and staging sites, but a production environment is likely to only have just the production sites. (Environment and sites can have the same names without ill effects.)

Sites are further broken down by the language they serve. We currently support two languages: English and Spanish. Thus, at a minimum, there will always be two WebCMS deployments to an environment (one each for the supported languages).

Resources are identified according to this hierarchy. For example, an environment-wide value may be named "WebCMS-preproduction-resource", but a site- and language-specific value would be named "WebCMS-preproduction-dev-en-resource". The network, infrastructure, and database modules all act on environment-wide values, but the webcms module is site- and language-specific.

### Documentation Structure

Each module's README is organized by roughly this structure:

* **About**: indicates what module this directory covers
* **Prerequisites**: anything that must be completed before the module should be run
* **Module inputs**: Terraform variables and AWS Parameter Store values that need to be set before running the module
* **Resources**: a high-level overview of what AWS resources are created
* **Module outputs**: what the module creates/outputs that may be of use to users or other modules
* **How to run**: special instructions for running a module, or steps that need to be completed alongside a Terraform run to further apply updates
* **Post-run steps**: actions to take after the first run (or re-initialization) of this module

### File Layout

Module files are broken down roughly by the AWS service being deployed. Permissions granted to each service are provided in that file. Files that define many resources of the same type are broken down by `#region`/`#endregion` markers to make navigation easier for editors that understand them.

## Modules

The modules are listed here in the order in which they should be run (or followed). Each subsequent module builds on the first, requiring resources and Parameter Store values for those created elements.

### Network Reference Architecture

**Directory:** [`network`](network) ([README](network/README.md))

This module defines the WebCMS' minimum VPC requirements. As mentioned in the README, we do not reuqire that this module be run directly - instead, the VPC should include the minimal public/private subnets and security groups documented in the Terraform files. The module can be applied in commercial AWS environments with no special needs (e.g., custom route tables or prefix lists) as a quickstart for the WebCMS.

### Infrastructure

**Directory:** [`infrastructure`](infrastructure) ([README](infrastructure/README.md))

This module defines the WebCMS' infrastructure. This module defines environment-wide storage resources such as Aurora and Elasticache clusters, compute resources such as an ECS cluster, and some site- and language-specific resources such as CloudWatch log groups and Secrets Manager secrets.

### Database Initialization

**Directory:** [`database`](database) ([README](database/README.md))

This module is a special database initialization module. Instead of defining an EC2-based infrastructure separate from the WebCMS' Fargate-based compute system, we instead define a Terraform module that can be run in a container to set up usernames and password for Drupal. This needs to be run in a container since it must be run inside the environment's VPC - it has to send SQL queries to the Aurora cluster, which does not have a publicly-accessible IP address. This module creates strong, random passwords for each site and language's user, and updates both the database and Secrets Manager with this information.

### WebCMS Application Deployment

**Directory:** [`webcms`](webcms) ([README](webcms/README.md))

This is the WebCMS' application deployment module. It acts on a per-site and per-language basis, allowing some finer-grained deployment workflows (e.g., only deploying the English dev site, or deploying both English and Spanish in parallel). The Terraform resources created by this module are fairly simple, but the surrounding deployment requires more work. See the README for more details.
