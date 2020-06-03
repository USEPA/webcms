# ECR repositories.

# First, create a custom Drupal container repository. This will house our built Drupal
# images.
resource "aws_ecr_repository" "drupal" {
  name = "webcms-drupal-${local.env-suffix}"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Drupal"
  })
}

# Second, we also create an nginx repository. We do this for two reasons:
# 1. It gives nginx full access to the built Drupal filesystem in order to serve static files
# 2. It lets us copy custom configuration into the image.
resource "aws_ecr_repository" "nginx" {
  name = "webcms-nginx-${local.env-suffix}"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Nginx"
  })
}

# Create a custom Drush container repo
resource "aws_ecr_repository" "drush" {
  name = "webcms-drush-${local.env-suffix}"

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Drush"
  })
}
