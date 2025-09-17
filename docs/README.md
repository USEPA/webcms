# WebCMS CI/CD Documentation

## Overview

This directory documents the CI/CD and operations workflows for the EPA WebCMS. Automation uses GitLab CI/CD pipelines to build images with Kaniko, apply Terraform, and deploy Drupal to AWS ECS Fargate.

- Primary CI/CD: GitLab CI/CD (see .gitlab-ci.yml and .gitlab/)
- Infrastructure: Terraform modules in terraform/
- Runtime: ECS Fargate, RDS, ElastiCache (Memcached), S3, CloudFront

## Scope and Audience

- This docs folder focuses on CI/CD, deployments, environments, monitoring, and troubleshooting.
- Developer workstation setup and local development are covered in the root README.md.

## Documentation Structure

- Core
  - [Environment Overview](environment-overview.md) — Environment/site/lang model and branch mapping
  - [CI/CD Pipeline](cicd-pipeline.md) — GitLab pipeline structure and flow
  - [Deployment Guide](deployment-guide.md) — Step-by-step deployment procedures (GitLab + ECS)
  - [Configuration Reference](configuration-reference.md) — Variables, Terraform state conventions, and naming
- Operations
  - [Troubleshooting](troubleshooting.md) — Common failures and how to resolve
  - [Monitoring](monitoring.md) — Logs, metrics, and health indicators

## Quick Links

- GitLab CI configuration: .gitlab-ci.yml and .gitlab/
- Terraform modules: terraform/README.md
- Developer setup: root README.md
