# Environment Overview

## Model

The WebCMS deployment model uses a three-level hierarchy:
- Environment: preproduction, production
- Site: dev | stage | main | live (site varies by branch/job)
- Language: en, es

Terraform and GitLab CI/CD combine these to create site- and language-specific deployments while sharing environment-wide infrastructure (RDS, ECS cluster, ElastiCache, etc.).

## Preproduction (current focus)

- ECS Cluster: webcms-preproduction (Container Insights enabled)
- Sites: dev (automatic from `development`), stage (manual templates on `live`), main/live (per rules)
- Languages: English (en) and Spanish (es) where defined
- Shared resources: RDS Aurora, ElastiCache Memcached, S3, ALB, CloudFront (optional per site/lang)

## Branch Mapping (GitLab)

- development → dev site (automatic)
- live → preproduction infrastructure (manual gates), staging templates available
- main → allows auto-apply for webcms per rules

## Naming Conventions (examples)

- ECS services: WebCMS-${environment}-${site}-${lang}
- ECR: ${account}.dkr.ecr.${region}.amazonaws.com/webcms-${environment}-drupal
- S3: webcms-${environment}-${site}-${lang}-uploads
- SSM Parameter Store:
  - /terraform/${environment}/network
  - /terraform/${environment}/infrastructure
  - /terraform/${environment}/${site}/${lang}

## DNS and Certificates

- Public endpoints fronted by ALB; CloudFront distributions per site/lang as needed
- Certificates via ACM (Terraform variables)

## Security

- AssumeRole providers per module (from SSM)
- Secrets in AWS Secrets Manager or SSM
- Task roles limited to required AWS APIs

## Monitoring

- CloudWatch Container Insights for ECS cluster/services
- CloudWatch Logs for containers; ALB target health and access logs (S3)
