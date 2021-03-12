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

      # Service updates are triggered if the image name changes
      image = var.image_tag_drupal

      # If this container exits for any reason, mark the task as unhealthy and force a restart
      essential = true

      # Inject the S3 information needed to connect for s3fs (cf. shared.tf)
      environment = local.drupal_environment

      # Inject the DB credentials needed (cf. shared.tf)
      secrets = local.drupal_secrets

      # Expose port 9000 inside the task. This is a FastCGI port, not an HTTP one, so it
      # won't be of use to anyone save for nginx. Most importantly, this means that this
      # port should NOT be exposed to a load balancer.
      portMappings = [{ containerPort = 9000 }]

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
      name = "nginx"

      # As with the Drupal definition, service updates are triggered when this changes
      image = var.image_tag_nginx

      # Docker labels are how we communicate our routing preferences to Traefik. These settings
      # correspond to Traefik's own configuration names. Specifically, we make use of router
      # configuration to dynamically create a Router (see https://doc.traefik.io/traefik/routing/routers/)
      # when this task launches in ECS.
      dockerLabels = {
        # Advertise to Traefik that we want to receive traffic
        "traefik.enable" = "true"

        # Tell Traefik to allow any hostname provided in variables. The expression on the right is
        # a map over the list of domains, which expands to a Traefik routing rule like the below:
        #  Rule: Host(`example.org`) || Host(`example.com`)
        "traefik.http.routers.${var.site}_${var.lang}.rule" = join(" || ", formatlist("Host(`%s`)", concat([var.drupal_hostname], var.drupal_extra_hostnames)))
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

      # Expose port 80 from the task. This port is not connected to the load balancer; instead,
      # Traefik routes to it after matching hostnames.
      portMappings = [{ containerPort = 80 }]

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
      image = "amazon/cloudwatch-agent:latest"

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

    # This is a small Alpine container used to report metrics from FPM to CloudWatch
    {
      name  = "metrics"
      image = "alpine:latest"

      environment = [
        { name = "WEBCMS_ENV_NAME", value = "${var.site}-${var.lang}" },

        # This is the map needed for mapping PHP-FPM metrics to CloudWatch. The keys are
        # the metric names output by PHP-FPM, and the values have two expected fields:
        # - name: the CloudWatch metric name (usually in PascalCase)
        # - unit: the CloudWatch unit (typically Count or Seconds)
        #
        # The keys of the FPM status JSON are listed here:
        # - "pool": the FPM pool name
        # - "process manager": the kind of FPM process manager (one of "static",
        #   "dynamic", or "ondemand")
        # - "start time": the UNIX timestamp when PHP-FPM started
        # - "start since": the number of seconds since the start time
        # - "accepted conn": number of requests accepted by the pool
        # - "listen queue": the size of PHP-FPM's socket backlog
        # - "max listen queue": the maximum number of requests in the pending queue seen
        #   since FPM started
        # - "listen queue len": the number of pending connections
        # - "idle processes": number of inactive PHP-FPM workers
        # - "active processes": number of active workers
        # - "total workers": number of workers, both idle and active
        # - "max active processes": highest number of active processes since PHP-FPM
        #   started
        # - "max children reached": the number of times PHP-FPM has reached the maximum
        #   worker size (only applicable for dynamic/ondemand process managers)
        #
        # We don't track every metric since some of them are redundant in the face of
        # CloudWatch metric math, but we report a significant subset. The metrics we track
        # are listed in the object below.
        {
          name = "WEBCMS_METRICS_MAP"
          value = jsonencode({
            # Track the age of the FPM process in order to allow us to convert counts to
            # counts per second
            "start since" = {
              name = "Age"
              unit = "Seconds"
            }
            "accepted conn" = {
              name = "RequestsAccepted"
              unit = "Count"
            }
            "listen queue" = {
              name = "RequestsPending"
              unit = "Count"
            }
            # By reporting the listen queue length, we can track the size of the listen
            # queue as a percentage (using RequestsPending/ListenQueueLength), which gives
            # us a good estimate of backlog pressure at the FPM socket level.
            "listen queue len" = {
              name = "ListenQueueLength"
              unit = "Count"
            }
            "idle processes" = {
              name = "ProcessesIdle"
              unit = "Count"
            }
            "active processes" = {
              name = "ProcessesActive"
              unit = "Count"
            }
            # We report this value in order to compute MaxChildrenReached/Age. This value
            # is the rate at which PHP-FPM hits its maximum workers. The closer it gets to
            # 1, the harder it means PHP-FPM is working.
            "max children reached" = {
              name = "MaxChildrenReached"
              unit = "Count"
            }
          })
        },

        {
          name = "WEBCMS_METRICS_SCRIPT"

          # This is a jq script (https://stedolan.github.io/jq) that processes the metrics
          # map against the output of PHP-FPM's status page.
          #
          # It is a simple array loop that expands { key, value } pairs from the
          # $WEBCMS_METRICS_MAP, looks up the key in the PHP-FPM output, and then formats
          # the result as a set of CloudWatch metrics.
          #
          # The beginning of the script is a variable assignment, ". as $input", which
          # saves the input stream (the JSON output from curl) as a variable, and switches
          # over to looping over the entries of the $metrics variable (the parsed JSON
          # of $WEBCMS_METRICS_MAP).
          #
          # The to_entries function converts an object into an array of { key, value }
          # objects, where key is the JSON key (e.g., "idle processes") and value is the
          # object value - in this case, it will be the { name, unit } pair.
          #
          # After that, the map() function constructs a CloudWatch-formatted value
          # suitable for passing to `aws cloudwatch put-metric-data`, which handles the
          # heavy lifting of publishing the PHP-FPM metrics.
          value = <<-SCRIPT
            . as $input
            | $metrics
            | to_entries
            | map({
              MetricName: .value.name,
              Unit: .value.unit,
              Value: $input[.key],
              Timestamp: now | floor,
              Dimensions: [
                { Name: "Environment", Value: "\($ENV.WEBCMS_ENV_NAME)" }
              ]
            })
          SCRIPT
        },
      ]

      # The inline shell script here scrapes PHP-FPM's metrics every 60 seconds and
      # reports them back to CloudWatch. It uses the inline jq script and metrics
      # configuration (see above) to transform the FPM output to what the
      # `put-metric-data` command expects.
      entrypoint = ["/bin/sh", "-c"]
      command = [
        <<-COMMAND
        apk add --no-cache aws-cli curl jq

        while true; do
          sleep 60

          input="$(curl -s http://localhost:8080/status?json)"
          echo "PHP-FPM metrics: $input"

          metrics="$(echo "$input" | jq -c "$WEBCMS_METRICS_SCRIPT" --argjson metrics "$WEBCMS_METRICS_MAP")"
          echo "CloudWatch metrics: $metrics"

          aws cloudwatch --region=${var.aws_region} put-metric-data --namespace WebCMS/FPM --metric-data "$metrics"
        done
        COMMAND
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
  launch_type = "FARGATE"
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
