# Create the user-facing load balancer for the ECS cluster
resource "aws_lb" "frontend" {
  name               = "webcms-frontend-${local.env-suffix}"
  internal           = false
  load_balancer_type = "network"
  security_groups    = [aws_security_group.load_balancer.id]
  subnets            = aws_subnet.public.*.id

  access_logs {
    bucket  = aws_s3_bucket.elb_logs.bucket
    enabled = true
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Load Balancer"
  })

  # Explicitly depend on the S3 bucket policy that enables the ALB to deliver logs
  depends_on = [aws_s3_bucket_policy.elb_logs_delivery]
}

# Listener for HTTP requests. This forwards to the HTTP target group
resource "aws_lb_listener" "frontend_http" {
  load_balancer_arn = aws_lb.frontend.arn
  port              = 80
  protocol          = "TCP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb
  }
}

resource "aws_lb_target_group" "drupal_http_target_group" {
  name = "webcms-drupal-http-tg-${local.env-suffix}"

  port        = 80
  protocol    = "TCP"
  target_type = "ip"
  vpc_id      = local.vpc-id

  # For the HTTP endpoint, we use cheap TCP checks instead of HTTP health checks.
  health_check {
    enabled  = true
    interval = 30
    timeout  = 5
    port     = 80
    protocol = "TCP"
  }
}

# Target group for Drupal container tasks
resource "aws_lb_target_group" "drupal_https_target_group" {
  name = "webcms-drupal-https-tg-${local.env-suffix}"

  port        = 443
  protocol    = "TLS"
  target_type = "ip"
  vpc_id      = local.vpc-id

  # Have the load balancer target the PHP-FPM status port (:8080) instead of the Drupal
  # application. In an ideal world, we could hit / to determine if Drupal is still
  # healthy, but this causes so much load on the PHP-FPM pool that it can cause the
  # container to fail to respond in time, resulting in an unhealthy task - which in turn
  # puts more load on the other containers, which can cause them to become unhealthy.
  health_check {
    enabled  = true
    interval = 300
    timeout  = 60
    path     = "/ping"
    port     = 8080
    protocol = "HTTP"
  }
}

# Send all HTTPS requests to Drupal
resource "aws_lb_listener" "frontend_https" {
  load_balancer_arn = aws_lb.frontend.arn
  port              = 443
  protocol          = "TLS"
  certificate_arn   = var.alb-certificate

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.drupal_https_target_group.arn
  }
}
