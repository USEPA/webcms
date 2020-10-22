# This launch template is very similar to the one in servers.tf with the exception that
# it uses a single higher-performing instance instead of relying on spot market overrides.
resource "aws_launch_template" "migration_servers" {
  name = "webcms-migration-template-${local.env-suffix}"

  image_id               = data.aws_ssm_parameter.ecs-ami.value
  vpc_security_group_ids = [aws_security_group.server.id]

  instance_type = "c5.metal"

  monitoring {
    enabled = true
  }

  iam_instance_profile {
    name = aws_iam_instance_profile.ec2_servers.name
  }

  block_device_mappings {
    device_name = "/dev/xvda"

    ebs {
      encrypted             = true
      kms_key_id            = var.encryption-at-rest-key
      volume_size           = 64
      volume_type           = "gp2"
      delete_on_termination = true
    }
  }

  user_data = data.template_cloudinit_config.servers.rendered

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Migration Launch Template"
  })

  lifecycle {
    create_before_destroy = true
  }
}

# This autoscaling group is similar to servers.tf, but with a few differences:
# 1. It uses a fixed size of 0/1 since it is only used for Drush migrations.
# 2. It does not request spot instances or set a max lifetime; we need migrations to run
#    without any interruption.
# 3. It protects instances from scale in.
resource "aws_autoscaling_group" "migration_servers" {
  name = "webcms-migration-autoscaling-${local.env-suffix}"

  min_size = 0
  max_size = 1

  vpc_zone_identifier = aws_subnet.private.*.id

  protect_from_scale_in = true

  metrics_granularity = "1Minute"
  enabled_metrics = [
    "GroupMinSize",
    "GroupMaxSize",
    "GroupInServiceInstances",
    "GroupPendingInstances",
    "GroupStandbyInstances",
    "GroupTerminatingInstances",
  ]

  launch_template {
    id      = aws_launch_template.migration_servers.id
    version = "$Latest"
  }

  dynamic "tag" {
    for_each = merge(local.common-tags, { Name = "${local.name-prefix} Migration" })

    content {
      key                 = tag.key
      value               = tag.value
      propagate_at_launch = true
    }
  }
}

# Create a separate migration capacity provider to provide these instances to the ECS
# cluster.
resource "aws_ecs_capacity_provider" "migration_capacity" {
  name = "webcms-migration-${random_pet.capacity_provider.id}"

  auto_scaling_group_provider {
    auto_scaling_group_arn = aws_autoscaling_group.migration_servers.arn

    # Enable termination protection - instances won't be scaled in unless the ECS agent
    # confirms they're not running anything.
    managed_termination_protection = "ENABLED"

    managed_scaling {
      status = "ENABLED"

      minimum_scaling_step_size = 1
      maximum_scaling_step_size = 1
    }
  }
}
