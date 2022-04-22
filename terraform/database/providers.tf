terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 3"
    }

    mysql = {
      source  = "winebarrel/mysql"
      version = "1.9.0-p6"
    }

    random = {
      source  = "hashicorp/random"
      version = "~> 3"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

provider "mysql" {
  endpoint = var.mysql_endpoint
  username = var.mysql_credentials.username
  password = var.mysql_credentials.password
}
