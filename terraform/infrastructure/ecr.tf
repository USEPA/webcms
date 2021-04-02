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

# Create a custom repo for the Alpine-based metrics sidecar. See services/metrics for more
# information.
resource "aws_ecr_repository" "metrics" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-fpm-metrics"

  tags = var.tags
}

# The repositories here are mirrors of images on the Docker Hub. We bring them inside the
# AWS perimeter because we are required to statically analyze *all* images prior to
# deployment - not just custom ones.
#
# Mirrors are treated as being environment-wide resources, and thus do not have a for_each
# associated with them.

# This mirrors docker.io/amazon/cloudwatch-agent:latest
resource "aws_ecr_repository" "cloudwatch_agent_mirror" {
  name = "webcms-${var.environment}-aws-cloudwatch"

  tags = var.tags
}

# This mirrors docker.io/traefik:<version>
resource "aws_ecr_repository" "traefik_mirror" {
  name = "webcms-${var.environment}-traefik"

  tags = var.tags
}

# Finally, we create a cache repository for Kaniko-based builds. This repository has some
# lifecycle policies that aggressively expire images in order to avoid an arbitrarily large
# cache from building up (see below).
resource "aws_ecr_repository" "kaniko_cache" {
  name = "webcms-${var.environment}-cache"

  tags = var.tags
}

# The cache repository has two expiration rules:
# 1. If for any reason an image is untagged, expire it after 48 hours.
# 2. All images expire after two weeks.
#
# These rules are designed to strike a balance between ECR storage costs and build
# convenience.
resource "aws_ecr_lifecycle_policy" "kaniko_cache" {
  repository = aws_ecr_repository.kaniko_cache.name

  policy = jsonencode({
    rules = [
      {
        rulePriority = 1
        description  = "Quickly expire untagged images"

        selection = {
          tagStatus   = "untagged"
          countType   = "sinceImagePushed"
          countUnit   = "days"
          countNumber = 2
        }

        action = {
          type = "expire"
        }
      },

      {
        rulePriority = 2
        description  = "Expire all images"

        selection = {
          tagStatus   = "any"
          countType   = "sinceImagePushed"
          countUnit   = "days"
          countNumber = 14
        }

        action = {
          type = "expire"
        }
      },
    ]
  })
}
