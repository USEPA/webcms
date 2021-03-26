# Define the Drush task

resource "aws_ecs_task_definition" "drush_task" {
  family             = "webcms-${var.environment}-${var.site}-${var.lang}-drush"
  network_mode       = "awsvpc"
  task_role_arn      = data.aws_ssm_parameter.drupal_iam_task.value
  execution_role_arn = data.aws_ssm_parameter.drupal_iam_exec.value

  requires_compatibilities = ["FARGATE"]

  cpu    = 1024
  memory = 2048

  container_definitions = jsonencode([
    {
      name  = "drush"
      image = "${data.aws_ssm_parameter.ecr_drush.value}:${var.image_tag}"

      # By explicitly emptying these values, we allow task overrides to effectively
      # take precedence over what is specified in the container. This enables us
      # to specify "drush cron" in the CloudWatch event schedule, and run a shell
      # script that runs "drush updb".
      entryPoint = []
      command    = []

      workingDirectory = "/var/www/html"

      # Use shared bindings so that Drush tasks can connect the same way Drupal does
      environment = local.drupal_environment
      secrets     = local.drupal_secrets

      # Unlike the Drupal container, this uses the drush log group in order to make the
      # logs for scheduled tasks easier to separate from the main Drupal PHP-FPM logs.
      logConfiguration = {
        logDriver = "awslogs"

        options = {
          awslogs-group         = data.aws_ssm_parameter.drush_log_group.value
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "drush"
        }
      }
    }
  ])

  tags = var.tags
}

resource "aws_cloudwatch_event_target" "cron" {
  target_id = "WebCMS-${var.environment}-${var.site}-${var.lang}-CronTask"
  arn       = data.aws_ssm_parameter.ecs_cluster_arn.value
  rule      = data.aws_ssm_parameter.cron_event_rule.value
  role_arn  = data.aws_ssm_parameter.cron_event_role.value

  ecs_target {
    launch_type         = "FARGATE"
    platform_version    = "1.4.0"
    task_count          = 1
    task_definition_arn = aws_ecs_task_definition.drush_task.arn

    network_configuration {
      subnets         = local.private_subnets
      security_groups = [data.aws_ssm_parameter.drupal_security_group.value]
    }
  }

  input = jsonencode({
    containerOverrides = [
      {
        name = "drush"
        command = [
          "/var/www/html/vendor/bin/drush",
          "--debug",
          "--uri", "https://${var.drupal_hostname}",
          "cron"
        ]
      }
    ]
  })
}
