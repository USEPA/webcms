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

resource "aws_launch_template" "servers" {
  name = "webcms-launch-template"

  image_id               = data.aws_ssm_parameter.ecs-ami.value
  instance_type          = var.server-instance-type
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

  # 1. Explicitly identify which cluster we're joining
  # 2. Don't allow access to the EC2 metadata service - instead, we have task-specific IAM
  #    roles
  # 3. Reserve memory for system tasks on each EC2 instance in order to prevent resource
  #    starvation
  user_data = base64encode(
    <<-USERDATA
    #!/bin/bash
    cat <<EOF >> /etc/ecs/ecs.config
    ECS_CLUSTER=${var.cluster-name}
    ECS_AWSVPC_BLOCK_IMDS=true
    ECS_RESERVED_MEMORY=128
    EOF
    USERDATA
  )

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Launch Template"
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

  # Only launch into the private VPC subnet - this will require that all traffic goes
  # through the ALB.
  vpc_zone_identifier = aws_subnet.private.*.id

  launch_template {
    id      = aws_launch_template.servers.id
    version = "$Latest"
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
