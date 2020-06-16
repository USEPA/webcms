resource "aws_elasticache_subnet_group" "default" {
  name       = "webcms-default-${local.env-suffix}"
  subnet_ids = aws_subnet.private.*.id
}

resource "aws_elasticache_replication_group" "cache" {
  replication_group_id          = "webcms-redis-${local.env-suffix}"
  replication_group_description = "Replication group for the WebCMS cache"
  automatic_failover_enabled    = true

  node_type = var.cache-instance-type

  port                       = 6379
  engine                     = "redis"
  engine_version             = "5.0.6"
  auto_minor_version_upgrade = true
  parameter_group_name       = "default.redis5.0.cluster.on"
  transit_encryption_enabled = true

  subnet_group_name  = aws_elasticache_subnet_group.default.name
  security_group_ids = [aws_security_group.cache.id]

  at_rest_encryption_enabled = true
  kms_key_id                 = var.encryption-at-rest-key

  cluster_mode {
    # Since we're using Redis as a cache backend instead of a data store, we don't
    # worry too much about the number of cluster shards and instead use cluster mode
    # for the availability guarantees.
    num_node_groups         = 1
    replicas_per_node_group = var.cache-replica-count
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Cache"
  })
}
