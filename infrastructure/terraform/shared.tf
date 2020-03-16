# Shared variables here. We use separate definitions for the web-facing Drupal tasks
# and scheduled Drush cron scripts for a few reasons (such as avoiding spawning nginx),
# so we share the values here.

locals {
  # Plaintext environment variables for Drupal containers
  drupal-environment = [
    { name = "WEBCMS_S3_BUCKET", value = aws_s3_bucket.uploads.bucket },
    { name = "WEBCMS_S3_REGION", value = var.aws-region },
  ]

  # Parameter store bindings for Drupal containers
  drupal-secrets = [
    { name = "WEBCMS_DB_USER", valueFrom = aws_ssm_parameter.db_app_username.arn },
    { name = "WEBCMS_DB_PASS", valueFrom = aws_ssm_parameter.db_app_password.arn },
    { name = "WEBCMS_DB_NAME", valueFrom = aws_ssm_parameter.db_app_database.arn },
  ]

  # Security groups used by Drupal containers
  drupal-security-groups = [
    aws_security_group.drupal_task.id,
    aws_security_group.database_access.id,
    aws_security_group.cache_access.id,
  ]
}
