output "ecr-drupal" {
  value = aws_ecr_repository.drupal.repository_url
}

output "ecr-nginx" {
  value = aws_ecr_repository.nginx.repository_url
}

output "s3-bucket" {
  value = aws_s3_bucket.uploads.bucket_regional_domain_name
}

output "dns-nameservers" {
  value = aws_route53_zone.public.name_servers
}
