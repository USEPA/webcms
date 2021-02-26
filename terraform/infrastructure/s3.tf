#region Uploads

resource "aws_s3_bucket" "uploads" {
  for_each = local.sites

  bucket_prefix = "webcms-${each.key}-uploads-"

  versioning {
    enabled = true
  }

  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm = "AES256"
      }
    }
  }

  tags = var.tags
}

# This policy allows anonymous reads to the /public/ prefix of the uploads bucket, which
# we need in order to satisfy s3fs - it only uses one bucket for both public and private
# files.
data "aws_iam_policy_document" "uploads_policy" {
  for_each = local.sites

  version = "2012-10-17"

  statement {
    sid       = "AddPerm"
    effect    = "Allow"
    actions   = ["s3:GetObject"]
    resources = ["${aws_s3_bucket.uploads[each.key].arn}/public/*"]

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
  bucket_prefix = "webcms-${var.environment}-elb-logs-"

  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm = "AES256"
      }
    }
  }

  tags = var.tags
}

# Don't allow any public access to the ELB logging bucket
resource "aws_s3_bucket_public_access_block" "elb_logs" {
  bucket = aws_s3_bucket.elb_logs.bucket

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

data "aws_iam_policy_document" "elb_logs_access" {
  version = "2012-10-17"

  statement {
    sid       = "rootDelivery"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${aws_s3_bucket.elb_logs.arn}/AWSLogs/*"]

    principals {
      type        = "AWS"
      identifiers = [data.aws_elb_service_account.main.arn]
    }
  }

  statement {
    sid       = "elbDelivery"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${aws_s3_bucket.elb_logs.arn}/AWSLogs/*"]

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
    resources = [aws_s3_bucket.elb_logs.arn]

    principals {
      type        = "Service"
      identifiers = ["delivery.logs.amazonaws.com"]
    }
  }
}

resource "aws_s3_bucket_policy" "elb_logs_delivery" {
  bucket = aws_s3_bucket.elb_logs.bucket
  policy = data.aws_iam_policy_document.elb_logs_access.json
}

#endregion

#region DB backups

# Create a bucket to house DB backups
resource "aws_s3_bucket" "backups" {
  bucket_prefix = "webcms-${var.environment}-backups-"

  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm = "AES256"
      }
    }
  }

  tags = var.tags
}

# As with the ELB logs bucket, this bucket is fully private. No public access should be
# permitted.
resource "aws_s3_bucket_public_access_block" "backups" {
  bucket = aws_s3_bucket.backups.bucket

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

#endregion
