# Task IAM role. This covers the IAM permissions for Drupal itself, not the underlying
# Fargate execution (which needs access to Secrets Manager).

resource "aws_iam_role" "drupal_task" {
  for_each = local.sites

  name        = "${var.iam_prefix}-${var.environment}-${each.key}-DrupalTask"
  description = "WebCMS task-level role"

  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json

  tags = var.tags
}

data "aws_iam_policy_document" "drupal_s3_access" {
  for_each = local.sites

  version = "2012-10-17"

  statement {
    sid       = "bucketReadAccess"
    effect    = "Allow"
    actions   = ["s3:HeadBucket", "s3:ListBucket", "s3:ListBucketVersions"]
    resources = [aws_s3_bucket.uploads[each.key].arn]
  }

  statement {
    sid    = "objectReadWriteAccess"
    effect = "Allow"

    actions = [
      "s3:DeleteObject",
      "s3:GetObject",
      "s3:GetObjectAcl",
      "s3:PutObject",
      "s3:PutObjectAcl"
    ]

    resources = ["${aws_s3_bucket.uploads[each.key].arn}/*"]
  }
}

resource "aws_iam_policy" "drupal_s3_access" {
  for_each = local.sites

  name        = "${var.iam_prefix}-${var.environment}-${each.key}-DrupalS3Access"
  description = "Grants read/write access to the ${each.key} S3 bucket"

  policy = data.aws_iam_policy_document.drupal_s3_access[each.key].json
}

resource "aws_iam_role_policy_attachment" "drupal_s3_access" {
  for_each = local.sites

  role       = aws_iam_role.drupal_task[each.key].name
  policy_arn = aws_iam_policy.drupal_s3_access[each.key].arn
}

data "aws_iam_policy_document" "drupal_es_access" {
  version = "2012-10-17"

  statement {
    sid    = "allowAccess"
    effect = "Allow"

    actions = [
      "es:ESHttpDelete",
      "es:ESHttpGet",
      "es:ESHttpHead",
      "es:ESHttpPost",
      "es:ESHttpPut",
      "es:ESHttpPatch",
    ]

    resources = ["${aws_elasticsearch_domain.es.arn}/*"]
  }
}

resource "aws_iam_policy" "drupal_es_access" {
  name        = "WebCMS-${var.environment}-ElasticsearchAccess"
  description = "Grants read/write access to Elasticsearch"

  policy = data.aws_iam_policy_document.drupal_es_access.json
}

resource "aws_iam_role_policy_attachment" "drupal_es_access" {
  for_each = local.sites

  role       = aws_iam_role.drupal_task[each.key].name
  policy_arn = aws_iam_policy.drupal_es_access.arn
}

data "aws_iam_policy_document" "drupal_publish_metrics" {
  version = "2012-10-17"

  statement {
    sid       = "putMetrics"
    effect    = "Allow"
    actions   = ["cloudwatch:PutMetricData"]
    resources = ["*"]
  }
}

resource "aws_iam_policy" "drupal_publish_metrics" {
  name        = "${var.iam_prefix}-${var.environment}-PublishMetrics"
  description = "Permits publishing CloudWatch metrics"

  policy = data.aws_iam_policy_document.drupal_publish_metrics.json
}

resource "aws_iam_role_policy_attachment" "drupal_publish_metrics" {
  for_each = local.sites

  role       = aws_iam_role.drupal_task[each.key].name
  policy_arn = aws_iam_policy.drupal_publish_metrics.arn
}

# Grant the Drupal container permissions to Cloudwatch to create a log stream
# and publish log events.
data "aws_iam_policy_document" "drupal_put_logs" {
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

resource "aws_iam_policy" "drupal_put_logs" {
  name        = "${var.iam_prefix}-${var.environment}-LogsPublish"
  description = "Permits publishing CloudWatch log events"

  policy = data.aws_iam_policy_document.drupal_put_logs.json
}

resource "aws_iam_role_policy_attachment" "drupal_put_logs" {
  for_each = local.sites

  role       = aws_iam_role.drupal_task[each.key].name
  policy_arn = aws_iam_policy.drupal_put_logs.arn
}
