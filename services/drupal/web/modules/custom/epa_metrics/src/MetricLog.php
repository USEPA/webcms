<?php

namespace Drupal\epa_metrics;

/**
 * Small wrapper class for the CloudWatch Embedded Metric Format. A single MetricLog
 * instance represents one log entry in CloudWatch, from which metrics are extracted for
 * reporting purposes.
 *
 * @see https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/CloudWatch_Embedded_Metric_Format_Generation.html
 */
class MetricLog {
  /**
   * The packet of data we will be sending to the CloudWatch agent.
   *
   * @var array
   */
  private $packet;

  /**
   * The metric data we will be sending.
   *
   * @var array
   */
  private $metrics;

  /**
   * The time of this log entry
   *
   * @var float
   */
  private $timestamp;

  /**
   * The namespace in which to add metrics.
   *
   * @var string|null
   */
  private $namespace;

  /**
   * The dimensions associated with this metric.
   *
   * Dimensions in the embedded metric format are structured as a list of arrays. Each
   * array contains one or more strings representing properties attached to the metric
   * log.
   *
   * @var array
   */
  private $dimensions;

  /**
   * Constructs a new log entry.
   *
   * @param float $timestamp The timestamp of this log entry, in milliseconds. Defaults to
   * the current time.
   * @param string $namespace The namespace of metrics in this entry.
   * @param array $dimensions Any dimensions to associate with this entry's metrics.
   * Remember to call `putProperty` on any dimension names here.
   */
  public function __construct($timestamp = null, $namespace = null, array $dimensions = []) {
    $this->timestamp = $timestamp ?? floor(microtime(TRUE));
    $this->namespace = $namespace;
    $this->dimensions = $dimensions;

    $this->packet = [];
    $this->metrics = [];
  }

  /**
   * Attaches data to this log entry. If not referenced in a dimension or metric, the data
   * is not used by CloudWatch directly but can still be searched.
   *
   * @param string $name The name of the property
   * @param mixed $value The value of the property
   */
  public function putProperty(string $name, $value) {
    $this->packet[$name] = $value;
  }

  /**
   * Puts a measurement into this log entry.
   *
   * @param string $name The name of the metric
   * @param mixed $value The value of the metric
   * @param string $unit The unit of this metric (e.g., Count, Bytes, or Milliseconds)
   */
  public function putMetric(string $name, $value, string $unit) {
    // Like dimensions and properties, metrics are top-level JSON elements.
    $this->packet[$name] = $value;

    $this->metrics[] = [
      'Name' => $name,
      'Unit' => $unit,
    ];
  }

  public function send() {
    $metrics = ['Metrics' => $this->metrics];

    if (!empty($this->namespace)) {
      $metrics['Namespace'] = $this->namespace;
    }

    if (!empty($this->dimensions)) {
      $metrics['Dimensions'] = $this->dimensions;
    }

    $this->packet['_aws'] = [
      'LogGroupName' => '/webcms-' . getenv('WEBCMS_ENV_NAME') . '/cloudwatch-metrics',
      'Timestamp' => $this->timestamp,
      'CloudWatchMetrics' => [$metrics],
    ];

    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($socket !== FALSE) {
      $data = json_encode($this->packet);
      socket_sendto($socket, $data, strlen($data), 0, '127.0.0.1', '25888');
    }
  }
}
