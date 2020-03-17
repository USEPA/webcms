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

# Alias the Aurora cluster as the hostname "mysql" in order to avoid having to update the
# address should the endpoint ever change
resource "aws_route53_record" "private_rds" {
  zone_id = aws_route53_zone.private.id
  name    = "mysql"
  type    = "CNAME"
  ttl     = 60

  records = [aws_rds_cluster.db.endpoint]
}

# Alias the ElastiCache cluster as the hostname "redis" for the same reasons as above
resource "aws_route53_record" "private_cache" {
  zone_id = aws_route53_zone.private.id
  name    = "redis"
  type    = "CNAME"
  ttl     = 60

  records = [aws_elasticache_replication_group.cache.configuration_endpoint_address]
}
