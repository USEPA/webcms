#region Cluster information

resource "aws_ssm_parameter" "ecs_cluster_name" {
  name  = "/webcms/${var.environment}/ecs/cluster-name"
  type  = "String"
  value = aws_ecs_cluster.cluster.name

  tags = var.tags
}

resource "aws_ssm_parameter" "ecs_cluster_arn" {
  name  = "/webcms/${var.environment}/ecs/cluster-arn"
  type  = "String"
  value = aws_ecs_cluster.cluster.arn

  tags = var.tags
}

#endregion

#region Service endpoints

resource "aws_ssm_parameter" "elasticache_endpoint" {
  name  = "/webcms/${var.environment}/endpoints/elasticache"
  type  = "String"
  value = aws_elasticache_cluster.cache.configuration_endpoint

  tags = var.tags
}

resource "aws_ssm_parameter" "rds_proxy_endpoint" {
  name  = "/webcms/${var.environment}/endpoints/rds-proxy"
  type  = "String"
  value = aws_db_proxy.proxy.endpoint

  tags = var.tags
}

resource "aws_ssm_parameter" "elasticsearch_endpoint" {
  name  = "/webcms/${var.environment}/endpoints/elasticsearch"
  type  = "String"
  value = aws_elasticsearch_domain.es.endpoint

  tags = var.tags
}

#endregion

#region Cron

resource "aws_ssm_parameter" "cron_event_rule" {
  name  = "/webcms/${var.environment}/cron/event-rule"
  type  = "String"
  value = aws_cloudwatch_event_rule.cron.name

  tags = var.tags
}

resource "aws_ssm_parameter" "cron_event_role" {
  name  = "/webcms/${var.environment}/cron/event-role"
  type  = "String"
  value = aws_iam_role.events.arn

  tags = var.tags
}

#endregion

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

resource "aws_ssm_parameter" "drupal_s3_bucket" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/s3-bucket"
  type  = "String"
  value = aws_s3_bucket.uploads[each.key].bucket

  tags = var.tags
}

resource "aws_ssm_parameter" "drupal_s3_domain" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal/s3-domain"
  type  = "String"
  value = aws_s3_bucket.uploads[each.key].bucket_regional_domain_name

  tags = var.tags
}

#endregion

#region ECR

resource "aws_ssm_parameter" "ecr_drupal" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/ecr/drupal"
  type  = "String"
  value = aws_ecr_repository.drupal[each.value.site].repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_nginx" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/ecr/nginx"
  type  = "String"
  value = aws_ecr_repository.nginx[each.value.site].repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_drush" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/ecr/drush"
  type  = "String"
  value = aws_ecr_repository.drush[each.value.site].repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_metrics" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/ecr/metrics"
  type  = "String"
  value = aws_ecr_repository.metrics[each.value.site].repository_url

  tags = var.tags
}

resource "aws_ssm_parameter" "ecr_cloudwatch" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/ecr/cloudwatch"
  type  = "String"
  value = aws_ecr_repository.cloudwatch_agent_mirror.repository_url

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

resource "aws_ssm_parameter" "drupal_log_group" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/log-groups/drupal"
  type  = "String"
  value = aws_cloudwatch_log_group.drupal[each.key].name

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
