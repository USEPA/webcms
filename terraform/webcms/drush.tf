# Define the Drush task

resource "aws_ecs_task_definition" "drush_task" {
  family             = "webcms-${var.environment}-${var.site}-${var.lang}-drush"
  network_mode       = "awsvpc"
  task_role_arn      = data.aws_ssm_parameter.drupal_iam_task.value
  execution_role_arn = data.aws_ssm_parameter.drupal_iam_exec.value

  requires_compatibilities = ["FARGATE"]

  cpu    = 1024
  memory = 2048

  runtime_platform {
    operating_system_family = "LINUX"
    cpu_architecture        = var.cpu_architecture
  }

  container_definitions = jsonencode([
    {
      name  = "drush"
      image = "${data.aws_ssm_parameter.ecr_drush.value}:${var.image_tag}"

      # Set the default command for our task definition to be a cron-like shell
      # script loop. Busybox's crond doesn't seem to function when running as a
      # non-root user, and even if it did, we only have one scheduled task.
      command = ["sh", "-c", "CRON_INTERVAL=60; while sleep $${CRON_INTERVAL}m; do echo \"$(date): Running cron\" && drush \"--uri=$${WEBCMS_SITE_URL}\" cron && echo \"$(date): Cron completed successfully\" || echo \"$(date): Cron failed with exit code $?\"; done"]

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
    },
  ])

  tags = var.tags
}

# This service is mostly identical to the Drupal service, save that it runs a
# persistent Drush container for cron. (This is needed to avoid creating a large
# number of AWS Config records every time EventBridge spawns a new container.)
#
# There are only two major differences between this and the Drupal service:
# 1. It uses the Drush task definition and
# 2. It does not define a load_balancer section.
resource "aws_ecs_service" "drush" {
  name            = "webcms-${var.environment}-${var.site}-${var.lang}-drush"
  cluster         = data.aws_ssm_parameter.ecs_cluster_arn.value
  desired_count   = var.drush_tasks
  task_definition = aws_ecs_task_definition.drush_task.arn

  launch_type      = "FARGATE"
  platform_version = "1.4.0"

  deployment_controller {
    type = "ECS"
  }

  network_configuration {
    subnets          = local.private_subnets
    assign_public_ip = false

    security_groups = [data.aws_ssm_parameter.drupal_security_group.value]
  }

  tags = var.tags
}
