resource "aws_elasticache_subnet_group" "default" {
  name       = "webcms-${var.environment}"
  subnet_ids = local.private_subnets
}

resource "aws_elasticache_cluster" "cache" {
  cluster_id           = "webcms-${var.environment}"
  engine               = "memcached"
  engine_version       = "1.6.6"
  node_type            = var.cache_instance_type
  num_cache_nodes      = var.cache_instance_count
  parameter_group_name = "default.memcached1.6"

  # Ignore maintenance windows in favor of immediately applying
  apply_immediately = true

  security_group_ids = [data.aws_ssm_parameter.memcached_security_group.value]
  subnet_group_name  = aws_elasticache_subnet_group.default.name

  tags = var.tags
}
