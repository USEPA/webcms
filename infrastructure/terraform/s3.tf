resource "aws_s3_bucket" "uploads" {
  bucket = var.s3-bucket-name

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Uploads"
  })
}

resource "aws_s3_bucket_policy" "uploads_policy" {
  bucket = aws_s3_bucket.uploads.bucket

  # This policy allows anonymous reads to the /files/ prefix of the uploads bucket, which
  # we need in order to satisfy s3fs - it only uses one bucket for both public and private
  # files.
  policy = jsonencode({
    Version = "2012-10-17",
    Statement = [
      {
        Sid       = "AddPerm",
        Effect    = "Allow"
        Principal = "*"
        Action    = ["s3:GetObject"]
        Resource  = ["arn:aws:s3:::${aws_s3_bucket.uploads.bucket}/files/*"]
      }
    ]
  })
}

# Create an S3 bucket using a random identifier (this is only used for administration so
# the name doesn't have to be pretty)
resource "aws_s3_bucket" "elb_logs" {
  bucket_prefix = "webcms-logs-${local.env-suffix}-"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} ELB Logs"
  })
}

# Don't allow any public access to the ELB logging bucket
resource "aws_s3_bucket_public_access_block" "elb_logs" {
  bucket = aws_s3_bucket.elb_logs.bucket

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Generate a bucket policy that grants access to the ELB logging bucket per
# https://docs.aws.amazon.com/elasticloadbalancing/latest/application/load-balancer-access-logs.html#access-logging-bucket-permissions
locals {
  elb-accounts = {
    "us-east-1"     = "127311923021"
    "us-east-2"     = "033677994240"
    "us-west-1"     = "027434742980"
    "us-west-2"     = "797873946194"
    "us-gov-west-1" = "048591011584"
    "us-gov-east-1" = "190560391635"
  }

  elb-log-path = "arn:aws:s3:::${aws_s3_bucket.elb_logs.bucket}/AWSLogs/${data.aws_caller_identity.current.account_id}"
}

data "aws_iam_policy_document" "elb_logs_access" {
  version = "2012-10-17"

  statement {
    sid       = "rootDelivery"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${local.elb-log-path}/*"]

    principals {
      type        = "AWS"
      identifiers = ["arn:aws:iam::${local.elb-accounts[var.aws-region]}:root"]
    }
  }

  statement {
    sid       = "elbDelivery"
    effect    = "Allow"
    actions   = ["s3:PutObject"]
    resources = ["${local.elb-log-path}/*"]

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
    resources = ["arn:aws:s3:::${aws_s3_bucket.elb_logs.bucket}"]

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

# Create a bucket to house DB backups
resource "aws_s3_bucket" "backups" {
  bucket_prefix = "webcms-db-backups-${local.env-suffix}-"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB Backups"
  })
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
