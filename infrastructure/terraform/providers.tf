provider "aws" {
  version = "~> 3.26"
  region  = var.aws-region
}

# See cluster.tf for why we need randomness
provider "random" {
  version = "~> 2.2"
}

# See utility.tf for usage of the template provider
provider "template" {
  version = "~> 2.1"
}

terraform {
  # Backend configuration is left unspecified here: there should be an external backend
  # configuration file used on each `terraform init`
  # cf. https://www.terraform.io/docs/backends/config.html#partial-configuration
  backend "s3" {}
}
