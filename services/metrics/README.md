# PHP-FPM Metrics Sidecar

## Table of Contents

## About

This directory contains the build for a sidecar container that exports PHP-FPM metrics. PHP-FPM's statistics are enabled via [`pm.status_path`](https://www.php.net/manual/en/install.fpm.configuration.php#pm.status-path) PHP-FPM option. The best reference for what stats are exported is the example [`www.conf`](https://github.com/php/php-src/blob/php-8.0.0/sapi/fpm/www.conf.in#L142-L161) in the PHP source tree; the link here points to PHP 8.0 but generally the exposed stats are stable between PHP releases.

This container is run as a [sidecar](https://docs.microsoft.com/en-us/azure/architecture/patterns/sidecar) container alongside the WebCMS' Drupal and nginx containers. Every 60 seconds, it runs these steps:

1. Query PHP-FPM's status endpoint.
2. Transform the JSON metrics into CloudWatch metrics (see the [Metrics Structure](#metrics-structure) section).
3. Publish metrics to CloudWatch with the [`aws cloudwatch put-metric-data`](https://awscli.amazonaws.com/v2/documentation/api/latest/reference/cloudwatch/put-metric-data.html) command.

Note that if PHP-FPM is overloaded, it's possible that the `curl` command will timeout or otherwise error. In that case, this script publishes nothing and sleeps for another minute.

## Files

- `transform.jq`: This is the `jq` transformation. The script iterates over an array of PHP-FPM metrics and transforms them into CloudWatch's expected metric data structure.
- `entrypoint.sh`: As the name suggests, this is the primary script that the container executes.

## Running

The entrypoint script requires two environment variables. It will deliberately crash on startup if they are not present:

1. `$AWS_REGION`: Used to tell the AWS CLI which region the metrics are being published in.
2. `$WEBCMS_SITE`: The name of this deployment, such as `dev`

## Metrics Structure

In order to publish metrics to CloudWatch, we need a few key pieces of information:

- `MetricName`, the name of the metric. While AWS is relatively permissive with these, we use the PascalCase convention established by AWS' own built-in metrics. (For example, the number of idle processes is reported as `ProcessesIdle`).
- `Unit`, the unit of the metric. This can be a count, a unit of time (such as seconds), or a quantity such as bytes or gigabytes.
- `Value`, the numeric value of the metric.
- `Dimensions`, an optional array of `{ Name, Value }` pairs to scope metrics. We primarily use this to scope FPM metrics to the specific WebCMS deployment (e.g., "English dev" or "Spanish production") using the environment name exposed via Terraform.

As an example, here is a sample of PHP-FPM's JSON statistics.

```json
{
  "pool": "www",
  "process manager": "dynamic",
  "start time": 1616779665,
  "start since": 1854,
  "accepted conn": 10,
  "listen queue": 0,
  "max listen queue": 0,
  "listen queue len": 511,
  "idle processes": 1,
  "active processes": 1,
  "total processes": 2,
  "max active processes": 1,
  "max children reached": 0,
  "slow requests": 0
}
```

The `jq` script will create a metric array with this structure (some elements omitted for brevity):

```json
[
  {
    "MetricName": "Age",
    "Unit": "Seconds",
    "Value": 1854,
    "Timestamp": 1616781829,
    "Dimensions": [{ "Name": "Environment", "Value": "example" }]
  },
  {
    "MetricName": "RequestsAccepted",
    "Unit": "Count",
    "Value": 10,
    "Timestamp": 1616781829,
    "Dimensions": [{ "Name": "Environment", "Value": "example" }]
  },
  {
    "MetricName": "RequestsPending",
    "Unit": "Count",
    "Value": 0,
    "Timestamp": 1616781829,
    "Dimensions": [{ "Name": "Environment", "Value": "example" }]
  }
]
```

Note that the `jq` script does not capture all metrics; some of them are either redundant or not useful. For example, we don't report the "start time" metric since CloudWatch doesn't use timestamps, and it's possible to use metric math to compute "total processes" instead of reporting it. This reduces the size of the payload we send to AWS (there is a hard limit in the API) and also cuts down on the amount of data stored in CloudWatch. CloudWatch is priced per metric, which creates an incentive to avoid redundancy.
