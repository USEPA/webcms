# Secrets in this file are not initialized; remember to populate them on the first run of
# the Terraform template.

resource "aws_secretsmanager_secret" "db_root_password" {
  name        = "/webcms/db_root/password"
  description = "Password for the WebCMS DB administrator"

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_secretsmanager_secret" "db_app_password" {
  name        = "/webcms/db_app/password"
  description = "Password for the WebCMS DB user"

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_secretsmanager_secret" "hash_salt" {
  name        = "/webcms/drupal/hash_salt"
  description = "Drupal hash salt"

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_secretsmanager_secret" "mail_pass" {
  name        = "/webcms/mail/password"
  description = "Password for SMTP auth"

  tags = {
    Application = "WebCMS"
  }
}
