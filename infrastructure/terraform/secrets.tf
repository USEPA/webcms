# Secrets in this file are not initialized; remember to populate them on the first run of
# the Terraform template.

# When we use fixed names in Secrets Manager, we can run into an issue where Terraform can't
# destroy the secret until the default time window has passed. This means that we will need
# to generate a unique suffix ourselves. Whenever the Terraform plan indicates that a secret
# will need to be replaced, simply taint this secret_suffix resource and Terraform will
# generate a new name for us.
#
# NB. When this happens, an admin will need to copy the secrets values over from the old
# secrets to the new secrets. Be sure to take a backup of the values BEFORE applying with
# Terraform, or else the secret values will be lost.
resource "random_pet" "secret_suffix" {
  # We use length=2 to follow the Docker convention
  length = 2
}

resource "aws_secretsmanager_secret" "db_root_password" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/db_root/password"
  description = "Password for the WebCMS DB administrator"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: DB Root Password"
  })
}

resource "aws_secretsmanager_secret" "db_app_password" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/db_app/password"
  description = "Password for the WebCMS DB user"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: DB App Password"
  })
}

resource "aws_secretsmanager_secret" "hash_salt" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/drupal/hash_salt"
  description = "Drupal hash salt"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: Hash Salt"
  })
}

resource "aws_secretsmanager_secret" "mail_pass" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/mail/password"
  description = "Password for SMTP auth"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: Email Password"
  })
}
