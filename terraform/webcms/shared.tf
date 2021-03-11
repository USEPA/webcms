locals {
  env-suffix  = var.site-env-name
  env-title   = title(var.site-env-name)
  name-prefix = "WebCMS ${local.env-title}"
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
  drupal-environment = [
    { name = "WEBCMS_S3_BUCKET", value = aws_s3_bucket.uploads.bucket },
    { name = "WEBCMS_S3_REGION", value = var.aws-region },
    { name = "WEBCMS_SITE_URL", value = "https://${var.site-hostname}" },
    { name = "WEBCMS_SITE_HOSTNAME", value = var.site-hostname },
    { name = "WEBCMS_ENV_STATE", value = var.site-env-state },
    { name = "WEBCMS_ENV_NAME", value = var.site-env-name },
    { name = "WEBCMS_ENV_LANG", value = var.site-env-lang },
    { name = "WEBCMS_S3_USES_DOMAIN", value = var.site-s3-uses-domain ? "1" : "0" },

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
  common-tags = {
    Group       = "webcms"
    Environment = var.site-env-name
  }
}

data "aws_ssm_parameter" "drupal_iam_task" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/iam-task"
}

data "aws_ssm_parameter" "drupal_iam_exec" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/iam-execution"
}

data "aws_ssm_parameter" "drupal_ecs_service" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecs-service"
}

data "aws_ssm_parameter" "alb_frontend" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/alb-frontend"
}

data "aws_ssm_parameter" "drupal_https_target_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/https-target-group"
}

data "aws_ssm_parameter" "ecr_repository_drush_url" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecr-repo-drush-url"
}

data "aws_ssm_parameter" "ecr_repository_drupal_url" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecr-repo-drupal-url"
}

data "aws_ssm_parameter" "drupal_s3_bucket" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/s3-bucket"
}

data "aws_ssm_parameter" "drupal_log_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/log-groups/drupal"
}

data "aws_ssm_parameter" "ecr_repository_nginx_url" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecr-repo-nginx-url"
}

data "aws_ssm_parameter" "bucket_regional_domain_name" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/bucket-regional-domain-name"
}

data "aws_ssm_parameter" "nginx_log_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/log-groups/nginx"
}

data "aws_ssm_parameter" "agent_log_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/log-groups/cloudwatch-agent"
}

data "aws_ssm_parameter" "fpm_metrics_log_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/log-groups/fpm-metrics"
}

data "aws_ssm_parameter" "ecs_cluster_name" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecs_cluster_name"
}

data "aws_ssm_parameter" "drush_log_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/log-groups/drush"
}