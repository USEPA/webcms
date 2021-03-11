data "aws_iam_policy_document" "events_assume_role_policy" {
  version = "2012-10-17"

  statement {
    sid     = ""
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["events.amazonaws.com"]
    }
  }
}

# This task definition depends on a built and published Drush image, so we have to
# conditionally create it the same way we did for drupal_task.
resource "aws_ecs_task_definition" "drush_task" {
  family             = "webcms-drush-${local.env_suffix}"
  network_mode       = "awsvpc"
  task_role_arn      = data.aws_ssm_parameter.drupal_iam_task.value
  execution_role_arn = data.aws_ssm_parameter.drupal_iam_exec.value

  requires_compatibilities = ["FARGATE"]

  cpu    = 1024
  memory = 2048

  # See cluster.tf for more information on these parameters
  container_definitions = jsonencode([
    {
      name  = "drush"
      image = "${data.aws_ssm_parameter.ecr_repository_drush_url.value}:${var.image_tag_drush}"

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

  tags = merge(local.common_tags, {
    Name = "${local.name_prefix} Task - Drush"
  })
}

resource "aws_cloudwatch_event_target" "cron" {
  count = length(aws_ecs_task_definition.drush_task)

  target_id = "WebCMS${local.env_title}CronTask"
  arn       = data.aws_ssm_parameter.ecs_cluster_arn.value
  rule      = data.aws_ssm_parameter.cloudwatch_event_rule_cron.value
  role_arn  = data.aws_ssm_parameter.aws_iam_role_events.value

  ecs_target {
    launch_type         = "FARGATE"
    task_count          = 1
    task_definition_arn = aws_ecs_task_definition.drush_task[count.index].arn

    network_configuration {
      subnets         = data.aws_ssm_parameter.private_subnets.value
      security_groups = local.drupal_security_groups
    }
  }

  input = jsonencode({
    containerOverrides = [
      {
        name = "drush"
        command = [
          "/var/www/html/vendor/bin/drush",
          "--debug",
          "--uri", "https://${var.site_hostname}",
          "cron"
        ]
      }
    ]
  })
}
