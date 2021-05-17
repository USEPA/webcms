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
  family = "aurora-mysql5.7"

  # The innodb_large_prefix parameter expands the key size from 767 bytes to ~3000 bytes,
  # enabling the use of indexes on VARCHAR(255) columns when that column uses the utf8m4
  # encoding.
  parameter {
    name  = "innodb_large_prefix"
    value = 1
  }

  # Bump the max allowed packet to 128MB (default is 1-4MB, depending on server version).
  parameter {
    name  = "max_allowed_packet"
    value = 128 * (1024 * 1024)
  }

  # Slow query logging

  # Enable the slow query log
  parameter {
    name  = "slow_query_log"
    value = 1
  }

  # Log any queries that took longer than this many seconds
  parameter {
    name  = "long_query_time"
    value = 0.125
  }

  # Log any queries not using indexes - if this is too much, we can throttle the amount of
  # queries per minute that MySQL logs.
  parameter {
    name  = "log_queries_not_using_indexes"
    value = 1
  }

  # Enable the performance schema - the defaults that ship with the MySQL server are more
  # than likely enough for what we'll need.
  parameter {
    name  = "performance_schema"
    value = 1

    apply_method = "pending-reboot"
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB parameters"
  })
}

resource "aws_rds_cluster" "db" {
  cluster_identifier = "webcms-rds-${local.env-suffix}"

  engine         = "aurora-mysql"
  engine_mode    = "provisioned"
  engine_version = "5.7.mysql_aurora.2.09.0"

  database_name   = local.database-name
  master_username = var.db-username
  master_password = var.db-password

  backup_retention_period      = 30
  preferred_backup_window      = "04:00-06:00"
  preferred_maintenance_window = "sun:06:00-sun:08:00"

  skip_final_snapshot = true

  db_cluster_parameter_group_name = aws_rds_cluster_parameter_group.params.id

  # Export most of the DB logs - the only thing we're not exporting is audit logs, which
  # we assume are less of an issue given that the cluster is accessible only from within
  # the VPC.
  enabled_cloudwatch_logs_exports = [
    "error",
    "general",
    "slowquery"
  ]

  db_subnet_group_name   = aws_db_subnet_group.default.name
  vpc_security_group_ids = [aws_security_group.database.id]

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DB"
  })

  # Ignore changes to the master password since it's stored in the Terraform state.
  # Instead, the value in Parameter Store should be treated as the sole source of truth.
  lifecycle {
    ignore_changes = [master_password]
  }
}

resource "aws_rds_cluster_instance" "db_instance" {
  count = var.db-instance-count

  identifier     = "webcms-rds-instance-${count.index}-${local.env-suffix}"
  instance_class = var.db-instance-type

  engine         = aws_rds_cluster.db.engine
  engine_version = aws_rds_cluster.db.engine_version

  cluster_identifier   = aws_rds_cluster.db.cluster_identifier
  db_subnet_group_name = aws_rds_cluster.db.db_subnet_group_name
}
