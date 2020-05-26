# Obtain the AWS-recommended AMI ID for ECS. By using this parameter store value, we can
# perform region-agnostic updates by simply re-applying with Terraform. As this value
# changes, Terraform will update the launch template.
data "aws_ssm_parameter" "ecs-ami" {
  name = "/aws/service/ecs/optimized-ami/amazon-linux-2/recommended/image_id"
}

# Ask AWS to prefer spreading EC2 instances across physical servers. This pattern is
# designed to minimize possible downtime due to errors in the underlying AWS hardware,
# since it reduces the number of instances in any given point of failure.
resource "aws_placement_group" "servers" {
  name = "WebCMS-Placement"

  strategy = "spread"
}

data "template_cloudinit_config" "servers" {
  base64_encode = true
  gzip          = true

  # Ask cloud-init to install the AWS CLI
  # cf. https://cloudinit.readthedocs.io/en/latest/
  part {
    content_type = "text/cloud-config"
    content      = <<-CONFIG
    packages:
      - awscli
    CONFIG
  }

  part {
    # ECS options configured below:
    # 1. ECS_CLUSTER tells the ECS agent which cluster to join.
    # 2. ECS_AWSVPC_BLOCK_IMDS prevents containers from accessing the EC2 instance
    #    metadata service
    # 3. ECS_RESERVED_MEMORY tells the ECS agent to mark memory as unavailable for use by
    #    ECS, which prevents the OS from running low on memory due to over-allocation
    # 4. ECS_ENABLE_SPOT_INSTANCE_DRAINING tells the ECS agent to listen for spot instance
    #    termination events and begin draining during the two-minute termination window
    content_type = "text/x-shellscript"
    content      = <<-USERDATA
    #!/bin/bash
    set -euo pipefail

    # Run custom bootstrap script (if present)
    ${var.server-extra-bootstrap}

    # Join the cluster (see comments above)
    cat <<EOF >> /etc/ecs/ecs.config
    ECS_CLUSTER=${var.cluster-name}
    ECS_AWSVPC_BLOCK_IMDS=true
    ECS_RESERVED_MEMORY=128
    ECS_ENABLE_SPOT_INSTANCE_DRAINING=true
    EOF
    USERDATA
  }
}

# Since we're using a mixed-instances policy, this template doesn't define an instance
# type. See the autoscaling group below.
resource "aws_launch_template" "servers" {
  name = "webcms-launch-template"

  image_id               = data.aws_ssm_parameter.ecs-ami.value
  vpc_security_group_ids = [aws_security_group.server.id]

  monitoring {
    enabled = true
  }

  iam_instance_profile {
    name = aws_iam_instance_profile.ec2_servers.name
  }

  # Root volume used for the OS
  block_device_mappings {
    device_name = "/dev/xvda"

    ebs {
      volume_size           = 64
      volume_type           = "gp2"
      delete_on_termination = true
    }
  }

  # Volume used for Docker
  # cf. https://docs.aws.amazon.com/AmazonECS/latest/developerguide/launch_container_instance.html
  block_device_mappings {
    device_name = "/dev/xvdcz"

    ebs {
      volume_size           = 64
      volume_type           = "gp2"
      delete_on_termination = true
    }
  }

  user_data = data.template_cloudinit_config.servers.rendered

  tags = {
    Group = "webcms"
    Name  = "WebCMS Launch Template"
  }

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_autoscaling_group" "servers" {
  name = "webcms-autoscaling"

  # NB. We don't set the desired count because it will be managed by the ECS capacity
  # provider.
  max_size = var.server-max-capacity
  min_size = var.server-min-capacity

  # Recycle oldest instaces first, which should help ensure that servers are running the
  # latest ECS AMI.
  termination_policies = ["OldestInstance"]

  # Enforce statelessness by removing servers after 7 days.
  max_instance_lifetime = 7 * 24 * 3600

  # Only launch into the private VPC subnet - this will require that all traffic goes
  # through the ALB.
  vpc_zone_identifier = aws_subnet.private.*.id

  mixed_instances_policy {
    instances_distribution {
      # We require at least one on-demand server at all times and request that 10% of the
      # autoscaling group's servers are on-demand as well.
      on_demand_base_capacity                  = 1
      on_demand_percentage_above_base_capacity = 10

      # Find the cheapest servers in as many spot instance pools as we can get away with.
      # This increases the chances of disruption compared to the capacity-optimized
      # strategy, but since we blend on-demand instances we're willing to shed a few spot
      # instances from time to time.
      spot_allocation_strategy = "lowest-price"
      spot_instance_pools      = 3
    }

    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.servers.id
        version            = "$Latest"
      }

      override {
        instance_type = var.server-instance-types.primary
      }

      override {
        instance_type = var.server-instance-types.secondary
      }

      override {
        instance_type = var.server-instance-types.tertiary
      }
    }
  }

  tag {
    key                 = "Application"
    value               = "WebCMS"
    propagate_at_launch = true
  }

  tag {
    key                 = "Name"
    value               = "WebCMS Cluster"
    propagate_at_launch = true
  }
}
