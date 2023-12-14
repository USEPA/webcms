resource "aws_db_subnet_group" "default" {
  name       = "webcms-${var.environment}"
  subnet_ids = local.private_subnets

  tags = var.tags
}

resource "random_password" "rds_root_password" {
  length = 20
}
