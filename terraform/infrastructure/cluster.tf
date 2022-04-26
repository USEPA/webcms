resource "aws_ecs_cluster" "cluster" {
  name = "webcms-${var.environment}"

  capacity_providers = ["FARGATE"]

  # All tasks will use Fargate
  default_capacity_provider_strategy {
    capacity_provider = "FARGATE"
    weight            = 100
  }

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  tags = var.tags
}

# Generic assume role policy for ECS tasks
data "aws_iam_policy_document" "ecs_task_assume" {
  version = "2012-10-17"

  statement {
    sid     = "1"
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}
