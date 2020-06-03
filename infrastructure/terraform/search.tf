resource "aws_elasticsearch_domain" "es" {
  domain_name = "webcms-domain-${local.env-suffix}"

  elasticsearch_version = "7.4"

  cluster_config {
    dedicated_master_enabled = var.search-dedicated-node-count != 0
    dedicated_master_count   = var.search-dedicated-node-count
    dedicated_master_type    = var.search-dedicated-node-type

    instance_type  = var.search-instance-type
    instance_count = var.search-instance-count

    # Only enable zone-awareness if we have multiple instances
    zone_awareness_enabled = var.search-instance-count > 1
    zone_awareness_config {
      availability_zone_count = var.search-availability-zones
    }
  }

  ebs_options {
    ebs_enabled = true
    volume_type = "gp2"
    volume_size = var.search-instance-storage
  }

  vpc_options {
    security_group_ids = [aws_security_group.search.id]

    # We have to shorten the list of subnets here if we have a single instance
    subnet_ids = slice(
      aws_subnet.private.*.id,
      0,
      min(length(aws_subnet.private), var.search-instance-count)
    )
  }

  domain_endpoint_options {
    enforce_https       = false
    tls_security_policy = "Policy-Min-TLS-1-2-2019-07"
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Elasticsearch"
  })
}
