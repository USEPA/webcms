# Log group for all nginx container logs
resource "aws_cloudwatch_log_group" "nginx" {
  name = "/webcms-${local.env-suffix}/app-nginx"
}

# Log group for all Drupal container logs
resource "aws_cloudwatch_log_group" "drupal" {
  name = "/webcms-${local.env-suffix}/app-drupal"
}

# Log group for all Drush tasks, which we keep separate from the Drupal site logs
resource "aws_cloudwatch_log_group" "drush" {
  name = "/webcms-${local.env-suffix}/app-drush"
}

# Log group for the CloudWatch agent
resource "aws_cloudwatch_log_group" "agent" {
  name = "/webcms-${local.env-suffix}/cloudwatch-agent"
}

# Log group for the FPM metrics helper
resource "aws_cloudwatch_log_group" "fpm_metrics" {
  name = "/webcms-${local.env-suffix}/fpm-metrics"
}

# Log group for SSM automation for the D7 DB
resource "aws_cloudwatch_log_group" "ssm_d7_automation" {
  name = "/webcms-${local.env-suffix}/d7-automation"
}

# Log group for SSM automation for the D8 DB
resource "aws_cloudwatch_log_group" "ssm_d8_automation" {
  name = "/webcms-${local.env-suffix}/d8-automation"
}
