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

#region ALB

resource "aws_ssm_parameter" "alb_arn" {
  name  = "/webcms/${var.environment}/alb/arn"
  type  = "String"
  value = aws_lb.app_load_balancer.arn

  tags = var.tags
}

resource "aws_ssm_parameter" "alb_listener" {
  name  = "/webcms/${var.environment}/alb/listener"
  type  = "String"
  value = aws_lb_listener.alb_https.arn

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

# Provide the ElastiCache nodes as a comma-separated list of host:port addresses for loading in settings.php.
resource "aws_ssm_parameter" "elasticache_node_endpoints" {
  name  = "/webcms/${var.environment}/endpoints/elasticache-nodes"
  type  = "StringList"
  value = join(",", [for node in aws_elasticache_cluster.cache.cache_nodes : "${node.address}:${node.port}"])

  tags = var.tags
}

resource "aws_ssm_parameter" "rds_proxy_endpoint" {
  name  = "/webcms/${var.environment}/endpoints/rds-proxy"
  type  = "String"
  value = var.regional_cluster_endpoint

  tags = var.tags
}

resource "aws_ssm_parameter" "elasticsearch_endpoint" {
  name  = "/webcms/${var.environment}/endpoints/elasticsearch"
  type  = "String"
  value = aws_elasticsearch_domain.es.endpoint

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

resource "aws_ssm_parameter" "basic_auth" {
  for_each = local.sites

  name  = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/secrets/basic-auth"
  type  = "String"
  value = aws_secretsmanager_secret.basic_auth[each.value.site].arn

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

#endregion
