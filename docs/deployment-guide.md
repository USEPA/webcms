# Deployment Guide (GitLab + ECS)

## Overview

This guide provides step-by-step instructions for deploying WebCMS using GitLab CI/CD pipelines and AWS ECS Fargate.

## Types of Deployments

1) Development deployment (automatic)
- Trigger: Push to `development`
- Actions: Build images (drupal, nginx, drush), deploy dev (en), run Drush updates

2) Preproduction infrastructure (manual)
- Trigger: Push to `live`
- Actions: init → validate → plan → apply (manual approval)

3) Staging deployment (templates; manual if enabled)
- Trigger: Push to `live` (templates prefixed with `.deploy:stage:*`)
- Actions: init → validate → plan → apply for en/es

## How to Deploy

1) Commit and push code
- `development`: triggers dev deploy
- `live`: triggers preproduction infra; staging templates available for manual promotion

2) Monitor pipelines in GitLab
- Verify Kaniko image builds succeed
- Confirm Terraform steps complete without errors
- Check Drush steps finish successfully for each language

3) Verify runtime (AWS)
- ECS service desired vs. running counts
- ALB target health (healthy/in-service)
- CloudWatch logs for application and nginx

## Manual Controls

- Retry a failed job: GitLab job retry button
- Manual gates: Use the play button on manual jobs (infrastructure apply, staging templates)

## Drush Updates

Drush updates run via the `update:en` stage using `ci/drush.js` and ECS RunTask:
- Applies pending database updates (updb)
- Synchronizes configuration (cim)
- Clears caches (cr)

To rerun updates:
- Retry the `update:dev:en` job in GitLab

## Rollback Procedures

Application rollback (ECS):
1. Identify prior working task definition revision
2. Update the ECS service to use that revision
3. Monitor ALB target health and CloudWatch logs

Infrastructure rollback (Terraform):
1. Revert infrastructure code to a known-good commit
2. Rerun infrastructure apply jobs on `live`

## Pre-Deployment Checklist
- Terraform plan reviewed (live infra)
- Database migrations safe to apply
- Configuration changes reviewed
- Monitoring/alerts in place
- Rollback plan identified

## Post-Deployment Verification
- ECS service is stable (desired = running)
- ALB targets are healthy
- No error spikes in CloudWatch logs
- Application functions validated by product owners
