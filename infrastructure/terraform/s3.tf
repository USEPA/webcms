resource "aws_s3_bucket" "uploads" {
  bucket = "webcms-uploads"

  tags = {
    Application = "WebCMS"
  }
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
