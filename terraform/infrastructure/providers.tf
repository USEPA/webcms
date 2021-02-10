terraform {
  required_version = ">= 0.14"

  required_providers {
    aws = {
      source = "hashicorp/aws"
      version = "3.27.0"
    }

    random = {
      source = "hashicorp/random"
      version = "3.0.1"
    }
  }
}

provider "aws" {
  region = var.aws_region
}
