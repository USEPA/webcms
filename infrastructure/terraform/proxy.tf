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

resource "aws_iam_role" "proxy" {
  name        = "${local.role-prefix}RDSProxyRole"
  description = "Role for the cluster's RDS proxy"

  assume_role_policy = data.aws_iam_policy_document.proxy_assume.json

  tags = local.common-tags
}

data "aws_kms_alias" "secretsmanager" {
  name = "alias/aws/secretsmanager"
}

data "aws_iam_policy_document" "proxy_secrets" {
  version = "2012-10-17"

  statement {
    sid     = "secretAccess"
    effect  = "Allow"
    actions = ["secretsmanager:GetSecretValue"]

    resources = [
      aws_secretsmanager_secret.db_app_credentials.arn,
      aws_secretsmanager_secret.db_app_d7_credentials.arn,
    ]
  }

  statement {
    sid       = "decryptSecret"
    effect    = "Allow"
    actions   = ["kms:Decrypt"]
    resources = [data.aws_kms_alias.secretsmanager.target_key_arn]

    condition {
      test     = "StringEquals"
      variable = "kms:ViaService"
      values   = ["secretsmanager.${var.aws-region}.amazonaws.com"]
    }
  }
}

resource "aws_iam_policy" "proxy_secrets" {
  name        = "${local.role-prefix}RDSProxySecretsAccess"
  description = "Grants the RDS proxy access to DB credentials"
  policy      = data.aws_iam_policy_document.proxy_secrets.json
}

resource "aws_iam_role_policy_attachment" "proxy_secrets" {
  role       = aws_iam_role.proxy.name
  policy_arn = aws_iam_policy.proxy_secrets.arn
}

resource "aws_db_proxy" "proxy" {
  name = "webcms-db-proxy-${local.env-suffix}"

  role_arn               = aws_iam_role.proxy.arn
  engine_family          = "MYSQL"
  vpc_subnet_ids         = aws_subnet.private[*].id
  vpc_security_group_ids = [aws_security_group.proxy.id, aws_security_group.database_access.id]

  auth {
    iam_auth    = "DISABLED"
    auth_scheme = "SECRETS"
    secret_arn  = aws_secretsmanager_secret.db_app_credentials.arn
  }

  auth {
    iam_auth    = "DISABLED"
    auth_scheme = "SECRETS"
    secret_arn  = aws_secretsmanager_secret.db_app_d7_credentials.arn
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} RDS Proxy"
  })
}

resource "aws_db_proxy_default_target_group" "proxy" {
  db_proxy_name = aws_db_proxy.proxy.name

  connection_pool_config {
    connection_borrow_timeout    = 120
    max_connections_percent      = 100
    max_idle_connections_percent = 50
  }
}
