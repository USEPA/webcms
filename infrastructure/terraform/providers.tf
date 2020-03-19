provider "aws" {
  version = "~> 2.49"
  region  = var.aws-region
}

# See cluster.tf for why we need randomness
provider "random" {
  version = "~> 2.2"
}

# See bastion.tf for usage of the template provider
provider "template" {
  version = "~> 2.1"
}
