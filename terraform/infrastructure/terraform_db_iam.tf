#region Execution Role

resource "aws_iam_role" "terraform_database_exec" {
  name        = "${var.iam_prefix}-${var.environment}-TerraformDatabaseExecution"
  description = "Role to execute the Terraform-based database initialization process"

  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json

  tags = var.tags
}

resource "aws_iam_role_policy_attachment" "terraform_database_exec" {
  role       = aws_iam_role.terraform_database_exec.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

data "aws_iam_policy_document" "terraform_database_credentials_access" {
  version = "2012-10-17"

  statement {
    sid       = "read"
    effect    = "Allow"
    actions   = ["secretsmanager:GetSecretValue"]
    resources = [aws_secretsmanager_secret.db_root_credentials.arn]
  }
}

resource "aws_iam_policy" "terraform_database_credentials_access" {
  name        = "${var.iam_prefix}-${var.environment}-TerraformDatabaseRootCredsAccess"
  description = "Allows binding the root RDS credentials to the Terraform database task"

  policy = data.aws_iam_policy_document.terraform_database_credentials_access.json
}

resource "aws_iam_role_policy_attachment" "terraform_database_credentials_access" {
  role       = aws_iam_role.terraform_database_exec.name
  policy_arn = aws_iam_policy.terraform_database_credentials_access.arn
}

#endregion

#region Task Role

resource "aws_iam_role" "terraform_database_task" {
  name        = "${var.iam_prefix}-${var.environment}-TerraformDatabaseTask"
  description = "Role for the Terraform-based database initialization process"

  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json

  tags = var.tags
}

data "aws_iam_policy_document" "terraform_database_tfstate_access" {
  version = "2012-10-17"

  statement {
    sid       = "readBucket"
    effect    = "Allow"
    actions   = ["s3:Listbucket"]
    resources = [aws_s3_bucket.tfstate.arn]
  }

  statement {
    sid       = "readWriteState"
    effect    = "Allow"
    actions   = ["s3:GetObject", "s3:PutObject"]
    resources = ["${aws_s3_bucket.tfstate.arn}/database.tfstate"]
  }
}

resource "aws_iam_policy" "terraform_database_tfstate_access" {
  name        = "${var.iam_prefix}-${var.environment}-TerraformDatabaseTFStateAccess"
  description = "Grants access to database.tfstate"

  policy = data.aws_iam_policy_document.terraform_database_tfstate_access.json
}

resource "aws_iam_role_policy_attachment" "terraform_database_tfstate_access" {
  role       = aws_iam_role.terraform_database_task.name
  policy_arn = aws_iam_policy.terraform_database_tfstate_access.arn
}

resource "aws_iam_role_policy_attachment" "terraform_database_locks_access" {
  role       = aws_iam_role.terraform_database_task.name
  policy_arn = aws_iam_policy.terraform_locks_access.arn
}

data "aws_iam_policy_document" "terraform_database_secrets_access" {
  version = "2012-10-17"

  statement {
    sid    = "readWrite"
    effect = "Allow"

    actions = [
      "secretsmanager:GetSecretValue",
      "secretsmanager:PutSecretValue",
      "secretsmanager:UpdateSecretVersionStage",
    ]

    resources = concat(
      [for secret in aws_secretsmanager_secret.db_d8_credentials : secret.arn],
      [for secret in aws_secretsmanager_secret.db_d7_credentials : secret.arn],
    )
  }
}

resource "aws_iam_policy" "terraform_database_secrets_access" {
  name        = "${var.iam_prefix}-${var.environment}-TerraformDatabaseSecretsAccess"
  description = "Grants access to the DB credentials for initialization"

  policy = data.aws_iam_policy_document.terraform_database_secrets_access.json
}

resource "aws_iam_role_policy_attachment" "terraform_database_secrets_access" {
  role       = aws_iam_role.terraform_database_task.name
  policy_arn = aws_iam_policy.terraform_database_secrets_access.arn
}

#endregion
