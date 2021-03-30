resource "aws_iam_service_linked_role" "es" {
  aws_service_name = "es.amazonaws.com"
  description      = "Allows Amazon ES to manage AWS resources for a domain on your behalf."
}

resource "aws_elasticsearch_domain" "es" {
  domain_name = "webcms-${var.environment}"

  elasticsearch_version = "7.4"

  cluster_config {
    dedicated_master_enabled = var.search_dedicated_node_count != 0
    dedicated_master_count   = var.search_dedicated_node_count
    dedicated_master_type    = var.search_dedicated_node_type

    instance_type  = var.search_instance_type
    instance_count = var.search_instance_count

    # Only enable zone_awareness if we have multiple instances
    zone_awareness_enabled = var.search_instance_count > 1
    zone_awareness_config {
      availability_zone_count = var.search_availability_zones
    }
  }

  ebs_options {
    ebs_enabled = true
    volume_type = "gp2"
    volume_size = var.search_instance_storage
  }

  encrypt_at_rest {
    enabled = true
  }

  node_to_node_encryption {
    enabled = true
  }

  vpc_options {
    security_group_ids = [data.aws_ssm_parameter.elasticsearch_security_group.value]

    # We have to shorten the list of subnets here if we have a single instance
    subnet_ids = slice(
      local.private_subnets,
      0,
      min(length(local.private_subnets), var.search_instance_count)
    )
  }

  domain_endpoint_options {
    enforce_https       = true
    tls_security_policy = "Policy-Min-TLS-1-2-2019-07"
  }

  tags = var.tags
}
