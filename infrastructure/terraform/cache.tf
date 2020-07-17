resource "aws_elasticache_subnet_group" "default" {
  name       = "webcms-default-${local.env-suffix}"
  subnet_ids = aws_subnet.private.*.id
}

resource "aws_elasticache_cluster" "cache" {
  cluster_id           = "webcms-cache-${local.env-suffix}"
  engine               = "memcached"
  node_type            = var.cache-instance-type
  num_cache_nodes      = var.cache-replica-count
  parameter_group_name = "default.memcached1.5"

  subnet_group_name = aws_elasticache_subnet_group.default.name

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Cache"
  })
}
