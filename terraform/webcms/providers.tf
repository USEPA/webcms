terraform {
  required_version = ">= 0.14"
}

provider "aws" {
  region = var.aws_region
}
