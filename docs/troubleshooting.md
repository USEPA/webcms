# Troubleshooting Guide

## Overview

Common issues and resolutions for the WebCMS CI/CD and runtime on GitLab + ECS.

## Pipeline Issues (GitLab)

### Image builds (Kaniko)

- ECR auth failures: Ensure runner IAM role has ECR push permissions; verify repository exists
- Insufficient disk: Check runner host disk utilization; prune caches if needed

### Terraform

- State lock errors: Confirm no concurrent apply; unlock via DynamoDB only if safe
- Provider auth: Verify AssumeRole config from SSM providers parameter

### Drush Steps

- Task fails: Review CloudWatch log stream for the Drush ECS task
- Re-run: Retry the update job (e.g., update:dev:en) in GitLab

## Deployment Failures (ECS)

- Service doesn’t stabilize:
  1) Check ECS service events for task stop reasons
  2) Inspect container logs in CloudWatch
  3) Verify environment variables and Secrets/SSM references
  4) Check ALB target health and health check path

- Image not found:
  1) Confirm ECR image tag (WEBCMS_IMAGE_TAG) exists
  2) Verify task definition uses correct tag

## Database and Cache

- DB connection errors:
  1) Validate RDS endpoint and security groups
  2) Check Drupal DB credentials (Secrets Manager/SSM)
  3) Confirm network access from tasks (subnets/NACL/SG)

- Cache issues (Memcached):
  1) Confirm WEBCMS_CACHE_HOST
  2) Verify memcache module enabled and settings applied

## Performance and Errors

- Elevated error rates:
  1) Review application logs for stack traces
  2) Check ALB 5xx metrics
  3) Roll back to prior task definition if recent change introduced errors

- High CPU/memory:
  1) Inspect Container Insights
  2) Increase task size or scale out service
  3) Optimize application code/queries

## Rollbacks

- Application: Update service to earlier task definition revision
- Infrastructure: Revert code and re-apply via infrastructure pipeline

## Useful AWS CLI Commands

- Check ECS service events:

```bash
aws ecs describe-services --cluster webcms-preproduction --services WebCMS-preproduction-dev-en
```

- Describe tasks:

```bash
aws ecs list-tasks --cluster webcms-preproduction --service-name WebCMS-preproduction-dev-en
aws ecs describe-tasks --cluster webcms-preproduction --tasks <task-arn>
```

- ALB target health:

```bash
aws elbv2 describe-target-health --target-group-arn <arn>
```
