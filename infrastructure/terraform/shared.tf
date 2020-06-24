# This file is for values shared across multiple other *.tf files.

data "aws_caller_identity" "current" {}

# We use separate definitions for the web-facing Drupal tasks and scheduled Drush cron
# scripts for a few reasons (such as avoiding spawning nginx), so we share the values
# here.
locals {
  # Plaintext environment variables for Drupal containers
  drupal-environment = [
    { name = "WEBCMS_S3_BUCKET", value = aws_s3_bucket.uploads.bucket },
    { name = "WEBCMS_S3_REGION", value = var.aws-region },
    { name = "WEBCMS_SITE_URL", value = "https://${var.site-hostname}" },
    { name = "WEBCMS_ENV_STATE", value = var.site-env-state },
    { name = "WEBCMS_ENV_NAME", value = var.site-env-name },

    # DB hostname
    { name = "WEBCMS_DB_HOST", value = aws_rds_cluster.db.endpoint },

    # Drupal 8 info
    { name = "WEBCMS_DB_USER", value = local.database-user },
    { name = "WEBCMS_DB_NAME", value = local.database-name },

    # Drupal 7 info - used for migration source
    { name = "WEBCMS_DB_USER_D7", value = local.database-user-d7 },
    { name = "WEBCMS_DB_NAME_D7", value = local.database-name-d7 },

    # Mail
    { name = "WEBCMS_MAIL_USER", value = var.email-auth-user },
    { name = "WEBCMS_MAIL_FROM", value = var.email-from },
    { name = "WEBCMS_MAIL_HOST", value = var.email-host },

    # Injected host names
    { name = "WEBCMS_SEARCH_HOST", value = "https://${aws_elasticsearch_domain.es.endpoint}:443/" },
    { name = "WEBCMS_CACHE_HOST", value = aws_elasticache_replication_group.cache.configuration_endpoint_address },
  ]

  # Secrets Manager bindings for Drupal containers
  drupal-secrets = [
    { name = "WEBCMS_DB_PASS", valueFrom = aws_secretsmanager_secret.db_app_password.arn },
    { name = "WEBCMS_DB_PASS_D7", valueFrom = aws_secretsmanager_secret.db_app_d7_password.arn },
    { name = "WEBCMS_HASH_SALT", valueFrom = aws_secretsmanager_secret.hash_salt.arn },
    { name = "WEBCMS_MAIL_PASS", valueFrom = aws_secretsmanager_secret.mail_pass.arn },
  ]

  # Security groups used by Drupal containers
  drupal-security-groups = [
    aws_security_group.drupal_task.id,
    aws_security_group.database_access.id,
    aws_security_group.cache_access.id,
    aws_security_group.search_access.id,
  ]

  # Database name for the WebCMS
  database-name = "webcms"

  # User for the WebCMS. This is *not* the master username that is specified
  # in Terraform! It's the app-level user that only needs permissions to modify
  # the WebCMS's database.
  database-user = "webcms"

  # Name of the Drupal 7 database for migration
  database-name-d7 = "webcms_d7"

  # User for the Drupal 7 database
  database-user-d7 = "webcms_d7"
}

# Local values related to environment-specific naming
locals {
  # Per-environment suffix (for creating "foo-dev" or "bar-prod")
  env-suffix = var.site-env-name

  # Title-cased name of the environment
  env-title = title(var.site-env-name)

  # Prefix of the name tag, to give human-friendly names to generated resources
  name-prefix = "WebCMS ${local.env-title}"

  # Save the role prefix in order to use it everywhere - we don't use a /foo/ prefix
  # because there are many places where role<->permission associations only use role
  # names, so we keep them unique instead of prefixing non-unique names.
  role-prefix = "WebCMS${local.env-title}"

  # Tags attached to every resource
  common-tags = {
    Group       = "webcms"
    Environment = var.site-env-name
  }
}

# Shared cluster naming values
locals {
  cluster-name = "webcms-cluster-${local.env-suffix}"
}
