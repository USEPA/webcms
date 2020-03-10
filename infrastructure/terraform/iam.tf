# ECS service role

data "aws_iam_policy_document" "ecs_assume_role_policy" {
  version = "2012-10-17"

  statement {
    sid     = "1"
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ecs.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "ecs_cluster_role" {
  name        = "WebCMSAppClusterRole"
  description = "Role for the WebCMS's cluster"

  assume_role_policy = data.aws_iam_policy_document.ecs_assume_role_policy.json

  tags = {
    Application = "WebCMS"
  }
}

# resource "aws_iam_role_policy_attachment" "ecs_policy" {
#   role = aws_iam_role.ecs_cluster_role.name
#   policy_arn = "arn:aws:iam::aws:policy/aws-service-role/AmazonECSServiceRolePolicy"
# }

# EC2 service role
# Provides IAM permissions for the EC2 instances (and, presumably, the ECS agent)

data "aws_iam_policy_document" "ec2_assume_role_policy" {
  version = "2012-10-17"

  statement {
    sid     = "1"
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "ec2_server_role" {
  name        = "WebCMSAppInstanceRole"
  description = "Role for the WebCMS's EC2 instances"

  assume_role_policy = data.aws_iam_policy_document.ec2_assume_role_policy.json

  tags = {
    Application = "WebCMS"
  }
}

data "aws_iam_policy_document" "ec2_instance_profile" {
  version = "2012-10-17"

  # Permissions needed to participate in service autoscaling
  # cf. https://docs.aws.amazon.com/AmazonECS/latest/developerguide/service-auto-scaling.html
  statement {
    sid    = "allowServiceAutoScaling"
    effect = "Allow"
    actions = [
      "application-autoscaling:*",
      "ecs:DescribeServices",
      "ecs:UpdateService",
      "cloudwatch:DescribeAlarms",
      "cloudwatch:PutMetricAlarm",
      "cloudwatch:DeleteAlarms",
      "cloudwatch:DescribeAlarmHistory",
      "cloudwatch:DescribeAlarms",
      "cloudwatch:DescribeAlarmsForMetric",
      "cloudwatch:GetMetricStatistics",
      "cloudwatch:ListMetrics",
      "cloudwatch:PutMetricAlarm",
      "cloudwatch:DisableAlarmActions",
      "cloudwatch:EnableAlarmActions",
      "iam:CreateServiceLinkedRole",
      "sns:CreateTopic",
      "sns:Subscribe",
      "sns:Get*",
      "sns:List*",
    ]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "ec2_instance_cluster" {
  name   = "WebCMSAppAutoscalingPolicy"
  role   = aws_iam_role.ec2_server_role.name
  policy = data.aws_iam_policy_document.ec2_instance_profile.json
}

resource "aws_iam_role_policy_attachment" "ec2_instance_cluster" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonEC2ContainerServiceforEC2Role"
}

resource "aws_iam_role_policy_attachment" "ec2_instance_registry" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonEC2ContainerRegistryReadOnly"
}

resource "aws_iam_instance_profile" "ec2_servers" {
  name = "WebCMSAppInstanceProfile"
  role = aws_iam_role.ec2_server_role.name
}

# ECS task policy for Drupal containers
# This IAM role is attached to the Drupal ECS service in order to avoid leaking the EC2
# permissions and granting Drupal too much access to the AWS API.

data "aws_iam_policy_document" "drupal_assume_role_policy" {
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

resource "aws_iam_role" "drupal_container_role" {
  name        = "WebCMSDrupalContainerRole"
  description = "Role for the WebCMS's Drupal app containers"

  assume_role_policy = data.aws_iam_policy_document.drupal_assume_role_policy.json

  tags = {
    Application = "WebCMS"
  }
}

data "aws_iam_policy_document" "drupal_container_policy" {
  version = "2012-10-17"

  statement {
    sid    = "allowUploadsReadAccess"
    effect = "Allow"
    actions = [
      "s3:HeadBucket",
      "s3:ListBucket",
    ]
    resources = [
      "arn:aws:s3:::${aws_s3_bucket.uploads.bucket}"
    ]
  }

  statement {
    sid    = "allowUploadsObjectMutation"
    effect = "Allow"
    actions = [
      "s3:DeleteObject",
      "s3:GetObject",
      "s3:PutObject",
    ]
    resources = [
      "arn:aws:s3:::${aws_s3_bucket.uploads.bucket}/*"
    ]
  }
}

resource "aws_iam_role_policy" "drupal_container_policy" {
  name   = "WebCMSDrupalContainerPolicy"
  role   = aws_iam_role.drupal_container_role.name
  policy = data.aws_iam_policy_document.drupal_container_policy.json
}

data "aws_iam_policy_document" "task_parameter_access" {
  version = "2012-10-17"

  statement {
    sid     = "allowParameterAccess"
    effect  = "Allow"
    actions = ["ssm:GetParameters"]

    resources = [
      aws_ssm_parameter.db_app_username.arn,
      aws_ssm_parameter.db_app_password.arn,
      aws_ssm_parameter.db_app_database.arn,
    ]
  }
}

resource "aws_iam_policy" "task_parameter_access" {
  name = "WebCMSTaskParameterAccess"

  policy = data.aws_iam_policy_document.task_parameter_access.json
}

data "aws_iam_policy_document" "drupal_execution_assume_role" {
  version = "2012-10-17"

  statement {
    sid     = ""
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

# ECS assumes the task execution role in order to pull container images and access the
# parameter secrets we've identified in the Drupal task definitions. This is not the same
# as the task role itself, which is how Drupal itself is identified.
resource "aws_iam_role" "drupal_execution_role" {
  name        = "WebCMSDrupalTaskExecutionRole"
  description = "Task execution role for Drupal containers"

  assume_role_policy = data.aws_iam_policy_document.drupal_execution_assume_role.json

  tags = {
    Application = "WebCMS"
  }
}

# Attach the AWS-managed default task execution policy
resource "aws_iam_role_policy_attachment" "drupal_execution_tasks" {
  role       = aws_iam_role.drupal_execution_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# Grant access to the app's username & password parameters
resource "aws_iam_role_policy_attachment" "drupal_execution_parameters" {
  role       = aws_iam_role.drupal_execution_role.name
  policy_arn = aws_iam_policy.task_parameter_access.arn
}
