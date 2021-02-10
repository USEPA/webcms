terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "3.26.0"
    }

    mysql = {
      source = "winebarrel/mysql"
      version = "1.9.0-p6"
    }

    random = {
      source  = "hashicorp/random"
      version = "3.0.1"
    }
  }
}

provider "aws" {
  region = var.aws_region
}
