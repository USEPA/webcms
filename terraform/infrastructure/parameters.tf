#region Drupal-specific

resource "aws_ssm_parameter" "drupal_iam_task" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/iam-task"
  type  = "String"
  value = aws_iam_role.drupal_task[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_iam_exec" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/iam-execution"
  type  = "String"
  value = aws_iam_role.drupal_exec[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_ecs_service" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/ecs-service"
  type  = "String"
  value = aws_ecs_service.drupal[count.index].name

  tags = var.tags
}

resource "aws_ssm_parameter" "alb_frontend" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/alb-frontend"
  type  = "String"
  value = aws_lb.frontend.arn

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_https_target_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/https-target-group"
  type  = "String"
  value = aws_lb_target_group.drupal_https_target_group.arn

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_http_target_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/http-target-group"
  type  = "String"
  value = aws_lb_target_group.drupal_http_target_group.arn

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_repository_drush_url" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/ecr-repo-drush-url"
  type  = "String"
  value = aws_ecr_repository.drush.repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_repository_drupal_url" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/ecr-repo-drupal-url"
  type  = "String"
  value = aws_ecr_repository.drupal.repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_repository_nginx_url" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/ecr-repo-nginx-url"
  type  = "String"
  value = aws_ecr_repository.nginx.repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "bucket_regional_domain_name" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/bucket-regional-domain-name"
  type  = "String"
  value = aws_s3_bucket.uploads.bucket_regional_domain_name

  tags = var.tags
}

resource "aws_ssm_parameter" "ecs_cluster_name" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/ecs_cluster_name"
  type  = "String"
  value = aws_ecs_cluster.cluster.name

  tags = var.tags
}

resource "aws_ssm_parameter" "ecs_cluster_arn" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/ecs_cluster_arn"
  type  = "String"
  value = aws_ecs_cluster.cluster.arn

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_s3_bucket" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/s3-bucket"
  type  = "String"
  value = aws_s3_bucket.uploads[each.key].bucket

  tags = var.tags
}

resource "aws_ssm_parameter" "elastic_cache_endpoint" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/elastic-cache-endpoint"
  type  = "String"
  value = aws_elasticache_cluster.cache[each.key].configuration_endpoint

  tags = var.tags
}

resource "aws_ssm_parameter" "aws_db_proxy_endpoint" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/aws-db-proxy-endpoint"
  type  = "String"
  value = aws_db_proxy.proxy[each.key].endpoint

  tags = var.tags
}

resource "aws_ssm_parameter" "aws_elasticsearch_endpoint" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/aws-elasticsearch-endpoint"
  type  = "String"
  value = aws_elasticsearch_domain.es[each.key].endpoint

  tags = var.tags
}

resource "aws_ssm_parameter" "cloudwatch_event_rule_cron" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/cloudwatch-event-rule-cron"
  type  = "String"
  value = aws_cloudwatch_event_rule.cron[each.key].name

  tags = var.tags
}

resource "aws_ssm_parameter" "aws_iam_role_events" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/aws-iam-role-events"
  type  = "String"
  value = aws_iam_role.events[each.key].arn

  tags = var.tags
}

#endregion

#region Log groups

resource "aws_ssm_parameter" "php_fpm_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/php-fpm"
  type  = "String"
  value = aws_cloudwatch_log_group.php_fpm[each.key].name

  tags = var.tags
}

resource "aws_ssm_parameter" "nginx_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/nginx"
  type  = "String"
  value = aws_cloudwatch_log_group.nginx[each.key].name

  tags = var.tags
}

resource "aws_ssm_parameter" "drush_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/drush"
  type  = "String"
  value = aws_cloudwatch_log_group.drush[each.key].name

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/drupal"
  type  = "String"
  value = aws_cloudwatch_log_group.drupal[each.key].name

  tags = var.tags
}

resource "aws_ssm_parameter" "agent_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/cloudwatch-agent"
  type  = "String"
  value = aws_cloudwatch_log_group.agent[each.key].name

  tags = var.tags
}

resource "aws_ssm_parameter" "fpm_metrics_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/fpm-metrics"
  type  = "String"
  value = aws_cloudwatch_log_group.fpm_metrics[each.key].name

  tags = var.tags
}

#endregion

#region Secrets Manager ARNs

resource "aws_ssm_parameter" "db_d8_credentials" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/db-d8-credentials"
  type  = "String"
  value = aws_secretsmanager_secret.db_d8_credentials[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "db_d7_credentials" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/db-d7-credentials"
  type  = "String"
  value = aws_secretsmanager_secret.db_d7_credentials[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "hash_salt" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/drupal-hash-salt"
  type  = "String"
  value = aws_secretsmanager_secret.hash_salt[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "mail_pass" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/mail-password"
  type  = "String"
  value = aws_secretsmanager_secret.mail_pass[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "saml_sp_key" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/saml-sp-key"
  type  = "String"
  value = aws_secretsmanager_secret.saml_sp_key[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "akamai_access_token" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/akamai-access-token"
  type  = "String"
  value = aws_secretsmanager_secret.akamai_access_token[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "akamai_client_token" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/akamai-client-token"
  type  = "String"
  value = aws_secretsmanager_secret.akamai_client_token[each.key].arn

  tags = var.tags
}

resource "aws_ssm_parameter" "akamai_client_secret" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/akamai-client-secret"
  type  = "String"
  value = aws_secretsmanager_secret.akamai_client_secret[each.key].arn

  tags = var.tags
}

#endregion

#region Terraform configuration

resource "aws_ssm_parameter" "terraform_state" {
  name  = "/webcms/${var.environment}/terraform/state"
  type  = "String"
  value = aws_s3_bucket.tfstate.bucket

  tags = var.tags
}

resource "aws_ssm_parameter" "terraform_locks" {
  name  = "/webcms/${var.environment}/terraform/locks"
  type  = "String"
  value = aws_dynamodb_table.terraform_locks.arn

  tags = var.tags
}

#endregion
