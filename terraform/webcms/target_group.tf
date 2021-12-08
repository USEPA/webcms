# Create a target group for the Drupal service.
resource "aws_lb_target_group" "drupal" {
  name = "${var.environment}-${var.site}-${var.lang}"

  port        = 443
  protocol    = "HTTPS"
  target_type = "ip"
  vpc_id      = data.aws_ssm_parameter.vpc_id.value
  
  health_check {
    enabled  = true
    port     = 8080
    protocol = "HTTP"
    path     = "/ping"
  }
}

# Route requests for the Drupal hostnames to the target group.
resource "aws_lb_listener_rule" "drupal" {
  listener_arn = data.aws_ssm_parameter.alb_listener.value

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.drupal.arn
  }

  condition {
    host_header {
      values = concat([var.drupal_hostname], var.drupal_extra_hostnames)
    }
  }
}
