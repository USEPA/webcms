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

# Alias the ElastiCache discovery endpoint as "memcache.cfg"
# cf. https://docs.aws.amazon.com/AmazonElastiCache/latest/mem-ug/AutoDiscovery.html
resource "aws_route53_record" "private_cache" {
  zone_id = aws_route53_zone.private.id
  name    = "memcache.cfg"
  type    = "CNAME"
  ttl     = 60

  records = [aws_elasticache_cluster.cache.cluster_address]
}

# Alias each memcache node as "memcache-$N"
resource "aws_route53_record" "private_cache_nodes" {
  count = var.cache-instance-count

  zone_id = aws_route53_zone.private.id
  name    = "memcache-${count.index}"
  type    = "CNAME"
  ttl     = 60

  records = [aws_elasticache_cluster.cache.cache_nodes[count.index].address]
}
