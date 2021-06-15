locals {
  traefik_version = "2.4"
}

resource "aws_ecs_task_definition" "traefik" {
  family = "webcms-${var.environment}-traefik"

  task_role_arn      = aws_iam_role.traefik_task.arn
  execution_role_arn = aws_iam_role.traefik_exec.arn

  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]

  cpu    = 256
  memory = 512

  container_definitions = jsonencode([
    {
      name      = "traefik"
      image     = "${aws_ecr_repository.traefik_mirror.repository_url}:${local.traefik_version}"
      essential = true

      entryPoint = ["traefik"]

      command = [
        # Tell Traefik which region it's in
        "--providers.ecs.region=${var.aws_region}",

        # Force discovery only on this cluster
        "--providers.ecs.autoDiscoverClusters=false",
        "--providers.ecs.clusters=${aws_ecs_cluster.cluster.name}",

        # Don't expose services/tasks by default
        "--providers.ecs.exposedByDefault=false",

        # Listen for HTTP traffic on port 80
        "--entryPoints.web.address=:80",

        # Honor the NLB's PROXY protocol
        "--entryPoints.web.proxyProtocol=true",
        "--entryPoints.web.proxyProtocol.trustedIPs=${join(",", local.public_cidrs)}",

        # Redirect HTTP traffic to HTTPS
        "--entryPoints.web.http.redirections.entryPoint.to=websecure",
        "--entrypoints.web.http.redirections.entryPoint.scheme=https",

        # Listen for (decrypted) HTTPS traffic on port 443
        "--entryPoints.websecure.address=:443",

        # Honor the NLB's PROXY protocol
        "--entryPoints.websecure.proxyProtocol=true",
        "--entryPoints.websecure.proxyProtocol.trustedIPs=${join(",", local.public_cidrs)}",
      ]

      portMappings = [
        { containerPort = 80 },
        { containerPort = 443 },
      ]

      logConfiguration = {
        logDriver = "awslogs"

        options = {
          awslogs-group         = aws_cloudwatch_log_group.traefik.name
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "traefik"
        }
      }
    }
  ])

  tags = var.tags
}

resource "aws_ecs_service" "traefik" {
  name = "webcms-${var.environment}-traefik"

  cluster         = aws_ecs_cluster.cluster.name
  task_definition = aws_ecs_task_definition.traefik.arn

  desired_count = 2

  enable_ecs_managed_tags = true
  propagate_tags          = "SERVICE"

  # HTTP (port 80) configuration
  load_balancer {
    container_name   = "traefik"
    container_port   = 80
    target_group_arn = aws_lb_target_group.http.arn
  }

  # HTTPS (port 443) configuration
  load_balancer {
    container_name   = "traefik"
    container_port   = 443
    target_group_arn = aws_lb_target_group.https.arn
  }

  network_configuration {
    subnets          = local.private_subnets
    assign_public_ip = false
    security_groups  = [data.aws_ssm_parameter.traefik_security_group.value]
  }

  capacity_provider_strategy {
    base              = 1
    capacity_provider = "FARGATE"
    weight            = 100
  }

  tags = var.tags
}

# Scale Traefik out based on CPU utilization. We've chosen a low 30% CPU target because
# Traefik is a router/load balance, making utilization a less than ideal proxy metric for
# what we're really interested in (number of connections being processed).
resource "aws_appautoscaling_target" "traefik" {
  min_capacity       = var.traefik_min_capacity
  max_capacity       = var.traefik_max_capacity
  resource_id        = "service/${aws_ecs_cluster.cluster.name}/${aws_ecs_service.traefik.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

resource "aws_appautoscaling_policy" "traefik" {
  name        = "webcms-${var.environment}-traefik-cpu"
  policy_type = "TargetTrackingScaling"

  resource_id        = aws_appautoscaling_target.traefik.id
  scalable_dimension = aws_appautoscaling_target.traefik.scalable_dimension
  service_namespace  = aws_appautoscaling_target.traefik.service_namespace

  target_tracking_scaling_policy_configuration {
    target_value = 30

    scale_in_cooldown  = 5 * 60
    scale_out_cooldown = 60

    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
  }
}
