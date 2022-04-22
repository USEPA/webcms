#region Permissions

data "aws_iam_policy_document" "proxy_assume" {
  version = "2012-10-17"

  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["rds.amazonaws.com"]
    }
  }
}

# Per the AWS documentation, we need to create an IAM role for the proxy to access the
# various Secrets Manager credentials.
resource "aws_iam_role" "proxy" {
  name        = "${var.iam_prefix}-${var.environment}-Proxy"
  description = "Role for the cluster's RDS proxy"

  assume_role_policy = data.aws_iam_policy_document.proxy_assume.json

  tags = var.tags
}

# Read out the default Secrets Manager ARN using the alias
data "aws_kms_alias" "secretsmanager" {
  name = "alias/aws/secretsmanager"
}

# Grant access to all site/lang secrets (but NOT the root credentials!)
data "aws_iam_policy_document" "proxy_secrets" {
  version = "2012-10-17"

  statement {
    sid     = "secretAccess"
    effect  = "Allow"
    actions = ["secretsmanager:GetSecretValue"]

    resources = concat(
      [for secret in aws_secretsmanager_secret.db_d8_credentials : secret.arn],
      [for secret in aws_secretsmanager_secret.db_d7_credentials : secret.arn],
    )
  }

  statement {
    sid       = "decryptSecret"
    effect    = "Allow"
    actions   = ["kms:Decrypt"]
    resources = [data.aws_kms_alias.secretsmanager.target_key_arn]

    condition {
      test     = "StringEquals"
      variable = "kms:ViaService"
      values   = ["secretsmanager.${var.aws_region}.amazonaws.com"]
    }
  }
}

resource "aws_iam_policy" "proxy_secrets" {
  name        = "${var.iam_prefix}-${var.environment}-ProxySecretsAccess"
  description = "Grants the RDS proxy access to DB credentials"
  policy      = data.aws_iam_policy_document.proxy_secrets.json
}

resource "aws_iam_role_policy_attachment" "proxy_secrets" {
  role       = aws_iam_role.proxy.name
  policy_arn = aws_iam_policy.proxy_secrets.arn
}

#endregion

#region Resources

resource "aws_db_proxy" "proxy" {
  name = "webcms-${var.environment}-proxy"

  role_arn               = aws_iam_role.proxy.arn
  engine_family          = "MYSQL"
  vpc_subnet_ids         = local.private_subnets
  vpc_security_group_ids = [data.aws_ssm_parameter.proxy_security_group.value]

  dynamic "auth" {
    for_each = concat(
      values(aws_secretsmanager_secret.db_d8_credentials),
      values(aws_secretsmanager_secret.db_d7_credentials),
    )

    content {
      iam_auth    = "DISABLED"
      auth_scheme = "SECRETS"
      secret_arn  = auth.value.arn
    }
  }

  tags = var.tags
}

resource "aws_db_proxy_default_target_group" "proxy" {
  db_proxy_name = aws_db_proxy.proxy.name

  connection_pool_config {
    connection_borrow_timeout    = 120
    max_connections_percent      = 100
    max_idle_connections_percent = 50
  }
}

resource "aws_db_proxy_target" "proxy" {
  db_proxy_name     = aws_db_proxy.proxy.name
  target_group_name = aws_db_proxy_default_target_group.proxy.name

  db_cluster_identifier = aws_rds_cluster.db.cluster_identifier
}

#endregion
