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

# Log group for metrics-enabled CloudWatch logs
# We keep this separate in order to avoid polluting other logs with metric data
resource "aws_cloudwatch_log_group" "metrics" {
  name = "/webcms-${local.env-suffix}/cloudwatch-metrics"
}
