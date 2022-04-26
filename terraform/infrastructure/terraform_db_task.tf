resource "aws_ecs_task_definition" "terraform_database_task" {
  family = "webcms-${var.environment}-database"

  task_role_arn      = aws_iam_role.terraform_database_task.arn
  execution_role_arn = aws_iam_role.terraform_database_exec.arn

  network_mode = "awsvpc"

  requires_compatibilities = ["FARGATE"]

  cpu    = 256
  memory = 1024

  container_definitions = jsonencode([
    {
      name  = "terraform"
      image = "${aws_ecr_repository.terraform_database.repository_url}:latest"

      environment = [
        # See terraform/database/variables.tf for more on these
        { name = "TF_VAR_aws_region", value = var.aws_region },
        {
          name = "TF_VAR_sites",
          value = jsonencode({
            for key, site in local.sites :
            key => merge(site, {
              d7 = aws_secretsmanager_secret.db_d7_credentials[key].arn
              d8 = aws_secretsmanager_secret.db_d8_credentials[key].arn
            })
          })
        },

        # Pass in the MySQL provider's endpoint; see https://registry.terraform.io/providers/winebarrel/mysql/latest/docs#argument-reference
        { name = "TF_VAR_mysql_endpoint", value = "${aws_rds_cluster.db.endpoint}:3306" },

        # See terraform/database/README.md for more on why these are regular env vars
        { name = "BACKEND_STORAGE", value = aws_s3_bucket.tfstate.bucket },
        { name = "BACKEND_LOCKS", value = aws_dynamodb_table.terraform_locks.name },
      ]

      secrets = [
        # Bind in the DB credentials as a TF variable. We do this because using keys from
        # inside a JSON-formatted secret is not yet supported in Fargate's LATEST version
        # (only 1.4.0), and this isn't so onerous that we can't work around it to simplify
        # the AWS CLI's run-task invocation needed to bootstrap the database secrets.
        { name = "TF_VAR_mysql_credentials", valueFrom = aws_secretsmanager_secret.db_root_credentials.arn }
      ]

      logConfiguration = {
        logDriver = "awslogs"

        options = {
          awslogs-group         = aws_cloudwatch_log_group.terraform.name
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "database"
        }
      }
    }
  ])

  tags = var.tags
}
