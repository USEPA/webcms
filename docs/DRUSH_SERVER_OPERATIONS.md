# Drush Server Operations Guide

This document explains how developers can execute Drush commands on server environments (development and staging) in the EPA WebCMS deployment pipeline.

## Table of Contents

- [Overview](#overview)
- [Automated Drush Execution](#automated-drush-execution)
- [Manual Drush Execution Methods](#manual-drush-execution-methods)
  - [Method 1: Manual ECS Task Execution](#method-1-manual-ecs-task-execution)
  - [Method 2: Custom Drush Scripts](#method-2-custom-drush-scripts)
  - [Method 3: Direct AWS CLI Execution](#method-3-direct-aws-cli-execution)
- [Access Requirements](#access-requirements)
- [Environment Configuration](#environment-configuration)
- [Common Use Cases](#common-use-cases)
- [Limitations and Restrictions](#limitations-and-restrictions)
- [Troubleshooting](#troubleshooting)
- [Best Practices](#best-practices)
- [Security Considerations](#security-considerations)

---

## Overview

The EPA WebCMS uses **containerized ECS tasks** to execute Drush commands on server environments. Unlike traditional server setups, there is **no direct SSH access** to containers. All Drush operations are performed through:

- **Automated CI/CD pipeline execution** (recommended for standard operations)
- **Manual ECS task execution** (for custom/debug operations)
- **AWS CLI-based task management** (for advanced users)

The system is designed for **security, automation, and scalability** rather than interactive access.

---

## Automated Drush Execution

### How It Works

The GitLab CI/CD pipeline automatically runs Drush commands after successful deployments using the `ci/drush.js` script.

### Automatic Execution Triggers

| Branch | Environment | When It Runs |
|--------|-------------|--------------|
| `development` | Development | After successful deployment |
| `live` | Staging | After successful deployment (manual trigger) |

### Standard Automated Commands

The following Drush commands are executed automatically in sequence:

```bash
# Enable maintenance mode
drush --debug --uri="$WEBCMS_SITE_URL" sset system.maintenance_mode 1 --input-format=integer

# Clear all caches
drush --debug --uri="$WEBCMS_SITE_URL" cr

# Deploy configuration and run updates
drush --debug --uri="$WEBCMS_SITE_URL" deploy -y

# Disable maintenance mode
drush --debug --uri="$WEBCMS_SITE_URL" sset system.maintenance_mode 0 --input-format=integer

# Clear all caches again
drush --debug --uri="$WEBCMS_SITE_URL" cr
```

### Monitoring Automated Execution

1. **GitLab Pipeline Logs**: View real-time execution in GitLab CI/CD interface
2. **CloudWatch Logs**: Access detailed logs in AWS CloudWatch
3. **ECS Console**: Monitor task status and resource usage

---

## Manual Drush Execution Methods

### Method 1: Manual ECS Task Execution

**Skill Level**: Intermediate  
**Use Case**: Custom maintenance operations, debugging  

#### Prerequisites

- AWS CLI configured with appropriate credentials
- Access to the `ci/` directory in this repository
- Node.js 14+ installed locally

#### Step-by-Step Process

1. **Set Environment Variables**:

   ```bash
   export WEBCMS_ENVIRONMENT=preproduction
   export WEBCMS_SITE=dev
   export WEBCMS_LANG=en
   export WEBCMS_IMAGE_TAG=development-<commit-sha>
   export AWS_REGION=us-east-1
   ```

2. **Navigate to CI Directory**:

   ```bash
   cd ci
   ```

3. **Install Dependencies**:

   ```bash
   npm ci --production
   ```

4. **Execute Drush Script**:

   ```bash
   node drush.js
   ```

#### Environment Variable Reference

| Variable | Description | Example Values |
|----------|-------------|----------------|
| `WEBCMS_ENVIRONMENT` | Target environment | `preproduction`, `production` |
| `WEBCMS_SITE` | Site identifier | `dev`, `stage` |
| `WEBCMS_LANG` | Language code | `en`, `es` |
| `WEBCMS_IMAGE_TAG` | Docker image tag | `development-abc123` |
| `AWS_REGION` | AWS region | `us-east-1` |

### Method 2: Custom Drush Scripts

**Skill Level**: Advanced  
**Use Case**: Custom Drush commands, specialized maintenance  

#### Creating Custom Scripts

1. **Copy the Base Script**:

   ```bash
   cp ci/drush.js ci/drush-custom.js
   ```

2. **Modify the Drush Commands**:

   ```javascript
   const drushScript = dedent`
     drush --debug --uri="$WEBCMS_SITE_URL" status
     drush --debug --uri="$WEBCMS_SITE_URL" config:export
     drush --debug --uri="$WEBCMS_SITE_URL" user:unblock admin
     drush --debug --uri="$WEBCMS_SITE_URL" cr
   `;
   ```

3. **Execute Custom Script**:

   ```bash
   node drush-custom.js
   ```

#### Common Custom Commands

```javascript
// Database operations
const dbScript = dedent`
  drush --debug --uri="$WEBCMS_SITE_URL" sql:query "SELECT COUNT(*) FROM users"
  drush --debug --uri="$WEBCMS_SITE_URL" entity:updates
`;

// User management
const userScript = dedent`
  drush --debug --uri="$WEBCMS_SITE_URL" user:login admin
  drush --debug --uri="$WEBCMS_SITE_URL" user:unblock --name=username
`;

// Content operations
const contentScript = dedent`
  drush --debug --uri="$WEBCMS_SITE_URL" search-api:index
  drush --debug --uri="$WEBCMS_SITE_URL" queue:run
`;
```

### Method 3: Direct AWS CLI Execution

**Skill Level**: Expert  
**Use Case**: One-off commands, emergency operations  

#### Single Command Execution

```bash
aws ecs run-task \
  --cluster webcms-preproduction \
  --task-definition webcms-preproduction-dev-drush \
  --network-configuration "awsvpcConfiguration={subnets=[subnet-12345],securityGroups=[sg-67890]}" \
  --overrides '{
    "containerOverrides": [{
      "name": "drush",
      "command": ["drush", "--uri=$WEBCMS_SITE_URL", "status"]
    }]
  }'
```

#### Required AWS Information

You'll need to gather these values from Parameter Store or AWS Console:

- **Cluster Name**: `/webcms/${environment}/ecs/cluster-name`
- **Task Definition**: `webcms-${environment}-${site}-drush`
- **Subnet IDs**: `/webcms/${environment}/vpc/private-subnets`
- **Security Group**: `/webcms/${environment}/security-groups/drupal`

---

## Access Requirements

### GitLab Access

- **Purpose**: Monitor automated pipeline Drush executions
- **Permissions**: Developer role on GitLab project
- **What you can do**: View logs, pipeline status, artifacts

### AWS Console Access

- **Purpose**: View CloudWatch logs, ECS task status
- **Permissions**: Read access to CloudWatch, ECS services
- **What you can do**: Monitor task execution, view detailed logs

### AWS CLI Access

- **Purpose**: Manual ECS task execution
- **Required Permissions**:

  ```json
  {
    "Version": "2012-10-17",
    "Statement": [
      {
        "Effect": "Allow",
        "Action": [
          "ecs:RunTask",
          "ecs:DescribeTasks",
          "ssm:GetParameter",
          "iam:PassRole"
        ],
        "Resource": "*"
      }
    ]
  }
  ```

### Local Development Environment

- **Node.js**: Version 14 or higher
- **npm**: For installing CI script dependencies
- **Git**: Access to this repository

---

## Environment Configuration

### Development Environment (`dev`)

- **Branch**: `development`
- **Automatic Execution**: Yes
- **Manual Access**: Full access with appropriate permissions
- **Site URL**: <https://dev-www.epa.gov>

### Staging Environment (`stage`)

- **Branch**: `live`
- **Automatic Execution**: Manual trigger required
- **Manual Access**: Restricted, coordinate with DevOps
- **Site URL**: <https://stage-www.epa.gov>

### Language Variants

- **English**: `WEBCMS_LANG=en`
- **Spanish**: `WEBCMS_LANG=es`

---

## Common Use Cases

### 1. Configuration Import/Export

```bash
# Export current configuration
drush --uri="$WEBCMS_SITE_URL" config:export

# Import configuration changes
drush --uri="$WEBCMS_SITE_URL" config:import -y
```

### 2. Cache Management

```bash
# Clear all caches
drush --uri="$WEBCMS_SITE_URL" cache:rebuild

# Clear specific cache bins
drush --uri="$WEBCMS_SITE_URL" cache:clear render
```

### 3. User Management

```bash
# Generate one-time login link
drush --uri="$WEBCMS_SITE_URL" user:login admin

# Unblock user account
drush --uri="$WEBCMS_SITE_URL" user:unblock username
```

### 4. Database Operations

```bash
# Run pending database updates
drush --uri="$WEBCMS_SITE_URL" updatedb -y

# Check entity updates
drush --uri="$WEBCMS_SITE_URL" entity:updates
```

### 5. Search Index Management

```bash
# Reindex search content
drush --uri="$WEBCMS_SITE_URL" search-api:index

# Clear search index
drush --uri="$WEBCMS_SITE_URL" search-api:clear
```

### 6. Module Management

```bash
# Enable modules
drush --uri="$WEBCMS_SITE_URL" pm:enable module_name -y

# Check module status
drush --uri="$WEBCMS_SITE_URL" pm:status
```

---

## Limitations and Restrictions

### What You CANNOT Do ❌

1. **Interactive Shell Access**: No `drush shell` or interactive commands
2. **Real-time Execution**: All commands run as batch ECS tasks
3. **Direct Database Access**: No direct database connections for Drush
4. **File System Access**: Cannot directly access container file systems
5. **Container SSH**: No SSH access to running containers

### What You CAN Do ✅

1. **Batch Command Execution**: Run multiple commands in sequence
2. **Log Monitoring**: View detailed execution logs
3. **Status Checking**: Monitor task progress and completion
4. **Custom Scripts**: Create specialized Drush execution scripts
5. **Error Debugging**: Access failure logs and stack traces

---

## Troubleshooting

### Common Issues

#### Task Fails to Start

**Symptoms**: ECS task never transitions to "RUNNING" state
**Solutions**:

1. Check IAM permissions for task and execution roles
2. Verify image tag exists in ECR
3. Check VPC subnet and security group configuration

#### Drush Commands Fail

**Symptoms**: Task runs but Drush commands return errors
**Solutions**:

1. Check database connectivity
2. Verify site URL configuration
3. Review CloudWatch logs for specific error messages

#### Permission Denied Errors

**Symptoms**: "Access denied" or similar authentication errors
**Solutions**:

1. Verify AWS CLI credentials and permissions
2. Check that task role has necessary Drupal permissions
3. Ensure secrets are properly configured in Secrets Manager

### Log Access

#### CloudWatch Log Groups

- **Drush Logs**: `/webcms/${environment}/${site}/${lang}/drush`
- **Application Logs**: `/webcms/${environment}/${site}/${lang}/drupal`

#### GitLab Pipeline Logs

1. Navigate to GitLab project
2. Go to CI/CD → Pipelines
3. Click on specific pipeline run
4. View job logs for Drush execution steps

### Getting Help

1. **Check existing logs** in CloudWatch and GitLab
2. **Review Parameter Store** values for environment configuration
3. **Contact DevOps team** for infrastructure-related issues
4. **Check AWS service health** for regional service issues

---

## Best Practices

### Execution Timing

1. **Avoid peak traffic hours** for maintenance operations
2. **Coordinate with deployments** to prevent conflicts
3. **Use staging environment** for testing complex operations

### Script Development

1. **Test locally** with development environment first
2. **Use meaningful commit messages** when modifying CI scripts
3. **Document custom scripts** for team knowledge sharing

### Monitoring

1. **Always monitor task execution** until completion
2. **Check logs immediately** after task completion
3. **Verify site functionality** after maintenance operations

### Error Handling

1. **Use `--debug` flag** for detailed error information
2. **Include error handling** in custom scripts
3. **Have rollback procedures** for configuration changes

---

## Security Considerations

### Credential Management

- **Never hardcode secrets** in scripts or documentation
- **Use IAM roles** instead of access keys when possible
- **Rotate credentials regularly** according to security policies

### Access Control

- **Follow principle of least privilege** for AWS permissions
- **Log all manual Drush executions** for audit purposes
- **Coordinate sensitive operations** with security team

### Data Protection

- **Backup before major operations** (automatic in production)
- **Test destructive operations** in development first
- **Verify data integrity** after database operations

### Network Security

- **All operations use private subnets** for container execution
- **Database access is proxied** through RDS Proxy
- **No direct internet access** from Drush containers

---

## Additional Resources

### Documentation References

- [CI Scripts Documentation](ci/README.md)
- [Terraform WebCMS Module](terraform/webcms/README.md)
- [Repository Relationships](REPOSITORY_RELATIONSHIPS.md)

### External Links

- [Drush Commands Reference](https://www.drush.org/latest/commands/all/)
- [AWS ECS CLI Documentation](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/ECS_CLI.html)
- [AWS CloudWatch Logs](https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/WhatIsCloudWatchLogs.html)

### Support Contacts

- **GitLab Pipeline Issues**: Development Team
- **AWS Infrastructure Issues**: DevOps/Infrastructure Team  
- **Drupal Application Issues**: Development Team
- **Security/Access Issues**: Security Team

---

**Important**: This repository is a mirror of the primary `webcms` repository. All development work should happen in the `webcms` repository. This documentation is specific to deployment and operations workflows.
