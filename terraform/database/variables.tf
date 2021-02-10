variable "backend_storage" {
  description = "Name of the S3 bucket in which to store the Terraform state"
  type        = string
}

variable "backend_locks" {
  description = "Name of the DynamoDB table used to lock this Terraform run"
  type        = string
}

variable "aws_region" {
  description = "Name of the AWS region in which this container is running"
  type        = string
}
