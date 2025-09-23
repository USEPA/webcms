#region Uploads

resource "aws_s3_bucket" "uploads" {
  for_each = local.sites

  bucket_prefix = "webcms-${each.key}-uploads-"

  versioning {
    enabled = true
  }

  lifecycle_rule {
    enabled = true

    noncurrent_version_expiration {
      days = 90
    }
  }

  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm = "AES256"
      }
    }
  }

  # Optionally replicate to another bucket. The dynamic block here makes
  # replication optional: if a key is missing for a given site-language pair,
  # then replication will not be enabled.
  dynamic "replication_configuration" {
    for_each = toset(try([var.s3_replication_destination[each.key]], []))

    content {
      role = var.s3_replication_role

      rules {
        status = "Enabled"

        delete_marker_replication_status = "Enabled"

        filter {}

        destination {
          bucket = replication_configuration.value
        }
      }
    }
  }

  # tags = var.tags
}

# This policy allows anonymous reads to the /files/ prefix of the uploads bucket, which
# we need in order to satisfy s3fs - it only uses one bucket for both public and private
# files.
data "aws_iam_policy_document" "uploads_policy" {
  for_each = local.sites

  version = "2012-10-17"

  statement {
    sid       = "AddPerm"
    effect    = "Allow"
    actions   = ["s3:GetObject"]
    resources = ["${aws_s3_bucket.uploads[each.key].arn}/files/*", "${aws_s3_bucket.uploads[each.key].arn}/archive/*"]

    principals {
      type        = "*"
      identifiers = ["*"]
    }
  }
}

resource "aws_s3_bucket_policy" "uploads_policy" {
  for_each = local.sites

  bucket = aws_s3_bucket.uploads[each.key].bucket

  policy = data.aws_iam_policy_document.uploads_policy[each.key].json
}

#endregion

#region Load balancer logs

resource "aws_s3_bucket" "elb_logs" {
  count = var.lb_logging_bucket == null ? 1 : 0

  bucket_prefix = "webcms-${var.environment}-elb-logs-"

  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm = "AES256"
      }
    }
  }

  # tags = var.tags
}

# Don't allow any public access to the ELB logging bucket
resource "aws_s3_bucket_public_access_block" "elb_logs" {
  count = var.lb_logging_bucket == null ? 1 : 0

  bucket = aws_s3_bucket.elb_logs[0].bucket

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

data "aws_iam_policy_document" "elb_logs_access" {
  count = var.lb_logging_bucket == null ? 1 : 0

  version = "2012-10-17"

  statement {
    sid       = "rootDelivery"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${aws_s3_bucket.elb_logs[0].arn}/AWSLogs/*"]

    principals {
      type        = "AWS"
      identifiers = [data.aws_elb_service_account.main.arn]
    }
  }

  statement {
    sid       = "elbDelivery"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${aws_s3_bucket.elb_logs[0].arn}/AWSLogs/*"]

    principals {
      type        = "Service"
      identifiers = ["delivery.logs.amazonaws.com"]
    }

    condition {
      test     = "StringEquals"
      variable = "s3:x-amz-acl"
      values   = ["bucket-owner-full-control"]
    }
  }

  statement {
    sid       = "aclAccess"
    effect    = "Allow"
    actions   = ["s3:GetBucketAcl"]
    resources = [aws_s3_bucket.elb_logs[0].arn]

    principals {
      type        = "Service"
      identifiers = ["delivery.logs.amazonaws.com"]
    }
  }
}

resource "aws_s3_bucket_policy" "elb_logs_delivery" {
  count = var.lb_logging_bucket == null ? 1 : 0

  bucket = aws_s3_bucket.elb_logs[0].bucket
  policy = data.aws_iam_policy_document.elb_logs_access[0].json
}

#endregion
