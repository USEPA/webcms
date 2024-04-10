# Log group for all nginx container logs
resource "aws_cloudwatch_log_group" "nginx" {
  for_each = local.sites

  name = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/nginx"

  tags = var.tags
}

# Log group for all Drupal container logs
resource "aws_cloudwatch_log_group" "php_fpm" {
  for_each = local.sites

  name = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/php-fpm"

  tags = var.tags
}

# Log group for all Drush tasks, which we keep separate from the Drupal site logs
resource "aws_cloudwatch_log_group" "drush" {
  for_each = local.sites

  name = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/app-drush"

  tags = var.tags
}

# Log group for Drupal logs, emitted by the epa_cloudwatch module
resource "aws_cloudwatch_log_group" "drupal" {
  for_each = local.sites

  name = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/app-drupal"

  tags = var.tags
}

# Log group for any Terraform runs performed inside the ECS cluster
resource "aws_cloudwatch_log_group" "terraform" {
  name = "/webcms/${var.environment}/terraform"

  tags = var.tags
}
