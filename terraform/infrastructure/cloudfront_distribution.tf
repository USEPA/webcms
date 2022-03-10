resource "aws_cloudfront_distribution" "drupalcloud_cloudfront_distribution" {
    aliases = var.cf_aliases
    default_root_object = ""
    enabled = false
    is_ipv6_enabled = false

    restrictions {
      geo_restriction {
          restriction_type = "none"
      }
    }

    origin {
        domain_name = var.cf_origin_domain
        origin_id = var.cf_origin_id
        origin_path = ""
        custom_header {
            name = "Route"
            value = "cloudfront"
        }
        custom_origin_config {
            http_port = "80"
            https_port = "443"
            origin_protocol_policy = "https-only"
            origin_ssl_protocols = ["TLSv1.2"]
            origin_read_timeout = 30
            origin_keepalive_timeout = 5
        }
    }

    default_cache_behavior {
        allowed_methods = ["HEAD", "DELETE", "POST", "GET", "OPTIONS", "PUT", "PATCH"]
        cached_methods = ["HEAD", "GET"]
        target_origin_id = var.cf_origin_id
        cache_policy_id = "default_cache_optimized"
        origin_request_policy_id = "default_request_policy"
        viewer_protocol_policy = "allow-all"
        compress = true
        smooth_streaming = false
    }

    ordered_cache_behavior {
      path_pattern = "/themes/epa_theme/*"
      target_origin_id = var.cf_origin_id
      cache_policy_id = "long_ttl"
      viewer_protocol_policy = "redirect-to-https"
      allowed_methods = ["HEAD", "GET"]
      cached_methods = ["HEAD", "GET"]
      smooth_streaming = false
      compress = true
    }

    ordered_cache_behavior {
      path_pattern = "/core/*"
      target_origin_id = var.cf_origin_id
      cache_policy_id = "long_ttl"
      viewer_protocol_policy = "redirect-to-https"
      allowed_methods = ["HEAD", "GET"]
      cached_methods = ["HEAD", "GET"]
      smooth_streaming = false
      compress = true
    }

    ordered_cache_behavior {
      path_pattern = "/sites/default/files/*"
      target_origin_id = var.cf_origin_id
      cache_policy_id = "drupal_upload_files"
      viewer_protocol_policy = "redirect-to-https"
      allowed_methods = ["HEAD", "GET"]
      cached_methods = ["HEAD", "GET"]
      smooth_streaming = false
      compress = true
    }

    logging_config {
        include_cookies = false
        bucket = var.lb_logging_bucket
        prefix = "cloudfront"
    }
    price_class = "PriceClass_All"
    viewer_certificate {
      iam_certificate_id = var.cf_certificate
      ssl_support_method = "sni-only"
      minimum_protocol_version = "TLSv1.2_2019"
    }
}