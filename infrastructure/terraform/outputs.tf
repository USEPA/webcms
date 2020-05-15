output "ecr-drupal" {
  value = aws_ecr_repository.drupal.repository_url
}

output "ecr-nginx" {
  value = aws_ecr_repository.nginx.repository_url
}

output "s3-bucket" {
  value = aws_s3_bucket.uploads.bucket_regional_domain_name
}

# The ALB zone ID can be used in Route 53 alias records, but...
output "alb-zone-id" {
  value = aws_lb.frontend.zone_id
}

# ... if Route 53 isn't being used, then use the dns_name to create a CNAME record.
output "alb-domain" {
  value = aws_lb.frontend.dns_name
}

# Network configuration for Drush tasks, when using the ECS RunTask API
output "drush-task-config" {
  value = jsonencode({
    awsvpcConfiguration = {
      subnets        = aws_subnet.private.*.id
      securityGroups = local.drupal-security-groups
      assignPublicIp = "DISABLED"
    }
  })
}
