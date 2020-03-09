# Launch DBs in private subnets
resource "aws_db_subnet_group" "default" {
  name       = "webcms_default"
  subnet_ids = aws_subnet.private.*.id

  tags = {
    Application = "WebCMS"
  }
}

resource "aws_db_instance" "db" {
  identifier = "webcms-db"

  # Engine & version params
  engine         = "mysql"
  engine_version = "5.7"

  # Request a multi-AZ instance
  multi_az = true

  # Instance/storage params
  instance_class          = var.db-instance-type
  storage_type            = "gp2"
  allocated_storage       = var.db-storage-size
  storage_encrypted       = true
  backup_retention_period = 30
  skip_final_snapshot     = true

  # Networking/VPC
  db_subnet_group_name   = aws_db_subnet_group.default.name
  vpc_security_group_ids = [aws_security_group.database.id]

  # DB params
  name     = "webcms"
  username = var.db-username
  password = var.db-password

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS DB"
  }
}
