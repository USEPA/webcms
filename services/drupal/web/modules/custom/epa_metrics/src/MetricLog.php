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
   * The namespace in which metrics are stored.
   *
   * @var string|null
   */
  private $namespace;

  /**
   * An array of dimensions to associate with each metric in this log entry.
   *
   * @var array
   */
  private $dimensions;

  /**
   * Constructs a new log entry.
   *
   * @param number $timestamp The timestamp of this log entry. Defaults to the current time.
   * @param string $namespace The namespace of metrics in this entry.
   * @param array $dimensions Any dimensions to associate with this entry's metrics.
   */
  public function __construct($timestamp = null, $namespace = null, array $dimensions = []) {
    $this->namespace = $namespace;

    $this->packet = [
      '_aws' => [
        'LogGroupName' => '/webcms-' . getenv('WEBCMS_ENV_NAME') . '/cloudwatch-metrics',
        'Timestamp' => $timestamp ?? time(),
        'CloudWatchMetrics' => [],
      ],
    ];

    $this->dimensions = [];

    // Dimensions are top-level properties in the log entry.
    foreach ($dimensions as $name => $value) {
      $this->packet[$name] = $value;

      // Metric dimensions are a list of arrays like ["<name>"], which refers to to the
      // top-level "<name>" property in the JSON.
      // Since we don't support nested dimensions, we simply compute the list of dimension
      // names here.
      $this->dimensions[] = [$name];
    }
  }

  /**
   * Attaches metadata to this log entry. This metadata is not used in reporting, but can
   * be searched for or ingested with other CloudWatch tools.
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

    $metric = [
      'Name' => $name,
      'Unit' => $unit,
    ];

    if (isset($this->namespace)) {
      $metric['Namespace'] = $this->namespace;
    }

    // Save a few bytes in JSON output: if the array of dimensions is empty, don't include
    // the key in the object at all.
    if (!empty($this->dimensions)) {
      $metric['Dimensions'] = $this->dimensions;
    }

    $this->packet['_aws']['CloudWatchMetrics'][] = $metric;
  }

  public function send() {
    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($socket !== FALSE) {
      $data = json_encode($this->packet);
      socket_sendto($socket, $data, strlen($data), 0, '127.0.0.1', '25888');
    }
  }
}
