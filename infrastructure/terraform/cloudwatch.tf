# CloudWatch dashboards
#
# The structure of a CloudWatch dashboard is relatively simple. A dashboard is modeled as
# a grid of widgets, positioned on abstract X/Y coordinates. The dashboards in this file
# follow a 4x4 layout. Each widget is the same size - 6x6 abstract units. On a 1920x1080
# monitor, this comes out to a widget that is 407x290, with the chart area being 387x270.
# The downside to this is that the fourth row of widgets is off the screen when using
# Firefox, so we should relegate the fourth row to smaller elements.
#
# The rows and columns of each dashboard are, vaguely, ordered like so:
# * Each column represents its own set of metrics (for example, EC2 instances or database
#   latencies). There is no particular order to the columns in a dashboard.
# * The rows are ordered roughly in terms of relevance to overall application health. For
#   instance, in the overview dashboard, the load balancer's target health metrics are
#   placed above the response times.
#
# For each dashboard, there is a dashboard_body argument that is a serialized JSON object
# representing the dashboard's widgets. Each widget has the following properties:
# * `type`: indicating the widget type - metrics, text, logs, etc.
# * `x`: the absolute x-position of the widget
# * `y`: the absolute y-position of the widget
# * `width`: the width of the widget
# * `height`: the height of the widget
# * `properties`: the actual widget contents
# * `annotations`: chart annotations/callouts
#
# Chart annotations are relatively simple:
# * `annotations.horizontal`: an array of annotation objects for horizontal (Y axis-based)
#   annotations
# * `annotations.vertical`: an array of annotation objects for vertical (X axis-based)
#   annotations
#
# An annotation object has `color`, `label`, and `value` properties.
#
# The `properties` object varies by the kind of widget. We document the `"widget"` type
# here:
# * `properties.metrics`: the metric(s) being displayed by this widget
# * `properties.view`: the kind of view (time series, stacked, etc.)
# * `properties.region`: the AWS region being graphed
# * `properties.title`: the display title of the widget
# * `properties.period`: the period, in seconds, being displayed (usually this is 300 - 5
#   minutes)
# * `properties.stat`: the computed statistic for the metric (e.g., average, percentile,
#   min/max)
#
# The `metrics` field is a list of JSON arrays. Roughly, the structure is as follows:
# ```
# metric ::= [
#   "<MetricNamespace>", "<MetricName>",
#   "<DimensionName_1>", "<DimensionValue_1>", ..., "<DimensionName_N>", "<DimensionValue_N>",
#   { <MetricOptions> }?
# ]
# metrics ::= [ <metric_1>, ..., <metric_N> ]
# ```
#
# CloudWatch dashboards use two special string values to indicate repetition:
# * `"."`: Repeat the single string from the prior array
# * `"..."`: Repeat the _entire_ metric from the prior array
#
# The `"."` marker is used often when a metric varies only by dimension, or shares a
# namespace. The below example reports two metrics from the same namespace:
# ```
# metrics = [
#  ["AWS/Foo", "MetricOne"],
#  [".",       "MetricTwo"],
# ]
# ```
#
# The `"..."` marker is used primarily when reporting on a different statistic for the
# same metric. The below example reports the `"SomeMetric"` metric's average and 95th
# percentile in the same widget:
# ```
# metrics = [
#   ["AWS/Foo", "SomeMetric", "SomeDimension", "AnotherDimension", { "stat": "Average" }],
#   ["...",                                                        { "stat": "p95" }    ],
# ]
# ```

locals {
  # Base JSON object structure for a dashboard widget
  dashboard-widget-base = {
    type   = "metric"
    width  = 6
    height = 6
  }

  # Base JSON object for a metrics view - almost all of the dashboard widgets we use are
  # time series line graphs, so we share the base elements here instead of repeating them
  # via copy/paste.
  dashboard-view-base = {
    view    = "timeSeries"
    stacked = false
    region  = var.aws-region
    period  = 300
  }

  # Color code: primary metric (this is the default CloudWatch blue color).
  # * Use: neutral purpose. Generally needed only to override the average color for a
  #   widget when comparing against 10th and 95th percentiles.
  dashboard-color-primary = "#1f77b4"

  # Color code: secondary metric (this is CloudWatch's orange color)
  # * Use: neutral purpose. Generally used to indicate a secondary metric (see, e.g., the
  #   overview dashboard's autoscaling group metrics) when another color is used before
  #   this metric.
  dashboard-color-secondary = "#ff7f0e"

  # Color code: healthy/"good" metric (this is CloudWatch's green color).
  # * Use: when comparing against other metrics in the same graph. This color is used
  #   primarily when reporting the 10th percentile in line charts.
  # * Other advice: When combining this and the dashboard-color-unhealthy code, make sure
  #   that the two statistics can be distinguished by something other than color alone.
  #   For example, it is a bad idea to use this color when comparing healthy to unhealthy
  #   hosts, as the values may switch positions. However, it _is_ useful when comparing
  #   percentiles, as it is guaranteed that the 10th percentile is less than or equal to
  #   the 95th percentile. (The stats can be distinguished by their order from top to
  #   bottom in the chart.)
  dashboard-color-healthy = "#2ca02c"

  # Color code: unhealthy/"bad" metric (this is CloudWatch's red color).
  # * Use: as a callout color to indicate high latency/potentially negative circumstances
  #   (for example, unhealthy hosts or high tail latency).
  # * Other advice: When showing good/bad (see, e.g., the load balancer health widget),
  #   use dashboard-color-primary as the "good" value since it plays better with red/green
  #   colorblindness.
  dashboard-color-unhealthy = "#d62728"

  # Color code: annotations (this is CloudWatch's purple color)
  # * Use: provides context for values (e.g., autoscaling threshold, or maximum number of
  #   instances in an autoscaling group)
  dashboard-color-annotation = "#9467bd"
}

# The overview dashboard is a high-level view of the entire WebCMS system. It uses some
# broad metrics to give insight into activity in both ECS and its associated resources
# (MySQL, Elasticache, and so on).
resource "aws_cloudwatch_dashboard" "overview" {
  # This dashboard has a dependency on the presence of the Drupal ECS task, so we have to
  # conditionally create it.
  count = length(aws_ecs_task_definition.drupal_task)

  dashboard_name = "WebCMS_${local.env-title}_Overview"

  dashboard_body = jsonencode({
    widgets = [
      # Column 1: ECS and Memcached activity
      # (1, 0): Drupal CPU/memory
      merge(local.dashboard-widget-base, {
        x = 1,
        y = 0,
        properties = merge(local.dashboard-view-base, {
          title = "ECS: Drupal CPU/Memory",
          stat  = "Average",
          metrics = [
            ["AWS/ECS", "CPUUtilization", "ServiceName", aws_ecs_task_definition.drupal_task[0].family, "ClusterName", aws_ecs_cluster.cluster.name],
            [".", "MemoryUtilization", ".", ".", ".", "."],
          ],
        }),
      }),

      # (1, 1): ECS overall CPU/memory
      merge(local.dashboard-widget-base, {
        x = 1,
        y = 1,
        properties = merge(local.dashboard-view-base, {
          title = "ECS: Overall CPU/Memory",
          stat  = "Average",
          metrics = [
            ["AWS/ECS", "CPUUtilization", "ClusterName", aws_ecs_cluster.cluster.name],
            [".", "MemoryUtilization", ".", "."],
          ],
        }),
      }),

      # (1, 2): Memcached get/set traffic
      merge(local.dashboard-widget-base, {
        x = 1,
        y = 2,
        properties = merge(local.dashboard-view-base, {
          title = "Memcached Get/Set",
          stat  = "Average",
          metrics = [
            ["AWS/Elasticache", "CmdGet", "CacheClusterId", aws_elasticache_cluster.cache.cluster_id],
            [".", "CmdSet", ".", "."],
          ],
        }),
      }),

      # (1, 3): Memcached hit/miss rates
      merge(local.dashboard-widget-base, {
        x = 1,
        y = 3,
        properties = merge(local.dashboard-view-base, {
          title = "Memcached Hit/Miss Rate",
          stat  = "Average",
          metrics = [
            ["AWS/Elasticache", "GetHits", "CacheClusterId", aws_elasticache_cluster.cache.cluster_id],
            [".", "GetMisses", ".", "."],
          ],
        }),
      }),

      # Column 2: Load balancer-related metrics
      # (2, 0): healthy/unhealthy target counts
      merge(local.dashboard-widget-base, {
        x = 2,
        y = 0,
        properties = merge(local.dashboard-view-base, {
          title = "Load Balancer Health",
          stat  = "Maximum",
          metrics = [
            ["AWS/ApplicationELB", "HealthyHostCount", "TargetGroup", data.aws_arn.target_group.resource, "LoadBalancer", substr(data.aws_arn.alb.resource, length("loadbalancer/"), length(data.aws_arn.alb.resource)), { stat = "Minimum" }],
            [".", "UnhealthyHostCount", ".", ".", ".", ".", { color = local.dashboard-color-unhealthy }],
          ],
        }),
      }),

      # (2, 1): response times
      merge(local.dashboard-widget-base, {
        x = 2,
        y = 1,
        properties = merge(local.dashboard-view-base, {
          title = "Load Balancer Response Times",
          stat  = "p95",
          metrics = [
            ["AWS/ApplicationELB", "TargetResponseTime", "TargetGroup", data.aws_arn.target_group.resource, "LoadBalancer", substr(data.aws_arn.alb.resource, length("loadbalancer/"), length(data.aws_arn.alb.resource)), { stat = "p10", color = local.dashboard-color-healthy }],
            ["...", { stat = "p50", color = local.dashboard-color-primary }],
            ["...", { color = local.dashboard-color-unhealthy }],
          ],
        }),
      }),

      # (2, 2): ELB error rates (these are rates that originate directly from the load
      # balancer instead of the application - e.g., mismatched host headers or load
      # balancer timeouts)
      merge(local.dashboard-widget-base, {
        x = 2,
        y = 2,
        properties = merge(local.dashboard-view-base, {
          title = "ELB 4xx/5xx Responses",
          stat  = "Sum",
          metrics = [
            ["AWS/ApplicationELB", "HTTPCode_ELB_4XX_Count", "LoadBalancer", substr(data.aws_arn.alb.resource, length("loadbalancer/"), length(data.aws_arn.alb.resource))],
            [".", "HTTPCode_ELB_5XX_Count", ".", ".", { color = local.dashboard-color-unhealthy }],
          ],
        }),
      }),

      # (2, 3): Application error rates
      merge(local.dashboard-widget-base, {
        x = 2,
        y = 3,
        properties = merge(local.dashboard-view-base, {
          title = "Application 4xx/5xx Responses",
          stat  = "Sum",
          metrics = [
            ["AWS/ApplicationELB", "HTTPCode_Target_4XX_Count", "LoadBalancer", substr(data.aws_arn.alb.resource, length("loadbalancer/"), length(data.aws_arn.alb.resource))],
            [".", "HTTPCode_Target_5XX_Count", ".", ".", { color = local.dashboard-color-unhealthy }],
          ],
        }),
      }),

      # Column 3: Database metrics
      # (3, 0): CPU
      merge(local.dashboard-widget-base, {
        x = 3,
        y = 0,
        properties = merge(local.dashboard-view-base, {
          title = "Database CPU",
          stat  = "Average",
          metrics = [
            ["AWS/RDS", "CPUUtilization", "DBClusterIdentifier", aws_rds_cluster.db.cluster_identifier]
          ],
        }),
      }),

      # (3, 1): queries
      merge(local.dashboard-widget-base, {
        x = 3,
        y = 1,
        properties = merge(local.dashboard-view-base, {
          title = "Database Queries",
          stat  = "Average",
          metrics = [
            ["AWS/RDS", "Queries", "DBClusterIdentifier", aws_rds_cluster.db.cluster_identifier],
          ],
        }),
      }),

      # (3, 2): RDS proxy connections
      merge(local.dashboard-widget-base, {
        x = 3,
        y = 2,
        properties = merge(local.dashboard-view-base, {
          title = "RDS Proxy Connections",
          stat  = "Average",
          metrics = [
            ["AWS/RDS", "ClientConnections", "ProxyName", aws_db_proxy.proxy.name],
            [".", "DatabaseConnections", ".", ".", { yAxis = "right" }],
          ],
        }),
      }),
    ],
  })
}
