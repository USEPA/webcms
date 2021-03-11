terraform {
  required_version = ">= 0.14"
  backend "s3" {}
}

provider "aws" {
  region = var.aws_region
}
