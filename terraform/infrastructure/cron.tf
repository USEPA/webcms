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

resource "aws_iam_role" "events" {
  name        = "WebCMS-${var.environment}-Cron"
  description = "IAM role for the CloudWatch cron schedule"

  assume_role_policy = data.aws_iam_policy_document.events_assume_role_policy.json

  tags = var.tags
}

data "aws_iam_policy_document" "events_task_execution" {
  version = "2012-10-17"

  # Per the docs, iam:PassRole is the permission we need to invoke our ECS task from
  # CloudWatch's events schedule. This is necessary due to our usage of the referenced
  # roles.
  # cf. https://docs.aws.amazon.com/AmazonECS/latest/developerguide/scheduled_tasks.html
  statement {
    sid     = "passTaskRoles"
    actions = ["iam:PassRole"]

    resources = concat(
      [for role in aws_iam_role.drupal_exec : role.arn],
      [for role in aws_iam_role.drupal_task : role.arn],
    )
  }

  statement {
    sid       = "invokeDrushTask"
    actions   = ["ecs:RunTask"]
    resources = ["*"]

    # Only allow spawning tasks on the WebCMS' cluster
    condition {
      test     = "StringEquals"
      variable = "ecs:Cluster"
      values   = [aws_ecs_cluster.cluster.arn]
    }
  }
}

resource "aws_iam_policy" "events_task_execution" {
  name   = "WebCMS-${var.environment}-EventsTaskExecution"
  policy = data.aws_iam_policy_document.events_task_execution.json
}

resource "aws_iam_role_policy_attachment" "events_task_execution" {
  role       = aws_iam_role.events.name
  policy_arn = aws_iam_policy.events_task_execution.arn
}
