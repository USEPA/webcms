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

resource "aws_secretsmanager_secret" "db_root_credentials" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/db_root/credentials"
  description = "Password for the WebCMS DB administrator"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: DB Root Credentials"
  })
}

resource "aws_secretsmanager_secret" "db_app_credentials" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/db_app/credentials"
  description = "Password for the WebCMS DB user"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: DB App Credentials"
  })
}

resource "aws_secretsmanager_secret" "db_app_d7_credentials" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/db_app_d7/credentials"
  description = "Password for the WebCMS' Drupal 7 database user"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: D7 DB Credentials"
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

resource "aws_secretsmanager_secret" "saml_sp_key" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/drupal/saml_sp_key"
  description = "Private key for Drupal's SAML SP."

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: SAML SP private key"
  })
}

resource "aws_secretsmanager_secret" "akamai_access_token" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/drupal/akamai_access_token"
  description = "Akamai access token."

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: Akamai access token"
  })
}

resource "aws_secretsmanager_secret" "akamai_client_token" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/drupal/akamai_client_token"
  description = "Akamai client token."

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: Akamai client token"
  })
}

resource "aws_secretsmanager_secret" "akamai_client_secret" {
  name        = "/webcms-${local.env-suffix}-${random_pet.secret_suffix.id}/drupal/akamai_client_secret"
  description = "Akamai client secret."

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Secret: Akamai client secret"
  })
}