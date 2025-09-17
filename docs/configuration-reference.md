# Configuration Reference

## Overview

This document provides a comprehensive reference for all configuration variables, resource naming conventions, and settings used in the WebCMS deployment.

## GitLab CI/CD Variables

### Global Variables

| Variable | Description | Example Value | Usage |
|----------|-------------|---------------|--------|
| `CI_COMMIT_REF_SLUG` | Branch name (sanitized) | `development` | Image tagging |
| `CI_COMMIT_SHA` | Git commit hash | `a1b2c3d4` | Image versioning |
| `TF_ROOT` | Terraform root directory | `terraform/${TF_MODULE}` | Terraform operations |
| `TF_MODULE` | Terraform module name | `webcms` or `infrastructure` | Module selection |

### Environment-Specific Variables

#### Development Environment

```yaml
WEBCMS_SITE: dev
WEBCMS_LANG: en
WEBCMS_ENVIRONMENT: preproduction
WEBCMS_TARGET: [drupal, nginx, drush]
```

#### Production Environment

```yaml
WEBCMS_SITE: prod
WEBCMS_LANG: [en, es, fr]
WEBCMS_ENVIRONMENT: production
WEBCMS_TARGET: [drupal, nginx, drush]
```

### Docker Build Variables

| Variable | Description | Values |
|----------|-------------|---------|
| `WEBCMS_TARGET` | Docker build target | `drupal`, `nginx`, `drush` |
| `WEBCMS_REPO_URL` | ECR repository URL | `${AWS_ACCOUNT}.dkr.ecr.${AWS_REGION}.amazonaws.com` |
| `KANIKO_CACHE_ARGS` | Kaniko cache configuration | `--cache --cache-repo=$WEBCMS_REPO_URL/webcms-$WEBCMS_ENVIRONMENT-cache` |

### AWS Configuration

| Variable | Description | Environment |
|----------|-------------|-------------|
| `AWS_DEFAULT_REGION` | Primary AWS region | `us-east-1` |
| `AWS_REGION_WEST` | Secondary AWS region | `us-west-2` |
| `AWS_ACCOUNT_ID` | AWS account identifier | Account-specific |

## Resource Naming Conventions

### Kubernetes Resources

#### Namespace Naming

- **Pattern**: `webcms-{site}-{lang}`
- **Examples**:
  - `webcms-dev-en` (development)
  - `webcms-prod-en` (production English)
  - `webcms-prod-es` (production Spanish)

#### Deployment Naming

- **Pattern**: `{service}-{site}-{lang}`
- **Examples**:
  - `drupal-dev-en`
  - `nginx-prod-es`
  - `metrics-dev-en`

### Terraform State

#### State Key Naming

- **Pattern**: `{site}-webcms-{lang}`
- **Examples**:
  - `dev-webcms-en.tfstate`
  - `prod-webcms-es.tfstate`
  - `staging-webcms-fr.tfstate`

#### State Backend Configuration

```hcl
terraform {
  backend "s3" {
    bucket = "webcms-terraform-state"
    key    = "${site}-webcms-${lang}.tfstate"
    region = "us-east-1"
    encrypt = true
    dynamodb_table = "webcms-terraform-locks"
  }
}
```

### AWS Resources

#### EKS Cluster Naming

- **Pattern**: `webcms-{environment}-{purpose}`
- **Examples**:
  - `webcms-preproduction-dev-cluster`
  - `webcms-production-cluster`

#### ECR Repository Naming

- **Pattern**: `webcms-{environment}-{service}`
- **Examples**:
  - `webcms-preproduction-drupal`
  - `webcms-production-nginx`
  - `webcms-preproduction-metrics`

#### RDS Instance Naming

- **Pattern**: `webcms-{environment}-{lang}-db`
- **Examples**:
  - `webcms-preproduction-en-db`
  - `webcms-production-en-db`

#### S3 Bucket Naming

- **Pattern**: `webcms-{environment}-{purpose}`
- **Examples**:
  - `webcms-preproduction-assets`
  - `webcms-production-backups`
  - `webcms-terraform-state`

## Application Configuration

### Drupal Settings

#### Database Configuration

```php
$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASS'),
  'host' => getenv('DB_HOST'),
  'port' => getenv('DB_PORT') ?: '3306',
  'driver' => 'mysql',
  'prefix' => '',
];
```

#### Cache Configuration

```php
$settings['cache']['default'] = 'cache.backend.redis';
$settings['redis.connection']['host'] = getenv('REDIS_HOST');
$settings['redis.connection']['port'] = getenv('REDIS_PORT') ?: '6379';
```

#### CloudFront Configuration

```php
// CloudFront distribution ID for cache invalidation
$settings['aws.distributionid'] = getenv('WEBCMS_CF_DISTRIBUTIONID');

// CloudFront module configuration
$config['cloudfront_cache_path_invalidate.settings']['distribution_id'] = getenv('WEBCMS_CF_DISTRIBUTIONID');
```

**Environment-Specific CloudFront Settings:**

- **Development**: `WEBCMS_CF_DISTRIBUTIONID=""` (disabled)
- **Staging**: `WEBCMS_CF_DISTRIBUTIONID="E1A2B3C4D5E6F7"` (staging distribution)
- **Production**: `WEBCMS_CF_DISTRIBUTIONID="E7F6E5D4C3B2A1"` (production distribution)

### Environment Variables

#### Required Environment Variables

```bash
# Database
DB_HOST=webcms-dev-en-db.cluster-xyz.us-east-1.rds.amazonaws.com
DB_NAME=drupal_dev
DB_USER=drupal_user
DB_PASS=secure_password

# Cache
REDIS_HOST=webcms-dev-en-redis.xyz.cache.amazonaws.com
REDIS_PORT=6379

# Application
DRUPAL_HASH_SALT=random_hash_salt_value
TRUSTED_HOST_PATTERNS=^dev\.webcms\.example\.com$

# File Storage
S3_BUCKET=webcms-preproduction-assets
S3_REGION=us-east-1
```

## Container Configuration

### Docker Image Tags

#### Tagging Strategy

- **Format**: `{branch}-{commit_sha}`
- **Examples**:
  - `development-a1b2c3d4`
  - `live-x9y8z7w6`
  - `main-m5n4o3p2`

#### Multi-Region Tagging

```bash
# Primary region (us-east-1)
${AWS_ACCOUNT}.dkr.ecr.us-east-1.amazonaws.com/webcms-preproduction-drupal:development-a1b2c3d4

# Secondary region (us-west-2)
${AWS_ACCOUNT}.dkr.ecr.us-west-2.amazonaws.com/webcms-preproduction-drupal:development-a1b2c3d4
```

### Kubernetes Deployment Configuration

#### Resource Limits

```yaml
resources:
  requests:
    cpu: "100m"
    memory: "256Mi"
  limits:
    cpu: "500m"
    memory: "512Mi"
```

#### Health Checks

```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 80
  initialDelaySeconds: 30
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /ready
    port: 80
  initialDelaySeconds: 5
  periodSeconds: 5
```

## Terraform Variables

### Infrastructure Module Variables

```hcl
variable "environment" {
  description = "Environment name (preproduction, production)"
  type        = string
  default     = "preproduction"
}

variable "region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

variable "cluster_version" {
  description = "EKS cluster version"
  type        = string
  default     = "1.27"
}
```

### WebCMS Module Variables

```hcl
variable "site" {
  description = "Site identifier (dev, staging, prod)"
  type        = string
}

variable "language" {
  description = "Language code (en, es, fr)"
  type        = string
  default     = "en"
}

variable "image_tag" {
  description = "Docker image tag"
  type        = string
}
```

## Network Configuration

### VPC Configuration

- **CIDR Block**: `10.0.0.0/16`
- **Public Subnets**: `10.0.1.0/24`, `10.0.2.0/24`
- **Private Subnets**: `10.0.10.0/24`, `10.0.20.0/24`
- **Database Subnets**: `10.0.100.0/24`, `10.0.200.0/24`

### Security Groups

#### EKS Node Group

```hcl
ingress {
  from_port   = 443
  to_port     = 443
  protocol    = "tcp"
  cidr_blocks = ["10.0.0.0/16"]
}

ingress {
  from_port   = 80
  to_port     = 80
  protocol    = "tcp"
  cidr_blocks = ["10.0.0.0/16"]
}
```

#### RDS Database

```hcl
ingress {
  from_port       = 3306
  to_port         = 3306
  protocol        = "tcp"
  security_groups = [aws_security_group.eks_nodes.id]
}
```

## Monitoring Configuration

### CloudWatch Metrics

- **Namespace**: `WebCMS/{environment}`
- **Metrics**:
  - `drupal.response_time`
  - `drupal.error_rate`
  - `kubernetes.pod_cpu_usage`
  - `kubernetes.pod_memory_usage`

### New Relic Configuration

```yaml
newrelic:
  license_key: ${NEW_RELIC_LICENSE_KEY}
  app_name: WebCMS-${environment}-${site}-${lang}
  monitor_mode: true
  log_level: info
```

## Security Configuration

### IAM Roles

#### EKS Service Role

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "eks.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
```

#### Node Group Instance Role

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
```

### RBAC Configuration

#### Service Account

```yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: webcms-service-account
  namespace: webcms-dev-en
  annotations:
    eks.amazonaws.com/role-arn: arn:aws:iam::ACCOUNT:role/webcms-service-role
```

## Backup Configuration

### Database Backups

- **Schedule**: Daily at 2:00 AM UTC
- **Retention**: 7 days for development, 30 days for production
- **Location**: S3 bucket `webcms-{environment}-backups`

### Application Backups

- **Files**: Drupal uploaded files
- **Schedule**: Daily at 3:00 AM UTC
- **Location**: S3 bucket with versioning enabled
