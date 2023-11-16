# Schedule scaling: Increase the task minimum before US Eastern working hours.
resource "aws_appautoscaling_scheduled_action" "drupal_scale_out" {
  count = var.drupal_schedule_min_capacity != null ? 1 : 0

  name = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-schedule-out"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  # Schedule expression: at 6:00 AM, Monday-Friday, US Eastern time zone
  schedule = "cron(0 6 * * MON-FRI *)"
  timezone = "America/New_York"

  scalable_target_action {
    min_capacity = var.drupal_schedule_min_capacity
  }
}

# Schedule scaling: Decrease the task minimum after US Eastern working hours.
# Since this only lowers the minimum, it should not negatively impact authors in
# more western time zones.
resource "aws_appautoscaling_scheduled_action" "drupal_scale_in" {
  count = var.drupal_schedule_min_capacity != null ? 1 : 0

  name = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-schedule-int"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  # Schedule expression: at 5:00 PM, Monday-Friday, US Eastern time zone
  schedule = "cron(0 17 * * MON-FRI *)"
  timezone = "America/New_York"

  scalable_target_action {
    min_capacity = var.drupal_min_capacity
  }
}
