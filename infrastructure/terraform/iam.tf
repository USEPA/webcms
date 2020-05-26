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
    Group = "webcms"
  }
}

# resource "aws_iam_role_policy_attachment" "ecs_policy" {
#   role = aws_iam_role.ecs_cluster_role.name
#   policy_arn = "arn:aws:iam::aws:policy/aws-service-role/AmazonECSServiceRolePolicy"
# }

# SSM-related S3 permissions

data "aws_iam_policy_document" "ssm_s3_policy" {
  version = "2012-10-17"

  statement {
    sid     = "allowReadAccess"
    effect  = "Allow"
    actions = ["s3:GetObject"]

    # cf. https://docs.aws.amazon.com/systems-manager/latest/userguide/ssm-agent-minimum-s3-permissions.html
    resources = [
      "arn:aws:s3:::aws-ssm-${var.aws-region}/*",
      "arn:aws:s3:::amazon-ssm-${var.aws-region}/*",
      "arn:aws:s3:::${var.aws-region}-birdwatcher-prod/*",
      "arn:aws:s3:::aws-ssm-document-attachments-${var.aws-region}/*",
      "arn:aws:s3:::patch-baseline-snapshot-${var.aws-region}/*",

      # Ignored buckets:
      # We're not on Windows: arn:aws:s3:::aws-windows-downloads-$REGION/*
      # We're not using older SSM agents: arn:aws:s3:::amazon-ssm-packages-$REGION/*
    ]
  }
}

resource "aws_iam_policy" "ssm_s3_policy" {
  name        = "WebCMSAccessPolicyForSSMAndS3"
  description = "Policy to grant access to SSM-related S3 buckets"
  policy      = data.aws_iam_policy_document.ssm_s3_policy.json
}

data "aws_iam_policy_document" "ssm_session_policy" {
  version = "2012-10-17"

  statement {
    sid       = "allowMessaging"
    effect    = "Allow"
    resources = ["*"]

    actions = [
      "ssmmessages:CreateControlChannel",
      "ssmmessages:CreateDataChannel",
      "ssmmessages:OpenControlChannel",
      "ssmmessages:OpenDataChannel"
    ]
  }

  statement {
    sid       = "allowGetConfiguration"
    effect    = "Allow"
    actions   = ["s3:GetEncryptionConfiguration"]
    resources = ["*"]
  }

  statement {
    sid       = "allowDecryption"
    effect    = "Allow"
    actions   = ["kms:Decrypt"]
    resources = [var.ssm-customer-key]
  }
}

resource "aws_iam_policy" "ssm_session_policy" {
  name        = "WebCMSSessionPolicyForSSM"
  description = "Policy to grant servers access to SSM sessions"
  policy      = data.aws_iam_policy_document.ssm_session_policy.json
}

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
    Group = "webcms"
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

# This policy is used by container instances on start-up to assign attributes to
# themselves based on whether or not they're spot instances so that we can avoid running
# Drush on the more volatile spot instances.
data "aws_iam_policy_document" "ec2_attributes" {
  version = "2012-10-17"

  # EC2 instance metadata doesn't inform us if our instance is spot or on-demand, so we
  # use the EC2 API to describe instances. Even though we can't limit this in the policy,
  # the instance bootstrap (see servers.tf) hook will only ever use its own instance ID.
  statement {
    sid       = "allowInstanceRead"
    effect    = "Allow"
    actions   = ["ec2:DescribeInstances"]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "ec2_attributes" {
  name   = "WebCMSInstanceAttributesPolicy"
  role   = aws_iam_role.ec2_server_role.name
  policy = data.aws_iam_policy_document.ec2_attributes.json
}

resource "aws_iam_role_policy_attachment" "ec2_instance_cluster" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonEC2ContainerServiceforEC2Role"
}

resource "aws_iam_role_policy_attachment" "ec2_instance_registry" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonEC2ContainerRegistryReadOnly"
}

# Allow cluster servers to interact with SSM
resource "aws_iam_role_policy_attachment" "ec2_ssm" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy_attachment" "ec2_ssm_s3" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = aws_iam_policy.ssm_s3_policy.arn
}

resource "aws_iam_role_policy_attachment" "ec2_ssm_session" {
  role       = aws_iam_role.ec2_server_role.name
  policy_arn = aws_iam_policy.ssm_session_policy.arn
}

resource "aws_iam_role_policy_attachment" "ec2_extra_policies" {
  for_each = toset(var.server-extra-policies)

  role       = aws_iam_role.ec2_server_role.name
  policy_arn = each.value
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
    Group = "webcms"
  }
}

data "aws_iam_policy_document" "uploads_access" {
  version = "2012-10-17"

  statement {
    sid    = "allowUploadsReadAccess"
    effect = "Allow"
    actions = [
      "s3:HeadBucket",
      "s3:ListBucket",
      "s3:ListBucketVersions",
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
      "s3:GetObjectAcl",
      "s3:PutObject",
      "s3:PutObjectAcl",
    ]
    resources = [
      "arn:aws:s3:::${aws_s3_bucket.uploads.bucket}/*"
    ]
  }
}

resource "aws_iam_policy" "uploads_access" {
  name        = "WebCMSUploadsAccess"
  description = "Grants read/write access to the WebCMS' uploads bucket"

  policy = data.aws_iam_policy_document.uploads_access.json
}

resource "aws_iam_role_policy_attachment" "drupal_uploads_access" {
  role       = aws_iam_role.drupal_container_role.name
  policy_arn = aws_iam_policy.uploads_access.arn
}

# This is a policy for read/write access to the cluster's data, but not for
# cluster management.
data "aws_iam_policy_document" "es_access" {
  version = "2012-10-17"

  statement {
    sid    = "allowSearchAccess"
    effect = "Allow"

    actions = [
      "es:ESHttpDelete",
      "es:ESHttpGet",
      "es:ESHttpHead",
      "es:ESHttpPost",
      "es:ESHttpPut",
      "es:ESHttpPatch",
    ]

    # Only allow access to the configured domain
    resources = ["${aws_elasticsearch_domain.es.arn}/*"]
  }
}

resource "aws_iam_policy" "es_access" {
  name        = "WebCMSElasticsearchAccess"
  description = "Grants read/write access to Elasticsearch"

  policy = data.aws_iam_policy_document.es_access.json
}


# Grant read/write access to the Elasticsearch cluster
resource "aws_iam_role_policy_attachment" "drupal_es_access" {
  role       = aws_iam_role.drupal_container_role.name
  policy_arn = aws_iam_policy.es_access.arn
}

data "aws_iam_policy_document" "task_secrets_access" {
  version = "2012-10-17"

  statement {
    sid     = "allowSecretAccess"
    effect  = "Allow"
    actions = ["secretsmanager:GetSecretValue"]

    # This policy does not - and should not - grant access to the root DB password.
    # Drupal doesn't need it.
    resources = [
      aws_secretsmanager_secret.db_app_password.arn,
      aws_secretsmanager_secret.hash_salt.arn,
      aws_secretsmanager_secret.mail_pass.arn,
    ]
  }
}

resource "aws_iam_policy" "task_secrets_access" {
  name        = "WebCMSTaskSecretsAccess"
  description = "Grants read access to the WebCMS's secrets"

  policy = data.aws_iam_policy_document.task_secrets_access.json
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
# secrets we've identified in the Drupal task definitions. This is not the same as the
# task role itself, which is how Drupal itself is identified.
resource "aws_iam_role" "drupal_execution_role" {
  name        = "WebCMSDrupalTaskExecutionRole"
  description = "Task execution role for Drupal containers"

  assume_role_policy = data.aws_iam_policy_document.drupal_execution_assume_role.json

  tags = {
    Group = "webcms"
  }
}

# Attach the AWS-managed default task execution policy
resource "aws_iam_role_policy_attachment" "drupal_execution_tasks" {
  role       = aws_iam_role.drupal_execution_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# Grant access to the app's secrets
resource "aws_iam_role_policy_attachment" "drupal_execution_parameters" {
  role       = aws_iam_role.drupal_execution_role.name
  policy_arn = aws_iam_policy.task_secrets_access.arn
}

data "aws_iam_policy_document" "bastion_assume" {
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

resource "aws_iam_role" "bastion_role" {
  name        = "WebCMSBastionRole"
  description = "IAM role for the utility EC2 instance"

  assume_role_policy = data.aws_iam_policy_document.bastion_assume.json

  tags = {
    Group = "webcms"
  }
}

resource "aws_iam_role_policy_attachment" "bastion_ssm" {
  role       = aws_iam_role.bastion_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy_attachment" "bastion_ssm_s3" {
  role       = aws_iam_role.bastion_role.name
  policy_arn = aws_iam_policy.ssm_s3_policy.arn
}

resource "aws_iam_role_policy_attachment" "bastion_ssm_session" {
  role       = aws_iam_role.bastion_role.name
  policy_arn = aws_iam_policy.ssm_session_policy.arn
}

resource "aws_iam_instance_profile" "bastion_profile" {
  name = "WebCMSUtilityInstanceProfile"
  role = aws_iam_role.bastion_role.name
}

# SSM role

data "aws_iam_policy_document" "ssm_assume" {
  version = "2012-10-17"

  statement {
    sid = "1"

    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ssm.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "ssm" {
  name               = "WebCMSSystemsManagerRole"
  description        = "Role for the Systems Manager service"
  assume_role_policy = data.aws_iam_policy_document.ssm_assume.json
}

data "aws_iam_policy_document" "ssm_policy" {
  version = "2012-10-17"

  statement {
    sid = "ssmPolicy"

    effect    = "Allow"
    resources = ["*"]

    actions = [
      "iam:CreateInstanceProfile",
      "iam:ListInstanceProfilesForRole",
      "iam:PassRole",
      "ec2:DescribeIamInstanceProfileAssociations",
      "iam:GetInstanceProfile",
      "ec2:DisassociateIamInstanceProfile",
      "ec2:AssociateIamInstanceProfile",
      "iam:AddRoleToInstanceProfile"
    ]
  }
}

resource "aws_iam_policy" "ssm_policy" {
  name        = "WebCMSPolicyForSystemsManager"
  description = "Grants permission to perform Systems Manager functions"
  policy      = data.aws_iam_policy_document.ssm_policy.json
}

resource "aws_iam_role_policy_attachment" "ssm" {
  role       = aws_iam_role.ssm.name
  policy_arn = aws_iam_policy.ssm_policy.arn
}

# User-level policies for interacting with AWS SSM

data "aws_iam_policy_document" "user_ssm_policy" {
  version = "2012-10-17"

  statement {
    sid = "startSession"

    effect    = "Allow"
    actions   = ["ssm:StartSession", "ssm:SendCommand"]
    resources = ["arn:aws:ec2:*:*:instance/*"]

    # Limit this policy only to WebCMS EC2 instances
    condition {
      test     = "StringLike"
      variable = "ssm:resourceTag/Application"
      values   = ["WebCMS"]
    }
  }

  statement {
    sid = "sessionManagement"

    effect    = "Allow"
    resources = ["*"]

    actions = [
      "ssm:DescribeSessions",
      "ssm:GetConnectionStatus",
      "ssm:DescribeInstanceInformation",
      "ssm:DescribeInstanceProperties",
      "ec2:DescribeInstances"
    ]
  }

  statement {
    sid = "endSession"

    effect    = "Allow"
    actions   = ["ssm:TerminateSession"]
    resources = ["arn:aws:ssm:*:*:session/$${aws:username}-*"]
  }

  statement {
    sid = "allowGetDocument"

    effect  = "Allow"
    actions = ["ssm:GetDocument"]

    resources = [
      "arn:aws:ssm:${var.aws-region}:${data.aws_caller_identity.current.account_id}:document/SSM-SessionManagerRunShell",
      "arn:aws:ssm:*:*:document/AWS-StartSSHSession"
    ]

    # TODO: Validate that this won't break user access
    # condition {
    #   test     = "BoolIfExists"
    #   variable = "ssm:SessionDocumentAccessCheck"
    #   values   = ["true"]
    # }
  }

  statement {
    sid = "allowSessionEncryption"

    effect    = "Allow"
    actions   = ["kms:GenerateDataKey"]
    resources = [var.ssm-customer-key]
  }
}

resource "aws_iam_policy" "user_ssm_policy" {
  name        = "WebCMSUserAccessPolicyForSSM"
  description = "Grants Session Manager access for users"
  policy      = data.aws_iam_policy_document.user_ssm_policy.json
}

resource "aws_iam_group" "webcms_administrators" {
  name = "WebCMSAdministrators"
}

resource "aws_iam_user" "webcms_admin" {
  name = "WebCMSAdmin"

  tags = {
    Group = "webcms"
  }
}

resource "aws_iam_group_membership" "webcms_administrators_admin" {
  name  = "WebCMSAdminGroupMembership"
  group = aws_iam_group.webcms_administrators.name

  users = [
    aws_iam_user.webcms_admin.name
  ]
}

resource "aws_iam_group_policy_attachment" "webcms_administrators" {
  group      = aws_iam_group.webcms_administrators.name
  policy_arn = aws_iam_policy.user_ssm_policy.arn
}
