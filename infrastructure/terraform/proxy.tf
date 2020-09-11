data "aws_kms_alias" "secretsmanager_alias" {
  name = "alias/aws/secretsmanager"
}

data "aws_iam_policy_document" "proxy_assume" {
  version = "2012-10-17"

  statement {
    sid     = "1"
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["rds.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "proxy_role" {
  name        = "${local.role-prefix}RDSProxy"
  description = "Role for the WebCMS' RDS proxy"

  assume_role_policy = data.aws_iam_policy_document.proxy_assume.json

  tags = local.common-tags
}


data "aws_iam_policy_document" "proxy_secrets" {
  version = "2012-10-17"

  statement {
    sid     = "allowSecretsAccess"
    effect  = "Allow"
    actions = ["secretsmanager:GetSecretValue"]

    resources = [
      aws_secretsmanager_secret.db_app_credentials.arn,
      aws_secretsmanager_secret.db_app_d7_credentials.arn,
    ]
  }

  statement {
    sid     = "allowDecryption"
    effect  = "Allow"
    actions = ["kms:Decrypt"]

    condition {
      test     = "StringEquals"
      variable = "kms:ViaService"
      values   = ["secretsmanager.${var.aws-region}.amazonaws.com"]
    }
  }
}

resource "aws_iam_policy" "proxy_secrets" {
  name        = "${local.role-prefix}RDSProxySecrets"
  description = "Policy to grant access to DB connection secrets"
  policy      = data.aws_iam_policy_document.proxy_secrets.json
}

resource "aws_iam_role_policy_attachment" "proxy_secrets" {
  role       = aws_iam_role.proxy_role.name
  policy_arn = aws_iam_policy.proxy_secrets.arn
}

resource "aws_db_proxy" "proxy" {
  name = "webcms-rds-proxy-${local.env-suffix}"

  debug_logging  = false
  engine_family  = "MYSQL"
  require_tls    = true
  role_arn       = aws_iam_role.proxy_role.arn
  vpc_subnet_ids = aws_subnet.private[*].id

  vpc_security_group_ids = [
    aws_security_group.database.id,
    aws_security_group.database_access.id,
  ]

  auth {
    auth_scheme = "SECRETS"
    iam_auth    = "DISABLED"
    secret_arn  = aws_secretsmanager_secret.db_app_credentials.arn
  }

  auth {
    auth_scheme = "SECRETS"
    iam_auth    = "DISABLED"
    secret_arn  = aws_secretsmanager_secret.db_app_d7_credentials.arn
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} RDS Proxy"
  })
}
