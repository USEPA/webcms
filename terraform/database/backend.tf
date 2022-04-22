terraform {
  backend "s3" {
    key     = "database.tfstate"
    encrypt = true
  }
}
