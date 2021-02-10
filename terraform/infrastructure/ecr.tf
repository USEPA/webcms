# ECR repositories.

# First, create a custom Drupal container repository. This will house our built Drupal
# images.
resource "aws_ecr_repository" "drupal" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-drupal"

  tags = var.tags
}

# Second, we also create an nginx repository. We do this for two reasons:
# 1. It gives nginx full access to the built Drupal filesystem in order to serve static files
# 2. It lets us copy custom configuration into the image.
resource "aws_ecr_repository" "nginx" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-nginx"

  tags = var.tags
}

# Create a custom Drush container repo
resource "aws_ecr_repository" "drush" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-drush"

  tags = var.tags
}
