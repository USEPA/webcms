# Launch DBs in private subnets
resource "aws_db_subnet_group" "default" {
  name       = "webcms_default"
  subnet_ids = aws_subnet.private.*.id

  tags = {
    Group = "webcms"
  }
}

resource "aws_rds_cluster" "db" {
  cluster_identifier = "webcms-db"

  # Aurora Serverless doesn't support engine versions (for MySQL, it's fixed at 5.6)
  engine      = "aurora"
  engine_mode = "serverless"

  database_name   = local.database-name
  master_username = var.db-username
  master_password = var.db-password

  backup_retention_period      = 30
  preferred_backup_window      = "04:00-06:00"
  preferred_maintenance_window = "sun:06:00-sun:08:00"

  db_subnet_group_name   = aws_db_subnet_group.default.name
  vpc_security_group_ids = [aws_security_group.database.id]
  # We don't set the availability zones manually here - Aurora auto-assigns 3 AZs which
  # should be sufficient.

  scaling_configuration {
    auto_pause               = var.db-auto-pause
    min_capacity             = var.db-min-capacity
    max_capacity             = var.db-max-capacity
    seconds_until_auto_pause = 21600 # 6 * 3600 = 6 hours
  }

  tags = {
    Group = "webcms"
    Name  = "WebCMS DB"
  }

  # Ignore changes to the master password since it's stored in the Terraform state.
  # Instead, the value in Parameter Store should be treated as the sole source of truth.
  lifecycle {
    ignore_changes = [master_password]
  }
}
