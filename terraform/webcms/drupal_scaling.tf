data "aws_arn" "alb" {
  arn = data.aws_ssm_parameter.alb_arn.value
}

data "aws_arn" "tg" {
  arn = aws_lb_target_group.drupal.arn
}

locals {
  # Prefix of the load balancer used to identify an ALB+TG combination for
  # target tracking scaling.
  alb_prefix = substr(data.aws_arn.alb.resource, length("loadbalancer/"), -1)

  # Per the AWS docs:
  #
  # > You create the resource label by appending the final portion of the load
  # > balancer ARN and the final portion of the target group ARN into a single
  # > value, separated by a forward slash (/). The format of the resource label
  # > is:
  # >
  # > ```
  # > app/my-alb/778d41231b141a0f/targetgroup/my-alb-target-group/943f017f100becff
  # > ```
  # >
  # > Where:
  # > * app/<load-balancer-name>/<load-balancer-id> is the final portion of the
  # >   load balancer ARN
  # > * targetgroup/<target-group-name>/<target-group-id> is the final portion
  # >   of the target group ARN
  alb_tracking_label = "${local.alb_prefix}/${data.aws_arn.tg.resource}"
}

# Define the Drupal service as an autoscaling target. Effectively, this configuration
# asks AWS to monitor the desired count of Drupal service replicas.
resource "aws_appautoscaling_target" "drupal" {
  min_capacity       = var.drupal_min_capacity
  max_capacity       = var.drupal_max_capacity
  resource_id        = "service/${data.aws_ssm_parameter.ecs_cluster_name.value}/${aws_ecs_service.drupal.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

# We define an autoscaling policy to track high CPU usage. When CPU is above
# this threshold, ECS will add more Drupal tasks until utilization averages out
# to around 40%, and if CPU is below this threshold, ECS will reduce the task
# count.
resource "aws_appautoscaling_policy" "drupal_autoscaling_cpu" {
  name        = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-cpu"
  policy_type = "TargetTrackingScaling"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  target_tracking_scaling_policy_configuration {
    target_value = 40

    scale_in_cooldown  = 5 * 60
    scale_out_cooldown = 60

    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
  }
}

# In addition to the above policy, we ask ECS to add capacity when individual
# tasks have to handle more than a few hundred requests in an average timespan.
#
# Note that ECS is required to respect both policies: if one policy indicates
# scaling out, then ECS will scale out. If all policies indicate scaling in,
# then ECS will scale in.
resource "aws_appautoscaling_policy" "drupal_autoscaling_requests" {
  name        = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-requests"
  policy_type = "TargetTrackingScaling"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  target_tracking_scaling_policy_configuration {
    # Ask ECS to average around 100 requests/target
    target_value = 100

    scale_in_cooldown  = 5 * 60
    scale_out_cooldown = 60

    predefined_metric_specification {
      predefined_metric_type = "ALBRequestCountPerTarget"
      resource_label         = local.alb_tracking_label
    }
  }
}
