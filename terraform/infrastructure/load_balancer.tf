# Create the user-facing load balancer for the ECS cluster
resource "aws_lb" "load_balancer" {
  name               = "webcms-${var.environment}"
  internal           = var.lb_internal
  load_balancer_type = "network"
  subnets            = local.public_subnets

  access_logs {
    bucket  = coalesce(var.lb_logging_bucket, aws_s3_bucket.elb_logs.bucket)
    enabled = true
  }

  tags = var.tags

  # Explicitly depend on the S3 bucket policy that enables the NLB to deliver logs
  depends_on = [aws_s3_bucket_policy.elb_logs_delivery]
}

# Listener for HTTP requests
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.load_balancer.arn
  port              = 80
  protocol          = "TCP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.http.arn
  }
}

resource "aws_lb_target_group" "http" {
  name = "webcms-${var.environment}-http"

  port        = 80
  protocol    = "TCP"
  target_type = "ip"
  vpc_id      = data.aws_ssm_parameter.vpc_id.value

  health_check {
    enabled  = true
    interval = 30
    port     = 80
    protocol = "TCP"
  }
}

# Listener for HTTPS
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.load_balancer.arn
  port              = 443
  protocol          = "TCP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.https.arn
  }
}

resource "aws_lb_target_group" "https" {
  name = "webcms-${var.environment}-https"

  port        = 443
  protocol    = "TCP"
  target_type = "ip"
  vpc_id      = data.aws_ssm_parameter.vpc_id.value

  health_check {
    enabled  = true
    interval = 30
    port     = 443
    protocol = "TCP"
  }
}

resource "aws_lb" "app_load_balancer" {
  name               = "webcms-${var.environment}-alb"
  internal           = true
  load_balancer_type = "application"

  subnets         = local.public_subnets
  security_groups = [data.aws_ssm_parameter.alb_security_group.value]

  access_logs {
    bucket  = coalesce(var.lb_logging_bucket, aws_s3_bucket.elb_logs.bucket)
    enabled = true
  }

  tags = var.tags

  # Explicitly depend on the S3 bucket policy that enables the ALB to deliver logs
  depends_on = [aws_s3_bucket_policy.elb_logs_delivery]
}

resource "aws_lb_listener" "alb_health" {
  load_balancer_arn = aws_lb.app_load_balancer.arn

  port     = 8080
  protocol = "HTTP"

  default_action {
    type = "fixed-response"

    fixed_response {
      content_type = "text/plain"
      status_code  = 204
    }
  }
}

resource "aws_lb_target_group" "alb_http" {
  name = "webcms-${var.environment}-alb-http"
  vpc_id      = data.aws_ssm_parameter.vpc_id.value
  
  port        = 80
  protocol    = "HTTP"
  target_type = "ip"

  health_check {
    enabled  = true
    port     = 8080
    protocol = "HTTP"
    path     = "/ping"
  }
}

resource "aws_lb_listener" "alb_http" {
  load_balancer_arn = aws_lb.app_load_balancer.arn

  port     = 80
  protocol = "HTTP"

  default_action {
    type = "redirect"

    redirect {
      port        = 443
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

resource "aws_lb_listener" "alb_https" {
  load_balancer_arn = aws_lb.app_load_balancer.arn
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = var.lb_default_certificate

  port     = 443
  protocol = "HTTPS"
  
  default_action {
    type = "forward"
    target_group_arn = aws_lb_target_group.alb_http.arn
  }
}

resource "aws_lb_listener_certificate" "alb_https" {
  for_each = toset(var.lb_extra_certificates)

  listener_arn    = aws_lb_listener.alb_https.arn
  certificate_arn = each.key
}
