# Execution IAM role. This is the IAM role that ECS uses to launch Fargate tasks, and thus
# needs access to credentials but not runtime data.

resource "aws_iam_role" "drupal_exec" {
  for_each = local.sites

  name        = "${var.iam_prefix}-${var.environment}-${each.key}-DrupalExecution"
  description = "WebCMS task execution role"

  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json

  tags = var.tags
}

resource "aws_iam_role_policy_attachment" "drupal_exec" {
  for_each = local.sites

  role       = aws_iam_role.drupal_exec[each.key].name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

data "aws_iam_policy_document" "drupal_secrets_access" {
  for_each = local.sites

  statement {
    sid     = "secretsAccess"
    effect  = "Allow"
    actions = ["secretsmanager:GetSecretValue"]

    resources = [
      aws_secretsmanager_secret.db_d8_credentials[each.key].arn,
      aws_secretsmanager_secret.db_d7_credentials[each.key].arn,
      aws_secretsmanager_secret.hash_salt[each.key].arn,
      aws_secretsmanager_secret.mail_pass[each.key].arn,
      aws_secretsmanager_secret.saml_sp_key[each.key].arn,
      aws_secretsmanager_secret.akamai_access_token[each.key].arn,
      aws_secretsmanager_secret.akamai_client_token[each.key].arn,
      aws_secretsmanager_secret.akamai_client_secret[each.key].arn,
    ]
  }
}

resource "aws_iam_policy" "drupal_secrets_access" {
  for_each = local.sites

  name        = "${var.iam_prefix}-${var.environment}-${each.key}-SecretsAccess"
  description = "Grants access to the WebCMS' secrets"

  policy = data.aws_iam_policy_document.drupal_secrets_access[each.key].json
}

resource "aws_iam_role_policy_attachment" "drupal_secrets_access" {
  for_each = local.sites

  role       = aws_iam_role.drupal_exec[each.key].name
  policy_arn = aws_iam_policy.drupal_secrets_access[each.key].arn
}
