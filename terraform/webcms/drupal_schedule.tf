resource "aws_appautoscaling_scheduled_action" "drupal_scale_in" {
  count = var.drupal_schedule_min_capacity != null ? 1 : 0

  name = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-schedule-in"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  # Schedule expression: at 7:30 AM, Monday-Friday, US Eastern time zone
  schedule = "cron(30 7 * * MON-FRI *)"
  timezone = "America/New_York"

  scalable_target_action {
    min_capacity = var.drupal_schedule_min_capacity
  }
}

resource "aws_appautoscaling_scheduled_action" "drupal_scale_in" {
  count = var.drupal_schedule_min_capacity != null ? 1 : 0

  name = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-schedule-out"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  # Schedule expression: at 7:30 PM, Monday-Friday, US Eastern time zone
  schedule = "cron(30 19 * * MON-FRI *)"
  timezone = "America/New_York"

  scalable_target_action {
    min_capacity = var.drupal_min_capacity
  }
}
