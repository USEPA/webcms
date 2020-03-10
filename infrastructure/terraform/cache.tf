resource "aws_elasticache_subnet_group" "default" {
  name       = "webcms_default"
  subnet_ids = aws_subnet.private.*.id
}

resource "aws_elasticache_cluster" "cache" {
  cluster_id = "webcms-cache"

  engine               = "memcached"
  engine_version       = "1.5.16"
  parameter_group_name = "default.memcached1.5"

  node_type                    = var.cache-instance-type
  num_cache_nodes              = var.cache-instance-count
  az_mode                      = var.cache-instance-count > 1 ? "cross-az" : "single-az"

  security_group_ids = [aws_security_group.cache.id]
  subnet_group_name  = aws_elasticache_subnet_group.default.name
  port               = 11211

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Cache"
  }
}
