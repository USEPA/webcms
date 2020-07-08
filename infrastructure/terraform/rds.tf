# Launch DBs in private subnets
resource "aws_db_subnet_group" "default" {
  name       = "webcms_default_${local.env-suffix}"
  subnet_ids = aws_subnet.private.*.id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB subnets"
  })
}

resource "aws_rds_cluster_parameter_group" "params" {
  name   = "webcms-params-${local.env-suffix}"
  family = "aurora5.6"

  # The innodb_large_prefix parameter expands the key size from 767 bytes to ~3000 bytes,
  # enabling the use of indexes on VARCHAR(255) columns when that column uses the utf8m4
  # encoding.
  parameter {
    name  = "innodb_large_prefix"
    value = 1
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB parameters"
  })
}

resource "aws_rds_cluster" "db" {
  cluster_identifier = "webcms-db-${local.env-suffix}"

  # Aurora Serverless doesn't support engine versions (for MySQL, it's fixed at 5.6)
  engine      = "aurora"
  engine_mode = "serverless"

  database_name   = local.database-name
  master_username = var.db-username
  master_password = var.db-password

  backup_retention_period      = 30
  preferred_backup_window      = "04:00-06:00"
  preferred_maintenance_window = "sun:06:00-sun:08:00"

  skip_final_snapshot = true

  db_cluster_parameter_group_name = aws_rds_cluster_parameter_group.params.id

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

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB"
  })

  # Ignore changes to the master password since it's stored in the Terraform state.
  # Instead, the value in Parameter Store should be treated as the sole source of truth.
  lifecycle {
    ignore_changes = [master_password]
  }
}
