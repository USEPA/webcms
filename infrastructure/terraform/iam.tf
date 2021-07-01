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
  name        = "${local.role-prefix}AppClusterRole"
  description = "Role for the WebCMS's cluster"

  assume_role_policy = data.aws_iam_policy_document.ecs_assume_role_policy.json

  tags = local.common-tags
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
  name        = "${local.role-prefix}AccessPolicyForSSMAndS3"
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
  name        = "${local.role-prefix}SessionPolicyForSSM"
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
  name        = "${local.role-prefix}AppInstanceRole"
  description = "Role for the WebCMS's EC2 instances"

  assume_role_policy = data.aws_iam_policy_document.ec2_assume_role_policy.json

  tags = local.common-tags
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
  name   = "${local.role-prefix}AppAutoscalingPolicy"
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
  name = "${local.role-prefix}AppInstanceProfile"
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
  name        = "${local.role-prefix}DrupalContainerRole"
  description = "Role for the WebCMS's Drupal app containers"

  assume_role_policy = data.aws_iam_policy_document.drupal_assume_role_policy.json

  tags = local.common-tags
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
  name        = "${local.role-prefix}UploadsAccess"
  description = "Grants read/write access to the WebCMS' uploads bucket"

  policy = data.aws_iam_policy_document.uploads_access.json
}

resource "aws_iam_role_policy_attachment" "drupal_uploads_access" {
  role       = aws_iam_role.drupal_container_role.name
  policy_arn = aws_iam_policy.uploads_access.arn
}

data "aws_iam_policy_document" "put_metrics" {
  version = "2012-10-17"

  statement {
    sid       = "allowPublishingMetrics"
    effect    = "Allow"
    actions   = ["cloudwatch:PutMetricData"]
    resources = ["*"]
  }
}

resource "aws_iam_policy" "put_metrics" {
  name        = "${local.role-prefix}MetricsPublish"
  description = "Permits publishing CloudWatch metrics"

  policy = data.aws_iam_policy_document.put_metrics.json
}

resource "aws_iam_role_policy_attachment" "drupal_put_metrics" {
  role       = aws_iam_role.drupal_container_role.name
  policy_arn = aws_iam_policy.put_metrics.arn
}

# Grant the Drupal container permissions to Cloudwatch to create a log stream
# and publish log events.
data "aws_iam_policy_document" "put_logs" {
  version = "2012-10-17"

  statement {
    sid    = "allowPublishingLogEvents"
    effect = "Allow"

    actions = [
      "logs:CreateLogStream",
      "logs:PutLogEvents"
    ]

    resources = ["*"]
  }
}

resource "aws_iam_policy" "put_logs" {
  name        = "${local.role-prefix}LogsPublish"
  description = "Permits publishing CloudWatch log events"

  policy = data.aws_iam_policy_document.put_logs.json
}

resource "aws_iam_role_policy_attachment" "drupal_put_logs" {
  role       = aws_iam_role.drupal_container_role.name
  policy_arn = aws_iam_policy.put_logs.arn
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
  name        = "${local.role-prefix}ElasticsearchAccess"
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
      aws_secretsmanager_secret.db_app_credentials.arn,
      aws_secretsmanager_secret.db_app_d7_credentials.arn,
      aws_secretsmanager_secret.hash_salt.arn,
      aws_secretsmanager_secret.mail_pass.arn,
      aws_secretsmanager_secret.saml_sp_key.arn,
      aws_secretsmanager_secret.akamai_access_token.arn,
      aws_secretsmanager_secret.akamai_client_token.arn,
      aws_secretsmanager_secret.akamai_client_secret.arn,
    ]
  }
}

resource "aws_iam_policy" "task_secrets_access" {
  name        = "${local.role-prefix}TaskSecretsAccess"
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
  name        = "${local.role-prefix}DrupalTaskExecutionRole"
  description = "Task execution role for Drupal containers"

  assume_role_policy = data.aws_iam_policy_document.drupal_execution_assume_role.json

  tags = local.common-tags
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

data "aws_iam_policy_document" "utility_assume" {
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

resource "aws_iam_role" "utility_role" {
  name        = "${local.role-prefix}UtilityRole"
  description = "IAM role for the utility EC2 instance"

  assume_role_policy = data.aws_iam_policy_document.utility_assume.json

  tags = local.common-tags
}

resource "aws_iam_role_policy_attachment" "utility_ssm" {
  role       = aws_iam_role.utility_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy_attachment" "utility_ssm_s3" {
  role       = aws_iam_role.utility_role.name
  policy_arn = aws_iam_policy.ssm_s3_policy.arn
}

resource "aws_iam_role_policy_attachment" "utility_ssm_session" {
  role       = aws_iam_role.utility_role.name
  policy_arn = aws_iam_policy.ssm_session_policy.arn
}

resource "aws_iam_role_policy_attachment" "utility_extra_polices" {
  for_each = toset(var.server-extra-policies)

  role       = aws_iam_role.utility_role.name
  policy_arn = each.value
}

resource "aws_iam_instance_profile" "utility_profile" {
  name = "${local.role-prefix}UtilityInstanceProfile"
  role = aws_iam_role.utility_role.name
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
  name               = "${local.role-prefix}SystemsManagerRole"
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
  name        = "${local.role-prefix}PolicyForSystemsManager"
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

    # Limit this policy only to WebCMS EC2 instances on a per-environment basis
    condition {
      test     = "StringLike"
      variable = "ssm:resourceTag/Group"
      values   = ["webcms"]
    }

    condition {
      test     = "StringLike"
      variable = "ssm:resourceTag/Environment"
      values   = [local.env-suffix]
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
  name        = "${local.role-prefix}UserAccessPolicyForSSM"
  description = "Grants Session Manager access for users"
  policy      = data.aws_iam_policy_document.user_ssm_policy.json
}

# User-level policy for running Drush tasks

data "aws_iam_policy_document" "user_run_tasks_policy" {
  # Only create this policy if Drush is being deployed
  count = var.image-tag-drush != null ? 1 : 0

  version = "2012-10-17"

  # Allow minimal read access for ECS tasks
  statement {
    sid = "listEcsTasks"

    effect    = "Allow"
    actions   = ["ecs:ListTasks", "ecs:DescribeTasks", "ecs:ListTaskDefinitions", "ecs:DescribeTaskDefinition"]
    resources = ["*"]
  }

  # Allow access to the RunTask ECS API, but only for Drush.
  # TODO: Resolve why the conditional permissions aren't allowing RunTask API calls
  statement {
    sid = "runTask"

    effect  = "Allow"
    actions = ["ecs:RunTask"]

    # Manually construct the ARN of the Drush task: we want to allow abitrary versions due
    # to the fact that deployments may introduce some churn in the IAM permissions, and
    # eventual consistency may inadvertently block access to any newly-created Drush tasks
    # due to the revision number not yet being fully "settled" in IAM.
    resources = [
      # AWS ARN syntax for ECS tasks: each of the pieces is separated by a colon
      # 1. The string "arn"
      # 2. The string "aws"
      # 3. The AWS service (here, ECS)
      # 4. AWS region
      # 5. AWS account ID
      # 6. ECS task family ("webcms-drush-${local.env-suffix}", but we read it from the task definition directly)
      # 7. The task revision number - we use "*" due to the reasons stated above.
      # "arn:aws:ecs:${data.aws_region.current.name}:${data.aws_caller_identity.current.account_id}:${aws_ecs_task_definition.drush_task[0].family}:*"
      "*"
    ]

    # Limit RunTask to the WebCMS's cluster.
    # condition {
    #   test     = "StringEquals"
    #   variable = "ecs:cluster"
    #   values   = [aws_ecs_cluster.cluster.arn]
    # }
  }

  # Allow users to stop tasks - useful to abort long-running Drush scripts or force a
  # restart of service tasks.
  # TODO: Limit this only to the WebCMS' specific cluster
  statement {
    sid = "stopTask"

    effect    = "Allow"
    actions   = ["ecs:StopTask"]
    resources = ["*"]
  }

  # Allow users to pass this deployment's Drush role to the RunTask API.
  statement {
    sid = "passDrupalRole"

    effect    = "Allow"
    actions   = ["iam:PassRole"]
    resources = [aws_iam_role.drupal_execution_role.arn]
  }
}

resource "aws_iam_policy" "user_run_tasks_policy" {
  count = length(data.aws_iam_policy_document.user_run_tasks_policy)

  name        = "${local.role-prefix}UserRunTasksPolicy"
  description = "Grants permission to run Drush tasks against the cluster"
  policy      = data.aws_iam_policy_document.user_run_tasks_policy[0].json
}

# Grants read access to the both the uploads and backups buckets, as well as read/write
# access to the objects in them.
data "aws_iam_policy_document" "user_s3_access_policy" {
  version = "2012-10-17"

  # Permissions needed for the S3 console
  # cf. https://aws.amazon.com/blogs/security/writing-iam-policies-how-to-grant-access-to-an-amazon-s3-bucket/
  statement {
    sid = "consoleAccess"

    effect    = "Allow"
    actions   = ["s3:GetBucketLocation", "s3:ListAllMyBuckets"]
    resources = ["*"]
  }

  statement {
    sid = "bucketRead"

    effect    = "Allow"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.uploads.arn, aws_s3_bucket.backups.arn]
  }

  statement {
    sid = "objectReadWrite"

    effect    = "Allow"
    actions   = ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.uploads.arn}/*", "${aws_s3_bucket.backups.arn}/*"]
  }
}

resource "aws_iam_policy" "user_s3_access_policy" {
  name        = "${local.role-prefix}UserS3AccessPolicy"
  description = "Grants access to the uploads and backups buckets"
  policy      = data.aws_iam_policy_document.user_s3_access_policy.json
}

# Grants WebCMS administrators access to the Systems Manager automation documents to
# manage the database (see automation.tf).
data "aws_iam_policy_document" "user_automation_policy" {
  version = "2012-10-17"

  # To determine: can these automation execution-related permissions be further limited in scope?
  statement {
    sid    = "viewExecutions"
    effect = "Allow"

    actions = [
      "ssm:DescribeAutomationExecutions",
      "ssm:GetAutomationExecution",
      "ssm:DescribeAutomationStepExecutions",
      "ssm:ListCommands",
      "ssm:StopAutomationExecution",
    ]

    resources = [
      "arn:aws:ssm:${var.aws-region}:${data.aws_caller_identity.current.account_id}:*"
    ]
  }

  statement {
    sid       = "listDocuments"
    effect    = "Allow"
    actions   = ["ssm:ListDocuments"]
    resources = ["*"]
  }

  statement {
    sid    = "viewDocuments"
    effect = "Allow"

    actions = [
      "ssm:DescribeDocument",
      "ssm:DescribeDocumentParameters",
      "ssm:GetDocument",
      "ssm:DescribeDocumentPermission",
    ]

    resources = [
      "arn:aws:ssm:${var.aws-region}:${data.aws_caller_identity.current.account_id}:document/${aws_ssm_document.d7_load_database.name}",
      "arn:aws:ssm:${var.aws-region}:${data.aws_caller_identity.current.account_id}:document/${aws_ssm_document.d8_dump_database.name}",
    ]
  }

  statement {
    sid     = "executeDocuments"
    effect  = "Allow"
    actions = ["ssm:StartAutomationExecution"]

    resources = [
      "arn:aws:ssm:${var.aws-region}:${data.aws_caller_identity.current.account_id}:automation-definition/${aws_ssm_document.d7_load_database.name}:*",
      "arn:aws:ssm:${var.aws-region}:${data.aws_caller_identity.current.account_id}:automation-definition/${aws_ssm_document.d8_dump_database.name}:*",
    ]
  }
}

resource "aws_iam_policy" "user_automation_policy" {
  name        = "${local.role-prefix}UserAutomationExecutionPolicy"
  description = "Grants permission to view and run automation documents that load and restore MySQL database dumps."
  policy      = data.aws_iam_policy_document.user_automation_policy.json
}

resource "aws_iam_group" "webcms_administrators" {
  name = "${local.role-prefix}Administrators"
}

resource "aws_iam_user" "webcms_admin" {
  name = "${local.role-prefix}Admin"

  tags = local.common-tags
}

resource "aws_iam_group_membership" "webcms_administrators_admin" {
  name  = "${local.role-prefix}AdminGroupMembership"
  group = aws_iam_group.webcms_administrators.name

  users = concat([aws_iam_user.webcms_admin.name], var.users-extra-admin)
}

resource "aws_iam_group_policy_attachment" "webcms_administrators" {
  group      = aws_iam_group.webcms_administrators.name
  policy_arn = aws_iam_policy.user_ssm_policy.arn
}

# Grant admin users read-only access to the app's secrets so they can make connections to,
# e.g., the Aurora cluster.
resource "aws_iam_group_policy_attachment" "webcms_administrators_secrets_access" {
  group      = aws_iam_group.webcms_administrators.name
  policy_arn = aws_iam_policy.task_secrets_access.arn
}

resource "aws_iam_group_policy_attachment" "webcm_administrators_s3_access" {
  group      = aws_iam_group.webcms_administrators.name
  policy_arn = aws_iam_policy.user_s3_access_policy.arn
}

resource "aws_iam_group_policy_attachment" "webcms_administrators_automation_access" {
  group      = aws_iam_group.webcms_administrators.name
  policy_arn = aws_iam_policy.user_automation_policy.arn
}

resource "aws_iam_group_policy_attachment" "webcm_administrators_run_tasks" {
  count = length(data.aws_iam_policy_document.user_run_tasks_policy)

  group      = aws_iam_group.webcms_administrators.name
  policy_arn = aws_iam_policy.user_run_tasks_policy[0].arn
}
