# This file is for values shared across multiple other *.tf files.

data "aws_caller_identity" "current" {}
data "aws_region" "current" {}

data "aws_kms_alias" "secretsmanager" {
  name = "alias/aws/secretsmanager"
}

# We use separate definitions for the web-facing Drupal tasks and scheduled Drush cron
# scripts for a few reasons (such as avoiding spawning nginx), so we share the values
# here.
locals {
  # Plaintext environment variables for Drupal containers
  drupal-environment = [
    { name = "WEBCMS_S3_BUCKET", value = aws_s3_bucket.uploads.bucket },
    { name = "WEBCMS_S3_REGION", value = var.aws-region },
    { name = "WEBCMS_SITE_URL", value = "https://${var.site-hostname}" },
    { name = "WEBCMS_SITE_HOSTNAME", value = var.site-hostname },
    { name = "WEBCMS_ENV_STATE", value = var.site-env-state },
    { name = "WEBCMS_SITE", value = var.site-env-name },
    { name = "WEBCMS_LANG", value = var.site-env-lang },
    { name = "WEBCMS_S3_USES_DOMAIN", value = var.site-s3-uses-domain ? "1" : "0" },
    { name = "WEBCMS_CSRF_ORIGIN_WHITELIST", value = var.site-csrf-origin-whitelist },

    # Akamai
    { name = "WEBCMS_AKAMAI_ENABLED", value = var.akamai-enabled ? "1" : "0" },
    { name = "WEBCMS_AKAMAI_API_HOST", value = var.akamai-api-host },

    # DB info
    { name = "WEBCMS_DB_HOST", value = aws_db_proxy.proxy.endpoint },
    { name = "WEBCMS_DB_NAME", value = local.database-name },
    { name = "WEBCMS_DB_NAME_D7", value = local.database-name-d7 },

    # Mail
    { name = "WEBCMS_MAIL_USER", value = var.email-auth-user },
    { name = "WEBCMS_MAIL_FROM", value = var.email-from },
    { name = "WEBCMS_MAIL_HOST", value = var.email-host },
    { name = "WEBCMS_MAIL_PORT", value = tostring(var.email-port) },
    { name = "WEBCMS_MAIL_PROTOCOL", value = var.email-protocol },
    { name = "WEBCMS_MAIL_ENABLE_WORKFLOW_NOTIFICATIONS", value = var.email-enable-workflow-notifications ? "1" : "0" },

    # Injected host names
    { name = "WEBCMS_SEARCH_HOST", value = "https://${aws_elasticsearch_domain.es.endpoint}:443" },
    { name = "WEBCMS_CACHE_HOST", value = aws_elasticache_cluster.cache.configuration_endpoint },

    # SAML
    { name = "WEBCMS_SAML_SP_ENTITY_ID", value = var.saml-sp-entity-id },
    { name = "WEBCMS_SAML_SP_CERT", value = var.saml-sp-cert },
    { name = "WEBCMS_SAML_IDP_ID", value = var.saml-idp-id },
    { name = "WEBCMS_SAML_IDP_SSO_URL", value = var.saml-idp-sso-url },
    { name = "WEBCMS_SAML_IDP_SLO_URL", value = var.saml-idp-slo-url },
    { name = "WEBCMS_SAML_IDP_CERT", value = var.saml-idp-cert },
    { name = "WEBCMS_SAML_FORCE_SAML_LOGIN", value = var.saml-force-saml-login ? "1" : "0" },
  ]

  # Secrets Manager bindings for Drupal containers
  drupal-secrets = [
    { name = "WEBCMS_DB_CREDS", valueFrom = aws_secretsmanager_secret.db_app_credentials.arn },
    { name = "WEBCMS_DB_CREDS_D7", valueFrom = aws_secretsmanager_secret.db_app_d7_credentials.arn },
    { name = "WEBCMS_HASH_SALT", valueFrom = aws_secretsmanager_secret.hash_salt.arn },
    { name = "WEBCMS_MAIL_PASS", valueFrom = aws_secretsmanager_secret.mail_pass.arn },
    { name = "WEBCMS_SAML_SP_KEY", valueFrom = aws_secretsmanager_secret.saml_sp_key.arn },
    { name = "WEBCMS_AKAMAI_ACCESS_TOKEN", valueFrom = aws_secretsmanager_secret.akamai_access_token.arn },
    { name = "WEBCMS_AKAMAI_CLIENT_TOKEN", valueFrom = aws_secretsmanager_secret.akamai_client_token.arn },
    { name = "WEBCMS_AKAMAI_CLIENT_SECRET", valueFrom = aws_secretsmanager_secret.akamai_client_secret.arn },
  ]

  # Security groups used by Drupal containers
  drupal-security-groups = [
    aws_security_group.drupal_task.id,
    aws_security_group.proxy_access.id,
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
