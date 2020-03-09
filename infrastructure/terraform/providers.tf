provider "aws" {
  version = "~> 2.49"
  region  = var.aws-region
}

# See cluster.tf for why we need randomness
provider "random" {
  version = "~> 2.2"
}
