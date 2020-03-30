# Log group for all nginx container logs
resource "aws_cloudwatch_log_group" "nginx" {
  name = "webcms/app-nginx"
}

# Log group for all Drupal container logs
resource "aws_cloudwatch_log_group" "drupal" {
  name = "webcms/app-drupal"
}

# Log group for all Drush tasks, which we keep separate from the Drupal site logs
resource "aws_cloudwatch_log_group" "drush" {
  name = "webcms/app-drush"
}
