# Public DNS

# Public-facing DNS in order to support ACM certificates and assigning a public domain
# to the load balancer
resource "aws_route53_zone" "public" {
  name    = var.dns-root-domain
  comment = "Public-facing DNS for the WebCMS. Managed by Terraform."

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Public DNS"
  }
}

resource "aws_route53_record" "frontend_domain" {
  zone_id = aws_route53_zone.public.id

  name = var.dns-subdomain == null ? var.dns-root-domain : "${var.dns-subdomain}.${var.dns-root-domain}"
  type = "A"

  alias {
    name                   = aws_lb.frontend.dns_name
    zone_id                = aws_lb.frontend.zone_id
    evaluate_target_health = true
  }
}

# Validate the ALB's certificate
resource "aws_route53_record" "frontend_validation" {
  zone_id = aws_route53_zone.public.id
  name    = aws_acm_certificate.frontend.domain_validation_options[0].resource_record_name
  type    = aws_acm_certificate.frontend.domain_validation_options[0].resource_record_type
  ttl     = 60

  records = [aws_acm_certificate.frontend.domain_validation_options[0].resource_record_value]
}

# Private DNS

# Create a private DNS zone in order to create convenient shorthands for external
# services (such as the RDS database)
resource "aws_route53_zone" "private" {
  name    = "epa.local"
  comment = "Private DNS for the WebCMS. Managed by Terraform."

  vpc {
    vpc_id = aws_vpc.main.id
  }

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Private DNS"
  }
}

# Alias the RDS instance as the hostname "mysql" in order to avoid having to update the
# address should the instance ever be changed
resource "aws_route53_record" "private_rds" {
  zone_id = aws_route53_zone.private.id
  name    = "mysql"
  type    = "CNAME"
  ttl     = 60

  records = [aws_db_instance.db.address]
}

# Alias the ElastiCache cluster as "memcache"
resource "aws_route53_record" "private_cache" {
  zone_id = aws_route53_zone.public.id
  name    = "memcache"
  type    = "CNAME"
  ttl     = 60

  records = [aws_elasticache_cluster.cache.cluster_address]
}

# Alias each memcache node as "memcache-$N"
resource "aws_route53_record" "private_cache_nodes" {
  count = length(aws_elasticache_cluster.cache.cache_nodes)

  zone_id = aws_route53_zone.public.id
  name    = "memcache-${count.index}"
  type    = "CNAME"
  ttl     = 60

  records = [aws_elasticache_cluster.cache.cache_nodes[count.index].address]
}
