#region Drupal 7

resource "mysql_database" "d7_database" {
  for_each = var.sites

  name = "webcms_${each.value.site}_${each.value.lang}_d7"
}

resource "random_password" "d7_password" {
  for_each = var.sites

  length = 20
}

resource "mysql_user" "d7_user" {
  for_each = var.sites

  user               = mysql_database.d7_database[each.key].name
  host               = "%"
  plaintext_password = random_password.d7_password[each.key].result
}

resource "mysql_grant" "d7_grant" {
  for_each = var.sites

  user       = mysql_user.d7_user[each.key].user
  host       = mysql_user.d7_user[each.key].host
  database   = mysql_database.d7_database[each.key].name
  privileges = ["ALL PRIVILEGES"]
}

resource "aws_secretsmanager_secret_version" "d7_credentials" {
  for_each = var.sites

  secret_id = each.value.d7

  secret_string = jsonencode({
    username = mysql_user.d7_user[each.key].user
    password = random_password.d7_password[each.key].result
  })

  version_stages = ["AWSCURRENT"]
}

#endregion

#region Drupal 8

resource "mysql_database" "d8_database" {
  for_each = var.sites

  name = "webcms_${each.value.site}_${each.value.lang}_d8"
}

resource "random_password" "d8_password" {
  for_each = var.sites

  length = 20
}

resource "mysql_user" "d8_user" {
  for_each = var.sites

  user               = mysql_database.d8_database[each.key].name
  host               = "%"
  plaintext_password = random_password.d8_password[each.key].result
}

resource "mysql_grant" "d8_grant" {
  for_each = var.sites

  user       = mysql_user.d8_user[each.key].user
  host       = mysql_user.d8_user[each.key].host
  database   = mysql_database.d8_database[each.key].name
  privileges = ["ALL PRIVILEGES"]
}

resource "aws_secretsmanager_secret_version" "d8_credentials" {
  for_each = var.sites

  secret_id = each.value.d8

  secret_string = jsonencode({
    username = mysql_user.d8_user[each.key].user
    password = random_password.d8_password[each.key].result
  })
}

#endregion
