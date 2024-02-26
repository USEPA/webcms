#region Database credentials

resource "aws_secretsmanager_secret" "db_root_credentials" {
  name        = "/webcms/${var.environment}/db-root-credentials_new"
  description = "Credentials for the WebCMS DB administrator"

  recovery_window_in_days = 0

  tags = var.tags
}

# Populate the root credentials with the randomly-generated password from rds.tf. This is
# needed for the database initialization task to create the application-specific
# credentials.

resource "aws_secretsmanager_secret_version" "db_root_credentials" {
  secret_id = aws_secretsmanager_secret.db_root_credentials.id

  secret_string = jsonencode({
    username = "root"
    password = random_password.rds_root_password.result
  })

  lifecycle {
    ignore_changes = [secret_string, version_stages]
  }
}

# The D8 and D7 credentials are populated by the database initialization task on its first
# run.

resource "aws_secretsmanager_secret" "db_d8_credentials" {
  for_each = local.sites

  name        = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/db-d8-credentials"
  description = "Credentials for the WebCMS DB user"

  recovery_window_in_days = 0

  tags = var.tags
}

resource "aws_secretsmanager_secret" "db_d7_credentials" {
  for_each = local.sites

  name        = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/db-d7-credentials"
  description = "Credentials for the WebCMS' Drupal 7 database user"

  recovery_window_in_days = 0

  tags = var.tags
}

#endregion

#region Application secrets

# Secrets below this line are not populated by Terraform; they will need to be set after
# Terraform has run but before the first deployment.

resource "aws_secretsmanager_secret" "hash_salt" {
  for_each = local.sites

  name        = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/drupal-hash-salt"
  description = "Drupal hash salt"

  recovery_window_in_days = 0

  tags = var.tags
}

resource "aws_secretsmanager_secret" "mail_pass" {
  for_each = local.sites

  name        = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/mail-password"
  description = "Password for SMTP auth"

  recovery_window_in_days = 0

  tags = var.tags
}

resource "aws_secretsmanager_secret" "saml_sp_key" {
  for_each = local.sites

  name        = "/webcms/${var.environment}/${each.value.site}/${each.value.lang}/saml-sp-key"
  description = "Private key for Drupal's SAML SP."

  recovery_window_in_days = 0

  tags = var.tags
}

#endregion

#region Basic Authentication

# Create an optional secret to store basic authentication credentials. This is
# used to, e.g., block access to preproduction sites by bots/crawlers.
#
# Note that this is a per-site secret, not a per-language secret.
resource "aws_secretsmanager_secret" "basic_auth" {
  for_each = toset(var.sites)

  name        = "/webcms/${var.environment}/${each.value}/basic-auth"
  description = "Basic authentication credentials"

  recovery_window_in_days = 0

  tags = var.tags
}

# Create an empty/default set of basic auth credentials. This exists solely to
# prepopulate the secret with a value to prevent ECS deployments from breaking.
resource "aws_secretsmanager_secret_version" "basic_auth" {
  for_each = toset(var.sites)

  secret_id = aws_secretsmanager_secret.basic_auth[each.value].id

  secret_string = "."

  lifecycle {
    ignore_changes = [secret_string, version_stages]
  }
}
#endregion
