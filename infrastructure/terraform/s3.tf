resource "aws_s3_bucket" "uploads" {
  bucket = var.s3-bucket-name

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Uploads"
  })
}

resource "aws_s3_bucket_policy" "uploads_policy" {
  bucket = aws_s3_bucket.uploads.bucket

  # This policy allows anonymous reads to the /public/ prefix of the uploads bucket, which
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
        Resource  = ["arn:aws:s3:::${aws_s3_bucket.uploads.bucket}/public/*"]
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
