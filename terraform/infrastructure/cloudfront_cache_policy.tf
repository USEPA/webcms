resource "aws_cloudfront_cache_policy" "long_ttl" {
    name = "Long_TTL"
    comment = "Cache Policy for longer-lived objects"
    min_ttl = 604800
    max_ttl = 31536000
    default_ttl = 31536000
    parameters_in_cache_key_and_forwarded_to_origin {
      cookies_config {
          cookie_behavior = "none"
      }
      headers_config {
          header_behavior = "none"
      }
      query_strings_config {
        query_string_behavior = "all"
      }
    }
}

resource "aws_cloudfront_cache_policy" "drupal_upload_files" {
    name = "Drupal_Upload_Files"
    comment = "Cache Policy for sites/default/files"
    min_ttl = 600
    max_ttl = 31536000
    default_ttl = 600
    parameters_in_cache_key_and_forwarded_to_origin {
      cookies_config {
          cookie_behavior = "none"
      }
      headers_config {
          header_behavior = "none"
      }
      query_strings_config {
        query_string_behavior = "all"
      }
    }
}

resource "aws_cloudfront_cache_policy" "new_default_cache_optimized" {
    name = "New_Default_CacheOptimized"
    comment = "Default Caching Policy with longer default TTL"
    min_ttl = 0
    max_ttl = 31536000
    default_ttl = 604800
    parameters_in_cache_key_and_forwarded_to_origin {
      cookies_config {
          cookie_behavior = "whitelist"
          cookies {
              items = ["SSESS*"]
          }
      }
      headers_config {
          header_behavior = "none"
      }
      query_strings_config {
        query_string_behavior = "all"
      }
    }
}