# NB. Do NOT change the passwords here in this file. Terraform stores all of its state
# values - even sensitive ones - in cleartext. Instead, use the Parameter Store dashboard
# to change them. Remember to force a restart of the ECS tasks once you've done so.

# Parameters for the MySQL administrator user, prefixed at /webcms/db_root

resource "aws_ssm_parameter" "db_root_username" {
  name        = "/webcms/db_root/username"
  description = "Username for the WebCMS DB administrator"
  value       = var.db-username
  type        = "String"

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_ssm_parameter" "db_root_password" {
  name        = "/webcms/db_root/password"
  description = "Password for the WebCMS DB administrato"
  value       = var.db-password
  type        = "SecureString"

  tags = {
    Application = "WebCMS"
  }

  # Ignore changes to this password parameter: we only create this here because we need
  # its ARN in other resources.
  lifecycle {
    ignore_changes = [value]
  }
}

# Parameters for the WebCMS's database, including the database name and user credentials.
# Note that these are NOT used to automatically create the user in MySQL. Someone will
# need to perform the initial database and user creation for Drupal after this cluster
# has been created for the first time.

resource "aws_ssm_parameter" "db_app_username" {
  name        = "/webcms/db_app/username"
  description = "Username for the WebCMS DB user"
  value       = local.database-name
  type        = "String"

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_ssm_parameter" "db_app_password" {
  name        = "/webcms/db_app/password"
  description = "Password for the WebCMS DB user"
  value       = "changeme"
  type        = "SecureString"

  tags = {
    Application = "WebCMS"
  }

  # Ignore changes to this password parameter: we only create this here because we need
  # its ARN in other resources.
  lifecycle {
    ignore_changes = [value]
  }
}

resource "aws_ssm_parameter" "db_app_database" {
  name        = "/webcms/db_app/database"
  description = "Name of the WebCMS DB schema"
  value       = local.database-user
  type        = "String"

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_ssm_parameter" "hash_salt" {
  name = "/webcms/drupal/hash_salt"
  description = "Drupal hash salt"
  value = "changeme"
  type = "SecureString"

  tags = {
    Application = "WebCMS"
  }

  # Ignore changes to this sensitive parameter: as with other sensitive values, this is
  # only here because we need the ARN.
  lifecycle {
    ignore_changes = [value]
  }
}
