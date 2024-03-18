# ECR repositories.

locals {
  default_tag_policy = jsonencode({
    rules = [
      {
        rulePriority = 1
        description  = "Quickly expire untagged images"

        selection = {
          tagStatus   = "untagged"
          countType   = "sinceImagePushed"
          countUnit   = "days"
          countNumber = 1
        }

        action = {
          type = "expire"
        }
      },
      {
        rulePriority = 2
        description  = "Retain at most 10 images"

        selection = {
          tagStatus   = "any"
          countType   = "imageCountMoreThan"
          countNumber = 10
        }

        action = {
          type = "expire"
        }
      }
    ]
  })
}

# First, create a custom Drupal container repository. This will house our built Drupal
# images.
resource "aws_ecr_repository" "drupal" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-drupal"

  tags = var.tags
}

resource "aws_ecr_lifecycle_policy" "drupal" {
  for_each = toset(var.sites)

  repository = aws_ecr_repository.drupal[each.key].name

  policy = local.default_tag_policy
}

# Second, we also create an nginx repository. We do this for two reasons:
# 1. It gives nginx full access to the built Drupal filesystem in order to serve static files
# 2. It lets us copy custom configuration into the image.
resource "aws_ecr_repository" "nginx" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-nginx"

  tags = var.tags
}

resource "aws_ecr_lifecycle_policy" "nginx" {
  for_each = toset(var.sites)

  repository = aws_ecr_repository.nginx[each.key].name
  
  policy = local.default_tag_policy
}

# Create a custom Drush container repo
resource "aws_ecr_repository" "drush" {
  for_each = toset(var.sites)

  name = "webcms-${var.environment}-${each.key}-drush"

  tags = var.tags
}

resource "aws_ecr_lifecycle_policy" "drush" {
  for_each = toset(var.sites)

  repository = aws_ecr_repository.drush[each.key].name

  policy = local.default_tag_policy
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
