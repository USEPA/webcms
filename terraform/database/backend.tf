terraform {
  backend "s3" {
    bucket  = var.backend_storage
    key     = "database.tfstate"
    encrypt = true

    dynamodb_table = var.backend_locks
  }
}
