# Create a Terraform state bucket for other Terraform runs
resource "aws_s3_bucket" "tfstate" {
  bucket_prefix = "webcms-${var.environment}-tfstate-"

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

resource "aws_s3_bucket_public_access_block" "tfstate" {
  bucket = aws_s3_bucket.tfstate.bucket

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Create a DynamoDB table for Terraform locks
resource "aws_dynamodb_table" "terraform_locks" {
  name         = "WebCMS-${var.environment}-TerraformLocks"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "LockID"

  attribute {
    name = "LockID"
    type = "S"
  }
}

data "aws_iam_policy_document" "terraform_locks_access" {
  version = "2012-10-17"

  statement {
    sid       = "itemAccess"
    effect    = "Allow"
    actions   = ["dynamodb:GetItem", "dynamodb:PutItem", "dynamodb:DeleteItem"]
    resources = [aws_dynamodb_table.terraform_locks.arn]
  }
}

resource "aws_iam_policy" "terraform_locks_access" {
  name        = "WebCMS-${var.environment}-TerraformLocksAccess"
  description = "Grants read-write access to the TerraformLocks table"

  policy = data.aws_iam_policy_document.terraform_locks_access.json
}
