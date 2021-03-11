data "aws_secretsmanager_secret" "db_root_credentials" {
  name = "/webcms/${var.environment}/db-root-credentials"
}

data "aws_secretsmanager_secret" "db_d8_credentials" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/db-d8-credentials"
}

data "aws_secretsmanager_secret" "db_d7_credentials" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/db-d7-credentials"
}

data "aws_secretsmanager_secret" "hash_salt" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal-hash-salt"
}

data "aws_secretsmanager_secret" "mail_pass" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/mail-password"
}

data "aws_secretsmanager_secret" "saml_sp_key" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/saml-sp-key"
}

data "aws_secretsmanager_secret" "akamai_access_token" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/akamai-access-token"
}

data "aws_secretsmanager_secret" "akamai_client_token" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/akamai-client-token"
}

data "aws_secretsmanager_secret" "akamai_client_secret" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/akamai-client-secret"
}

data "aws_ssm_parameter" "elastic_cache_endpoint" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/elastic-cache-endpoint"
}

data "aws_ssm_parameter" "drupal_s3_bucket" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/s3-bucket"
}

data "aws_ssm_parameter" "aws_db_proxy_endpoint" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/aws_db_proxy_endpoint"
}

data "aws_ssm_parameter" "aws_elasticsearch_endpoint" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/aws-elasticsearch-endpoint"
}
#
data "aws_ssm_parameter" "database_security_group" {
  name = "/webcms/${var.environment}/security-groups/database"
}

data "aws_ssm_parameter" "proxy_security_group" {
  name = "/webcms/${var.environment}/security-groups/proxy"
}

data "aws_ssm_parameter" "elasticsearch_security_group" {
  name = "/webcms/${var.environment}/security-groups/elasticsearch"
}

data "aws_ssm_parameter" "memcached_security_group" {
  name = "/webcms/${var.environment}/security-groups/memcached"
}

data "aws_ssm_parameter" "drupal_security_group" {
  name = "/webcms/${var.environment}/security-groups/drupal"
}

data "aws_ssm_parameter" "traefik_security_group" {
  name = "/webcms/${var.environment}/security-groups/traefik"
}

data "aws_ssm_parameter" "terraform_database_security_group" {
  name = "/webcms/${var.environment}/security-groups/terraform-database"
}

locals {
  env_suffix       = var.site_env_name
  env_title        = title(var.site_env_name)
  name_prefix      = "WebCMS ${local.env_title}"
  database_name    = "webcms"
  database_user    = "webcms"
  database_name_d7 = "webcms_d7"
  database_user_d7 = "webcms_d7"
  drupal_secrets = [
    { name = "WEBCMS_DB_CREDS", valueFrom = data.aws_secretsmanager_secret.db_d8_credentials.name },
    { name = "WEBCMS_DB_CREDS_D7", valueFrom = data.aws_secretsmanager_secret.db_d7_credentials.name },
    { name = "WEBCMS_HASH_SALT", valueFrom = data.aws_secretsmanager_secret.hash_salt.name },
    { name = "WEBCMS_MAIL_PASS", valueFrom = data.aws_secretsmanager_secret.mail_pass.name },
    { name = "WEBCMS_SAML_SP_KEY", valueFrom = data.aws_secretsmanager_secret.saml_sp_key.name },
    { name = "WEBCMS_AKAMAI_ACCESS_TOKEN", valueFrom = data.aws_secretsmanager_secret.akamai_access_token.name },
    { name = "WEBCMS_AKAMAI_CLIENT_TOKEN", valueFrom = data.aws_secretsmanager_secret.akamai_client_token.name },
    { name = "WEBCMS_AKAMAI_CLIENT_SECRET", valueFrom = data.aws_secretsmanager_secret.akamai_client_secret.name },
  ]
  drupal_environment = [
    { name = "WEBCMS_S3_BUCKET", value = data.aws_ssm_parameter.drupal_s3_bucket.value },
    { name = "WEBCMS_S3_REGION", value = var.aws_region },
    { name = "WEBCMS_SITE_URL", value = "https://${var.site_hostname}" },
    { name = "WEBCMS_SITE_HOSTNAME", value = var.site_hostname },
    { name = "WEBCMS_ENV_STATE", value = var.site_env_state },
    { name = "WEBCMS_ENV_NAME", value = var.site_env_name },
    { name = "WEBCMS_ENV_LANG", value = var.sites.lang },
    { name = "WEBCMS_S3_USES_DOMAIN", value = var.site_s3_uses_domain ? "1" : "0" },

    # Akamai
    { name = "WEBCMS_AKAMAI_ENABLED", value = var.akamai_enabled ? "1" : "0" },
    { name = "WEBCMS_AKAMAI_API_HOST", value = var.akamai_api_host },

    # DB info
    { name = "WEBCMS_DB_HOST", value = data.aws_ssm_parameter.aws_db_proxy_endpoint.value },
    { name = "WEBCMS_DB_NAME", value = local.database_name },
    { name = "WEBCMS_DB_NAME_D7", value = local.database_name_d7 },

    # Mail
    { name = "WEBCMS_MAIL_USER", value = var.email_auth_user },
    { name = "WEBCMS_MAIL_FROM", value = var.email_from },
    { name = "WEBCMS_MAIL_HOST", value = var.email_host },
    { name = "WEBCMS_MAIL_PORT", value = tostring(var.email_port) },
    { name = "WEBCMS_MAIL_PROTOCOL", value = var.email_protocol },
    { name = "WEBCMS_MAIL_ENABLE_WORKFLOW_NOTIFICATIONS", value = var.email_enable_workflow_notifications ? "1" : "0" },

    # Injected host names
    { name = "WEBCMS_SEARCH_HOST", value = "https://${data.aws_ssm_parameter.aws_elasticsearch_endpoint.value}:443" },
    { name = "WEBCMS_CACHE_HOST", value = data.aws_ssm_parameter.elastic_cache_endpoint.value },

    # SAML
    { name = "WEBCMS_SAML_SP_ENTITY_ID", value = var.saml_sp_entity_id },
    { name = "WEBCMS_SAML_SP_CERT", value = var.saml_sp_cert },
    { name = "WEBCMS_SAML_IDP_ID", value = var.saml_idp_id },
    { name = "WEBCMS_SAML_IDP_SSO_URL", value = var.saml_idp_sso_url },
    { name = "WEBCMS_SAML_IDP_SLO_URL", value = var.saml_idp_slo_url },
    { name = "WEBCMS_SAML_IDP_CERT", value = var.saml_idp_cert },
    { name = "WEBCMS_SAML_FORCE_SAML_LOGIN", value = var.saml_force_saml_login ? "1" : "0" },
  ]
  drupal_security_groups = [
    data.aws_ssm_parameter.database_security_group.value,
    data.aws_ssm_parameter.proxy_security_group.value,
    data.aws_ssm_parameter.elasticsearch_security_group.value,
    data.aws_ssm_parameter.memcached_security_group.value,
    data.aws_ssm_parameter.drupal_security_group,
    data.aws_ssm_parameter.traefik_security_group.value,
    data.aws_ssm_parameter.terraform_database_security_group,
  ]
  common_tags = {
    Group       = "webcms"
    Environment = var.site_env_name
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

data "aws_ssm_parameter" "ecr_repository_drush_url" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecr-repo-drush-url"
}

data "aws_ssm_parameter" "ecr_repository_drupal_url" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecr-repo-drupal-url"
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

data "aws_ssm_parameter" "ecs_cluster_arn" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/ecs_cluster_arn"
}

data "aws_ssm_parameter" "drush_log_group" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/log-groups/drush"
}

data "aws_ssm_parameter" "private_subnets" {
  name = "/webcms/${var.environment}/vpc/private-subnets"
}

data "aws_ssm_parameter" "cloudwatch_event_rule_cron" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/cloudwatch-event-rule-cron"
}

data "aws_ssm_parameter" "aws_iam_role_events" {
  name = "/webcms/${var.environment}/${var.sites.site}/${var.sites.lang}/drupal/aws-iam-role-events"
}