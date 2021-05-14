# Define the Drupal ECS task. An ECS task is the lower-level definition of what
# containers to run and their configuration (permissions, volumes, etc.).
# Creating a task definition does not, by itself, define the actual web-facing application.
resource "aws_ecs_task_definition" "drupal_task" {
  family             = "webcms-${var.environment}-${var.site}-${var.lang}-drupal"
  network_mode       = "awsvpc"
  task_role_arn      = data.aws_ssm_parameter.drupal_iam_task.value
  execution_role_arn = data.aws_ssm_parameter.drupal_iam_exec.value

  requires_compatibilities = ["FARGATE"]

  # Setting reservations at the task level lets Docker be more flexible in how the
  # resources are used (mainly, it allows Drupal to soak up as much CPU capacity as it
  # needs)
  cpu    = 1024
  memory = 2048

  container_definitions = jsonencode([
    # Drupal container. The WebCMS' Drupal container is based on an FPM-powered PHP
    # container, which means that by itself it cannot receive HTTP requests. Instead, the
    # task also includes an nginx container (see below) that "adapts" requests from HTTP
    # to FastCGI, the protocol that FPM uses.
    {
      # Do not change the name of this container freely: ECS uses this as a hostname when
      # the task is launched, and it is the primary way in which two containers will be
      # able to communicate (since the IP address will vary).
      name = "drupal"

      # Service updates are triggered if var.image_tag changes
      image = "${data.aws_ssm_parameter.ecr_drupal.value}:${var.image_tag}"

      # If this container exits for any reason, mark the task as unhealthy and force a restart
      essential = true

      # Inject the S3 information needed to connect for s3fs (cf. shared.tf)
      environment = local.drupal_environment

      # Inject the DB credentials needed (cf. shared.tf)
      secrets = local.drupal_secrets

      # Shunt logs to the PHP-FPM CloudWatch log group
      logConfiguration = {
        logDriver = "awslogs"

        options = {
          awslogs-group         = data.aws_ssm_parameter.php_fpm_log_group.value
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "php-fpm"
        }
      }
    },

    # nginx container. As mentioned above, this container exists primarily to route
    # requests. We use nginx separately instead of, for example, Apache and mod_php
    # because nginx is specialized for HTTP server tasks, and any task we can perform in
    # nginx removes some of the PHP overhead.
    {
      name  = "nginx"
      image = "${data.aws_ssm_parameter.ecr_nginx.value}:${var.image_tag}"

      # Docker labels are how we communicate our routing preferences to Traefik. These
      # settings correspond to Traefik's own configuration names. Specifically, we make
      # use of router configuration to dynamically create a Router (see
      # https://doc.traefik.io/traefik/routing/routers/) when this task launches in ECS.
      dockerLabels = {
        # Advertise to Traefik that we want to receive traffic
        "traefik.enable" = "true"

        # Tell Traefik to allow any hostname provided in variables. The expression on the
        # right is a map over the list of domains, which expands to a Traefik routing rule
        # like the below:
        #
        #     Rule: Host(`example.org`) || Host(`example.com`)
        "traefik.http.routers.${var.site}_${var.lang}.rule" = join(" || ", formatlist("Host(`%s`)", concat([var.drupal_hostname], var.drupal_extra_hostnames)))

        # Only allow routing to this task via the "websecure" (port 443) entrypoint
        "traefik.http.routers.${var.site}_${var.lang}.entrypoints" = "websecure"

        # Ask Traefik to forward traffic to nginx port 443
        "traefik.http.services.${var.site}_${var.lang}.loadbalancer.server.port" = "443"
      }

      environment = [
        # See nginx.conf in services/drupal for why this is needed.
        { name = "WEBCMS_DOMAIN", value = var.drupal_hostname },

        # Inject the S3 domain name so that nginx can proxy to it - we do this instead of
        # the region and bucket name because in us-east-1, the domain isn't easy to
        # construct via "$bucket.s3-$region.amazonaws.com".
        { name = "WEBCMS_S3_DOMAIN", value = data.aws_ssm_parameter.drupal_s3_domain.value },

        # Pass all the valid domain names as $WEBCMS_SERVER_NAMES
        { name = "WEBCMS_SERVER_NAMES", value = join(" ", concat([var.drupal_hostname], var.drupal_extra_hostnames)) },
      ]

      # As with the Drupal container, we ask ECS to restart this task if nginx fails
      essential = true

      # Expose port 443 from the task. This port is not connected to the load balancer;
      # instead, Traefik routes to it after matching hostnames.
      portMappings = [{ containerPort = 443 }]

      dependsOn = [
        { containerName = "drupal", condition = "START" }
      ],

      # Shunt logs to the nginx CloudWatch log group
      logConfiguration = {
        logDriver = "awslogs",

        options = {
          awslogs-group         = data.aws_ssm_parameter.nginx_log_group.value
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "nginx"
        }
      }
    },

    # In ECS, Amazon's CloudWatch agent can be run to collect application metrics. See
    # the epa_metrics module for what we export to the agent.
    {
      name  = "cloudwatch"
      image = "${data.aws_ssm_parameter.ecr_cloudwatch.value}:latest"

      # The agent reads its JSON-formatted configuration from the environment in containers
      environment = [
        {
          name = "CW_CONFIG_CONTENT"
          # cf. https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/CloudWatch-Agent-Configuration-File-Details.html
          value = jsonencode({
            metrics = {
              namespace = "WebCMS",
              metrics_collected = {
                statsd = {
                  service_address = ":8125"
                },
              },
            },
          }),
        },
      ],

      logConfiguration = {
        logDriver = "awslogs"

        options = {
          awslogs-group         = data.aws_ssm_parameter.agent_log_group.value
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "cloudwatch"
        }
      }
    },

    # Report FPM metrics to CloudWatch using the custom metrics container. See the
    # services/metrics directory for more.
    {
      name  = "metrics"
      image = "${data.aws_ssm_parameter.ecr_metrics.value}:${var.image_tag}"

      environment = [
        { name = "AWS_REGION", value = var.aws_region },
        { name = "WEBCMS_SITE", value = "${var.site}-${var.lang}" },
      ]

      logConfiguration = {
        logDriver = "awslogs"

        options = {
          awslogs-group         = data.aws_ssm_parameter.fpm_metrics_log_group.value
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "fpm-metrics"
        }
      }
    }
  ])

  tags = var.tags
}

# Create the actual ECS service that serves Drupal traffic. This uses the Drupal task
# definition from above as its template, and adds the configuration ECS needs in order
# to know how many copies to run, what the scaling rules are, and how to route traffic
# to it from a load balancer.
#
# NB. Be careful with parameters here; Terraform will often force replacement of a service
# instead of an update which can result in downtime.
resource "aws_ecs_service" "drupal" {
  name            = "webcms-${var.environment}-${var.site}-${var.lang}-drupal"
  cluster         = data.aws_ssm_parameter.ecs_cluster_arn.value
  desired_count   = 1
  task_definition = aws_ecs_task_definition.drupal_task.arn

  health_check_grace_period_seconds = 0

  # Since we are referencing JSON keys in secrets, we need to use platform version 1.4.0 (this is
  # not yet LATEST at the time of writing).
  launch_type      = "FARGATE"
  platform_version = "1.4.0"

  deployment_controller {
    type = "ECS"
  }

  # Since we're running our tasks in AWSVPC mode, we have to give extra VPC configuration.
  # We launch the Drupal tasks into our private subnet (which means that they don't get
  # public-facing IPs), and attach the Drupal-specific VPC rules to each task.
  network_configuration {
    subnets          = local.private_subnets
    assign_public_ip = false

    security_groups = [data.aws_ssm_parameter.drupal_security_group.value]
  }

  # Ignore changes to the desired_count attribute - we assume that the application
  # autoscaling rules will take over after deployment.
  lifecycle {
    ignore_changes = [desired_count]
  }

  tags = var.tags
}

# Define the Drupal service as an autoscaling target. Effectively, this configuration
# asks AWS to monitor the desired count of Drupal service replicas.
resource "aws_appautoscaling_target" "drupal" {
  min_capacity       = var.drupal_min_capacity
  max_capacity       = var.drupal_max_capacity
  resource_id        = "service/${data.aws_ssm_parameter.ecs_cluster_name.value}/${aws_ecs_service.drupal.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

# We define an autoscaling policy to track high CPU usage. When CPU is above this threshold, ECS
# will add more Drupal tasks until
resource "aws_appautoscaling_policy" "drupal_autoscaling_cpu" {
  name        = "webcms-${var.environment}-${var.site}-${var.lang}-drupal-cpu"
  policy_type = "TargetTrackingScaling"

  resource_id        = aws_appautoscaling_target.drupal.id
  scalable_dimension = aws_appautoscaling_target.drupal.scalable_dimension
  service_namespace  = aws_appautoscaling_target.drupal.service_namespace

  target_tracking_scaling_policy_configuration {
    target_value = 60

    scale_in_cooldown  = 5 * 60
    scale_out_cooldown = 60

    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
  }
}
