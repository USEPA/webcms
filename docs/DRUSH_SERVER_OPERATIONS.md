# Drush Server Operations Guide

This document explains how developers can execute Drush commands on server environments (development, staging, and production) in the EPA WebCMS deployment pipeline.

## Table of Contents

- [Overview](#overview)
- [Quick Reference](#quick-reference)
- [Automated Drush Execution](#automated-drush-execution)
- [Manual Drush Execution Methods](#manual-drush-execution-methods)
  - [Method 1: Manual ECS Task Execution](#method-1-manual-ecs-task-execution)
  - [Method 2: Custom Drush Scripts](#method-2-custom-drush-scripts)
  - [Method 3: Direct AWS CLI Execution](#method-3-direct-aws-cli-execution)
  - [Method 4: AWS Web Interface Execution](#method-4-aws-web-interface-execution)
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
- **AWS Web Console interface** (for GUI-based task execution and monitoring)

The system is designed for **security, automation, and scalability** rather than interactive access.

---

## Quick Reference

- Roles and environments:
  - [Preproduction/Staging](#staging-environment-stage): Role [www-dev](#aws-console-access), Cluster [webcms-preproduction](#staging-environment-stage)
  - [Production](#production-environment-prod): Role [www-prod](#aws-console-access), Cluster [webcms-production](#production-environment-prod)

- Language availability:
  - [Development](#development-environment-dev): English (`en`) only
  - [Staging](#staging-environment-stage)/[Production](#production-environment-prod): English (`en`) and Spanish (`es`)

- Task definition families (see [Method 4](#method-4-aws-web-interface-execution)):
  - [Development](#development-environment-dev): `webcms-preproduction-dev-en-drush`
  - [Staging](#staging-environment-stage): `webcms-preproduction-stage-en-drush`, `webcms-preproduction-stage-es-drush`
  - [Production](#production-environment-prod): `webcms-production-prod-en-drush`, `webcms-production-prod-es-drush`

- Primary site URLs:
  - [Development (EN)](#development-environment-dev): <https://dev-www.epa.gov>
  - [Staging (EN)](#staging-environment-stage): <https://stage-www.epa.gov>
  - [Staging (ES)](#staging-environment-stage): <https://stage-espanol.epa.gov/>
  - [Production (EN)](#production-environment-prod): <https://www.epa.gov>
  - [Production (ES)](#production-environment-prod): <https://espanol.epa.gov/>

---

## Automated Drush Execution

### How It Works

The GitLab CI/CD pipeline automatically runs Drush commands after successful deployments using the `ci/drush.js` script.

### Automatic Execution Triggers

| Branch | Environment | When It Runs |
|--------|-------------|--------------|
| `development` | Development | After successful deployment |
| `live` | Staging | After successful deployment (manual trigger) |

Note on languages:

- Development environment: English (`en`) only; Spanish (`es`) is not deployed.
- Staging and Production: English (`en`) and Spanish (`es`) run in parallel.

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
      "name": "drupal",
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

### Method 4: AWS Web Interface Execution

**Skill Level**: Intermediate  
**Use Case**: GUI-based task execution, visual monitoring, one-off maintenance commands  

This method allows you to run Drush commands directly through the AWS Management Console without needing the AWS CLI or local development environment setup.

#### Prerequisites

- AWS Console access with appropriate IAM permissions
- Correct AWS role for target environment:
  - **Preproduction/Staging**: `www-dev` role
  - **Production (prod)**: `www-prod` role
- Knowledge of target environment and language requirements

#### Step-by-Step Process

##### 1. Access AWS Console and Navigate to ECS

1. **Log into AWS Console** using the correct role for your target environment
2. **Navigate to ECS service** (Elastic Container Service)
3. **Ensure correct region** is selected (typically `us-east-1`)

##### 2. Select Target Cluster

1. **Click on "Clusters"** in the ECS dashboard
2. **Select the appropriate cluster** based on your target environment:
   - **Preproduction/Staging**: `webcms-preproduction` cluster
   - **Production**: `webcms-production` cluster
   - Note: The `www-dev` role operates within the preproduction/staging environment.

##### 3. Initiate New Task

1. **Click on the "Tasks" tab** within the cluster page
2. **Click "Run new task"** button to open the task configuration wizard

##### 4. Configure Task Definition

1. **Launch Type**: Keep default (usually "Fargate" or "EC2" depending on cluster configuration)
2. **Task Definition Family**: Select the appropriate Drush task definition:
   - **Development (English only)**: `webcms-preproduction-dev-en-drush` (Spanish is not available in Development)
   - **Staging**: `webcms-preproduction-stage-en-drush` (English), `webcms-preproduction-stage-es-drush` (Spanish)
   - **Production**: `webcms-production-prod-en-drush` (English), `webcms-production-prod-es-drush` (Spanish)
3. **Revision**: Use "LATEST" unless you need a specific version

##### 5. Configure Networking

1. **VPC**: Select the correct VPC for the environment
   - Usually named `webcms-${environment}-vpc`
2. **Subnets**: Select **only application subnets** (subnets with "app" in the name)
   - Example names: `webcms-preproduction-app-subnet-1a`, `webcms-preproduction-app-subnet-1b`
   - **Important**: Avoid public or database subnets
3. **Security Groups**: Select `Customer-Drupal` security group
4. **Public IP**: **Turn OFF** (set to "DISABLED")
   - This is crucial for security - Drush tasks should run in private subnets only

##### 6. Configure Container Overrides

1. **Expand "Container overrides" section**
2. **Find the "drupal" container** (not "drush" - this is the container name within the task)
3. **Click on the "drupal" container** to expand its override options
4. **In the "Command override" field**, enter your Drush command with the following format:

   ```command
   drush,--uri=$WEBCMS_SITE_URL,your-command,--option1,value1,--option2
   ```

   **Important**: Use **commas instead of spaces** to separate command parts

##### 7. Example Commands

| Purpose | Command Override |
|---------|------------------|
| **Site Status** | `drush,--uri=$WEBCMS_SITE_URL,status` |
| **Clear Cache** | `drush,--uri=$WEBCMS_SITE_URL,cache:rebuild` |
| **Config Import** | `drush,--uri=$WEBCMS_SITE_URL,config:import,-y` |
| **User Login Link** | `drush,--uri=$WEBCMS_SITE_URL,user:login,admin` |
| **Run Updates** | `drush,--uri=$WEBCMS_SITE_URL,updatedb,-y` |
| **Enable Module** | `drush,--uri=$WEBCMS_SITE_URL,pm:enable,module_name,-y` |
| **SQL Query** | `drush,--uri=$WEBCMS_SITE_URL,sql:query,"SELECT COUNT(*) FROM users"` |

##### 8. Launch and Monitor Task

1. **Review configuration** to ensure all settings are correct
2. **Click "Run Task"** to launch the Drush execution
3. **Monitor task status** on the Tasks tab:
   - **PENDING**: Task is being scheduled
   - **RUNNING**: Task is executing
   - **STOPPED**: Task completed (check exit code)

##### 9. View Execution Logs

1. **Click on the task** in the Tasks list
2. **Go to the "Logs" tab** to view real-time output
3. **Check CloudWatch logs** for detailed execution information:
   - Log Group: `/webcms/${environment}/${site}/${lang}/drush`
   - Log Stream: Will be named with task ID and timestamp

#### Important Notes and Best Practices

##### Command Formatting Rules

- **Always use commas**: Replace all spaces with commas in the command override
- **Quote handling**: For SQL queries or complex strings, wrap in double quotes and escape as needed
- **Boolean flags**: Include flags as separate comma-delimited tokens (for example `,-y`)
- **Multiple options**: Each option and its value should be comma-separated

##### Security Considerations

- **Never use public subnets** for Drush tasks
- **Always disable public IP** assignment
- **Use Customer-Drupal security group** to ensure proper database access
- **Monitor task execution** to completion to ensure no hanging processes

##### Environment-Specific Configuration

| Environment | Cluster | Task Definition Pattern | VPC Pattern |
|-------------|---------|------------------------|-------------|
| **Development** | `webcms-preproduction` | `webcms-preproduction-dev-{lang}-drush` | `webcms-preproduction-vpc` |
| **Staging** | `webcms-preproduction` | `webcms-preproduction-stage-{lang}-drush` | `webcms-preproduction-vpc` |
| **Production** | `webcms-production` | `webcms-production-prod-{lang}-drush` | `webcms-production-vpc` |

##### Troubleshooting Web Interface Issues

**Task Fails to Start:**

- Verify correct task definition is selected
- Check that app subnets are selected (not public/db subnets)
- Ensure security group allows database connectivity
- Confirm IAM permissions for task execution role

**Command Fails:**

- Check command formatting (commas instead of spaces)
- Verify Drush command syntax is correct
- Review CloudWatch logs for specific error messages
- Ensure site URL environment variable is properly set

**No Output/Logs:**

- Wait for task to fully start (can take 1-2 minutes)
- Check CloudWatch log group permissions
- Verify log group exists for the environment/language combination
- Try refreshing the console page

##### Advantages of Web Interface Method

- **Visual monitoring**: Easy to see task status and logs in real-time
- **No local setup**: No need for AWS CLI or development environment
- **Guided configuration**: Web interface validates most configuration options
- **Accessible**: Can be used from any device with web browser access
- **Audit trail**: AWS Console actions are logged for security compliance

##### Limitations

- **Single command execution**: Cannot run multiple commands in sequence like CI scripts
- **Manual process**: Requires clicking through multiple screens
- **Command formatting**: Comma-separated format can be error-prone for complex commands
- **No automation**: Each execution requires manual intervention

---

## Access Requirements

### GitLab Access

- **Purpose**: Monitor automated pipeline Drush executions
- **Permissions**: Developer role on GitLab project
- **What you can do**: View logs, pipeline status, artifacts

### AWS Console Access

- **Purpose**: View CloudWatch logs, ECS task status, execute Drush tasks via web interface
- **Required Roles**:
  - **Preproduction/Staging**: `www-dev` role
  - **Production**: `www-prod` role
- **Permissions**: Read/write access to ECS services, CloudWatch, task execution
- **What you can do**: Monitor task execution, view detailed logs, run Drush tasks through GUI

### AWS CLI Access

- **Purpose**: Manual ECS task execution via command line
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
          "ecs:ListTasks",
          "ecs:DescribeTaskDefinition",
          "ecs:ListClusters",
          "ssm:GetParameter",
          "ssm:GetParameters",
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents",
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
- **Languages**: English only (Spanish is not deployed to Development)
- **Site URL**: <https://dev-www.epa.gov>

### Staging Environment (`stage`)

- **Branch**: `live`
- **Automatic Execution**: Manual trigger required
- **Manual Access**: Restricted, coordinate with DevOps
- **Languages**: English and Spanish (Spanish is available starting in Staging)
- **AWS Role**: `www-dev` (preproduction/staging)
- **Site URL**: <https://stage-www.epa.gov>
- **Spanish Site URL**: <https://stage-espanol.epa.gov/>

### Language Variants

- **English**: `WEBCMS_LANG=en`
- **Spanish**: `WEBCMS_LANG=es`

### Production Environment (`prod`)

- **Branch**: N/A (coordinate with DevOps for release process)
- **Automatic Execution**: As defined per release; manual approval typically required
- **Manual Access**: Restricted; operations via approved roles only
- **Languages**: English and Spanish
- **AWS Role**: `www-prod`
- **Site URL**: <https://www.epa.gov>
- **Spanish Site URL**: <https://espanol.epa.gov/>

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
