resource "aws_iam_role" "traefik_task" {
  name        = "${var.iam_prefix}-${var.environment}-TraefikTask"
  description = "Task role for the Traefik router"

  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json

  tags = var.tags
}

# Traefik needs to be able to see ECS resources. (TODO: Determine if we should scope this
# policy to just the WebCMS application cluster.)
# cf. https://doc.traefik.io/traefik/providers/ecs/
data "aws_iam_policy_document" "traefik_ecs_access" {
  version = "2012-10-17"

  statement {
    sid    = "ecsReadAccess"
    effect = "Allow"

    actions = [
      "ecs:ListClusters",
      "ecs:ListTasks",
      "ecs:DescribeClusters",
      "ecs:DescribeTasks",
      "ecs:DescribeContainerInstances",
      "ecs:DescribeTaskDefinition",
      "ec2:DescribeInstances"
    ]

    resources = ["*"]
  }
}

resource "aws_iam_policy" "traefik_ecs_access" {
  name        = "${var.iam_prefix}-${var.environment}-TraefikECSAccess"
  description = "Grants read-only access to ECS for Traefik"

  policy = data.aws_iam_policy_document.traefik_ecs_access.json
}

resource "aws_iam_role_policy_attachment" "traefik_ecs_access" {
  role       = aws_iam_role.traefik_task.name
  policy_arn = aws_iam_policy.traefik_ecs_access.arn
}

# The execution role only needs standard execution permissions - we do not bind secrets
# or other sensitive values to the router.

resource "aws_iam_role" "traefik_exec" {
  name        = "${var.iam_prefix}-${var.environment}-TraefikExecution"
  description = "Execution role for the Traefik router"

  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json

  tags = var.tags
}

resource "aws_iam_role_policy_attachment" "traefik_exec" {
  role       = aws_iam_role.traefik_exec.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}
