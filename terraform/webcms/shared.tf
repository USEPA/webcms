#region VPC

data "aws_ssm_parameter" "private_subnets" {
  name = "/webcms/${var.environment}/vpc/private-subnets"
}

locals {
  private_subnets = split(",", data.aws_ssm_parameter.private_subnets.value)
}

data "aws_ssm_parameter" "drupal_security_group" {
  name = "/webcms/${var.environment}/security-groups/drupal"
}

#endregion

#region Cluster information

data "aws_ssm_parameter" "ecs_cluster_name" {
  name = "/webcms/${var.environment}/ecs/cluster-name"
}

data "aws_ssm_parameter" "ecs_cluster_arn" {
  name = "/webcms/${var.environment}/ecs/cluster-arn"
}

#endregion

#region Service endpoints

data "aws_ssm_parameter" "elasticache_endpoint" {
  name = "/webcms/${var.environment}/endpoints/elasticache"
}

data "aws_ssm_parameter" "rds_proxy_endpoint" {
  name = "/webcms/${var.environment}/endpoints/rds-proxy"
}

data "aws_ssm_parameter" "elasticsearch_endpoint" {
  name = "/webcms/${var.environment}/endpoints/elasticsearch"
}

#region Cron

data "aws_ssm_parameter" "cron_event_rule" {
  name = "/webcms/${var.environment}/cron/event-rule"
}

data "aws_ssm_parameter" "cron_event_role" {
  name = "/webcms/${var.environment}/cron/event-role"
}

#endregion

#region Drupal-specific

data "aws_ssm_parameter" "drupal_iam_task" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/drupal/iam-task"
}

data "aws_ssm_parameter" "drupal_iam_exec" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/drupal/iam-execution"
}

data "aws_ssm_parameter" "drupal_s3_bucket" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/drupal/s3-bucket"
}

data "aws_ssm_parameter" "drupal_s3_domain" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/drupal/s3-domain"
}

#endregion

#region ECR

data "aws_ssm_parameter" "ecr_drupal" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/ecr/drupal"
}

data "aws_ssm_parameter" "ecr_nginx" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/ecr/nginx"
}

data "aws_ssm_parameter" "ecr_drush" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/ecr/drush"
}

data "aws_ssm_parameter" "ecr_metrics" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/ecr/metrics"
}

data "aws_ssm_parameter" "ecr_cloudwatch" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/ecr/cloudwatch"
}

#endregion

#region Log groups

data "aws_ssm_parameter" "php_fpm_log_group" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/log-groups/php-fpm"
}

data "aws_ssm_parameter" "nginx_log_group" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/log-groups/nginx"
}

data "aws_ssm_parameter" "drush_log_group" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/log-groups/drush"
}

data "aws_ssm_parameter" "agent_log_group" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/log-groups/cloudwatch-agent"
}

data "aws_ssm_parameter" "fpm_metrics_log_group" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/log-groups/fpm-metrics"
}

data "aws_ssm_parameter" "drupal_log_group" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/log-groups/drupal"
}

#endregion

#region Secrets Manager ARNs

data "aws_ssm_parameter" "db_d8_credentials" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/db-d8-credentials"
}

data "aws_ssm_parameter" "db_d7_credentials" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/db-d7-credentials"
}

data "aws_ssm_parameter" "hash_salt" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/drupal-hash-salt"
}

data "aws_ssm_parameter" "mail_pass" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/mail-password"
}

data "aws_ssm_parameter" "saml_sp_key" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/saml-sp-key"
}

data "aws_ssm_parameter" "akamai_access_token" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/akamai-access-token"
}

data "aws_ssm_parameter" "akamai_client_token" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/akamai-client-token"
}

data "aws_ssm_parameter" "akamai_client_secret" {
  name = "/webcms/${var.environment}/${var.site}/${var.lang}/secrets/akamai-client-secret"
}

#endregion

#region Locals

locals {
  #region Secret bindings

  drupal_secrets = [
    { name = "WEBCMS_DB_CREDS", valueFrom = data.aws_ssm_parameter.db_d8_credentials.value },

    { name = "WEBCMS_DB_CREDS_D7", valueFrom = data.aws_ssm_parameter.db_d7_credentials.value },

    { name = "WEBCMS_HASH_SALT", valueFrom = data.aws_ssm_parameter.hash_salt.value },
    { name = "WEBCMS_MAIL_PASS", valueFrom = data.aws_ssm_parameter.mail_pass.value },
    { name = "WEBCMS_SAML_SP_KEY", valueFrom = data.aws_ssm_parameter.saml_sp_key.value },
    { name = "WEBCMS_AKAMAI_ACCESS_TOKEN", valueFrom = data.aws_ssm_parameter.akamai_access_token.value },
    { name = "WEBCMS_AKAMAI_CLIENT_TOKEN", valueFrom = data.aws_ssm_parameter.akamai_client_token.value },
    { name = "WEBCMS_AKAMAI_CLIENT_SECRET", valueFrom = data.aws_ssm_parameter.akamai_client_secret.value },
  ]

  #endregion

  #region Environment variables

  drupal_environment = [
    { name = "WEBCMS_S3_BUCKET", value = data.aws_ssm_parameter.drupal_s3_bucket.value },
    { name = "WEBCMS_S3_REGION", value = var.aws_region },
    { name = "WEBCMS_SITE_URL", value = "https://${var.drupal_hostname}" },
    { name = "WEBCMS_SITE_HOSTNAME", value = var.drupal_hostname },
    { name = "WEBCMS_ENV_STATE", value = var.drupal_state },
    { name = "WEBCMS_SITE", value = var.site },
    { name = "WEBCMS_LANG", value = var.lang },
    { name = "WEBCMS_S3_USES_DOMAIN", value = var.drupal_use_s3_domain ? "1" : "0" },
    { name = "WEBCMS_CSRF_ORIGIN_WHITELIST", value = join(",", var.drupal_csrf_origin_whitelist) },
    { name = "WEBCMS_LOG_GROUP", value = data.aws_ssm_parameter.drupal_log_group.value },

    # Akamai
    { name = "WEBCMS_AKAMAI_ENABLED", value = var.akamai_enabled ? "1" : "0" },
    { name = "WEBCMS_AKAMAI_API_HOST", value = var.akamai_api_host },

    # DB info
    { name = "WEBCMS_DB_HOST", value = data.aws_ssm_parameter.rds_proxy_endpoint.value },
    { name = "WEBCMS_DB_NAME", value = "webcms_${var.site}_${var.lang}_d8" },
    { name = "WEBCMS_DB_NAME_D7", valueFrom = "webcms_${var.site}_${var.lang}_d7" },

    # Mail
    { name = "WEBCMS_MAIL_USER", value = var.email_auth_user },
    { name = "WEBCMS_MAIL_FROM", value = var.email_from },
    { name = "WEBCMS_MAIL_HOST", value = var.email_host },
    { name = "WEBCMS_MAIL_PORT", value = tostring(var.email_port) },
    { name = "WEBCMS_MAIL_PROTOCOL", value = var.email_protocol },
    { name = "WEBCMS_MAIL_ENABLE_WORKFLOW_NOTIFICATIONS", value = var.email_enable_workflow_notifications ? "1" : "0" },

    # Injected host names
    { name = "WEBCMS_SEARCH_HOST", value = "https://${data.aws_ssm_parameter.elasticsearch_endpoint.value}:443" },
    { name = "WEBCMS_CACHE_HOST", value = data.aws_ssm_parameter.elasticache_endpoint.value },

    # SAML
    { name = "WEBCMS_SAML_SP_ENTITY_ID", value = var.saml_sp_entity_id },
    { name = "WEBCMS_SAML_SP_CERT", value = var.saml_sp_cert },
    { name = "WEBCMS_SAML_IDP_ID", value = var.saml_idp_id },
    { name = "WEBCMS_SAML_IDP_SSO_URL", value = var.saml_idp_sso_url },
    { name = "WEBCMS_SAML_IDP_SLO_URL", value = var.saml_idp_slo_url },
    { name = "WEBCMS_SAML_IDP_CERT", value = var.saml_idp_cert },
    { name = "WEBCMS_SAML_FORCE_SAML_LOGIN", value = var.saml_force_saml_login ? "1" : "0" },
  ]

  #endregion
}

#endregion
