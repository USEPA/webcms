<?php

namespace Drupal\epa_cloudwatch\Logger;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Credentials\Credentials;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

use Drupal\epa_cloudwatch\Exception\ConfigurationRequiredException;
use Drupal\epa_cloudwatch\Exception\RetryExceededException;

/**
 * Class to export log messages to AWS CloudWatch Logs.
 *
 * This logger is driven by a few configuration items:
 *
 * * epa_cloudwatch.region (required) - the AWS region to ship logs to.
 * * epa_cloudwatch.endpoint (optional) - overrides the AWS API endpoint. Useful for local
 *   development to communicate with a mock service like Localstack.
 * * epa_cloudwatch.log_group (required) - the CloudWatch log group to write to. Must
 *   already exist.
 * * epa_cloudwatch.log_stream (optional) - Sets the name of the log stream directly.
 *   Intended for local development (where the ECS metadata service is not available).
 */
class CloudWatch implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * The maximum number of bytes in a single PutLogEvents payload.
   *
   * Per the AWS docs:
   *
   * > The maximum batch size is 1,048,576 bytes. This size is calculated as the sum of
   * > all event messages in UTF-8, plus 26 bytes for each log event.
   */
  const MAX_BATCH_SIZE = 1048576;

  /**
   * The maximum time (in seconds) a PutLogEvents payload can span.
   *
   * Per the AWS docs:
   *
   * > A batch of log events in a single request cannot span more than 24 hours.
   * > Otherwise, the operation fails.
   */
  const MAX_BATCH_DURATION = 24 * 60 * 60;

  /**
   * Buffer of log events. This buffer is unspooled only at the termination of a request
   * (or console exit, in the case of Drush).
   *
   * @var array
   */
  protected static $log_events = [];

  /**
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Aws\CloudWatchLogs\CloudWatchLogsClient
   */
  protected $client;

  /**
   * The CloudWatch logs sequence token. This token is used to ensure log events are sent
   * in the correct order to AWS; see the putLogMessage() method for usage.
   *
   * @var string|null
   */
  protected $sequenceToken;

  /**
   * The name of the log group to write to.
   *
   * @var string|null
   */
  protected $logGroup;

  /**
   * The name of the log stream to write to.
   *
   * @var string|null
   */
  protected $logStream;

  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser) {
    $this->config = $config_factory->get('epa_cloudwatch');
    $this->parser = $parser;

    $this->createClient();
  }

  /**
   * Instantiates a CloudWatchLogsClient object.
   */
  protected function createClient() {
    if (isset($this->client)) {
      return $this->client;
    }

    // Load the region from an environment variable.
    $region = getenv('WEBCMS_S3_REGION');
    if (empty($region)) {
      throw new ConfigurationRequiredException('region');
    }
    $args = ['region' => $region];

    // Set the cloudwatch logs api version.
    $args['version'] = '2014-03-28';

    // Provide local overrides for arguments that would normally be loaded from
    // the AWS environment.
    $endpoint = $this->config->get('endpoint');
    if (!empty($endpoint)) {
      $args['endpoint'] = $endpoint;
    }

    $credentials = $this->config->get('credentials');
    if (!empty($credentials)) {
      $args['credentials'] = new Credentials($credentials['access_key'], $credentials['secret_key']);
    }

    $this->client = new CloudWatchLogsClient($args);
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    // To save space in log messages (CloudWatch Logs imposes a maximum payload size),
    // remove any backtraces.
    unset($context['backtrace']);

    // Create a JSON message object. The values here are based on what the dblog module
    // records.
    $payload = [
      'uid' => $context['uid'],
      'type' => mb_substr($context['channel'], 0, 64),
      'message' => $message,
      'variables' => $this->parser->parseMessagePlaceholders($message, $context),
      'severity' => $level,
      'link' => $context['link'],
      'location' => $context['request_uri'],
      'referer' => $context['referer'],
      'hostname' => mb_substr($context['ip'], 0, 128),
      'timestamp' => $context['timestamp'],
    ];

    // Buffer the log event, but don't actually send. Instead, we wait until the end of
    // the request, when the event subscriber calls flushLogEvents().
    self::$log_events[] = $payload;
  }

  /**
   * Determine the CloudWatch log stream from configuration.
   *
   * @return boolean TRUE if a log stream was set in configuration and FALSE otherwise
   */
  protected function setLogStreamFromConfig() {
    $logStream = $this->config->get('log_stream');
    if (empty($logStream)) {
      return FALSE;
    }

    $this->logStream = $logStream;
    return TRUE;
  }

  /**
   * Determine the CloudWatch log stream from ECS container metadata.
   *
   * This relies on the presence of the $ECS_CONTAINER_METADATA_URI_V4 environment
   * variable and container metadata being available at the referenced endpoint.
   *
   * @see https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-metadata-endpoint-v4.html
   *
   * @return boolean TRUE if a log stream name could be determined and FALSE otherwise
   */
  protected function setLogStreamFromContainerMetadata() {
    // First, find the endpoint in the container environment
    $endpoint = getenv('ECS_CONTAINER_METADATA_URI_V4');
    if (empty($endpoint)) {
      return FALSE;
    }

    // Next, load the value from the endpoint
    $json = file_get_contents($endpoint);
    if (empty($json)) {
      return FALSE;
    }

    // Use the Docker container ID as the stream name, same as the ECS logs agent.
    $metadata = json_decode($json, FALSE, 512, JSON_THROW_ON_ERROR);

    $this->logStream = $metadata->DockerId;
    return TRUE;
  }

  /**
   * Determines the log stream name to use.
   *
   * @return string The CloudWatch log stream name
   */
  protected function getLogStream() {
    if (isset($this->logStream)) {
      return $this->logStream;
    }

    if ($this->setLogStreamFromConfig()) {
      return $this->logStream;
    }

    if ($this->setLogStreamFromContainerMetadata()) {
      return $this->logStream;
    }

    // If we can't determine a log stream name, throw an exception.
    throw new \Exception('Unable to determine log stream name.');
  }

  /**
   * Determines the log group to use.
   *
   * @return string The CloudWatch log group name
   */
  protected function getLogGroup() {
    if (isset($this->logGroup)) {
      return $this->logGroup;
    }

    // As with the AWS region, raise an error if we don't have a log group.
    $logGroup = getenv('WEBCMS_LOG_GROUP');
    if (empty($logGroup)) {
      throw new ConfigurationRequiredException('log_group');
    }

    $this->logGroup = $logGroup;
    return $logGroup;
  }

  /**
   * Empties the internal buffer of log events and sends them all to CloudWatch Logs.
   */
  public function flushLogEvents() {
    // Sometimes we get throttled by AWS when pushing logs up to the cloud.
    // This ensures the time we spend waiting on the API doesn't get counted in
    // our New Relic stats since it's not slowness the user experiences.
    if (extension_loaded('newrelic')) { // Ensure PHP agent is available
      newrelic_end_transaction();
    }
    $all_events = self::$log_events;
    if (empty($all_events)) {
      return;
    }

    // The current batch of log events to send to CloudWatch
    $log_events = [];

    // Count the size (in bytes) of this batch of events.
    $batch_size = 0;

    // Calculate the start time (in seconds) of this batch of events.
    $batch_start = $all_events[0]['timestamp'];

    foreach ($all_events as $event) {
      // Get the timestamp (in seconds) of the event
      $event_time = $event['timestamp'];

      // Encode the event as a JSON string and calculate the size
      $message = json_encode($event);
      $event_size = strlen($message) + 26;

      // This is the structure expected by the PutLogEvents API call. We have to convert
      // from the seconds-based timestamp to AWS' milliseconds-based timestamp, hence
      // the arithmetic.
      $log_event = [
        'message' => $message,
        'timestamp' => $event_time * 1000,
      ];

      // Compute the dimensions (byte size and overall timespan) of the events.
      $total_size = $batch_size + $event_size;
      $total_time = $event_time - $batch_start;

      if ($total_size > self::MAX_BATCH_SIZE || $total_time > self::MAX_BATCH_DURATION) {
        // If the new dimensions would exceed CloudWatch's limits, send the current batch
        // of log events and reset the list to the next message.
        $this->putLogEvents($log_events);

        $log_events = [$log_event];
        $batch_size = $event_size;
        $batch_start = $event_time;
      } else {
        // Otherwise, continue accumulating log events. $batch_size is incremented but
        // not $batch_start, since that holds the earliest (not latest) timestamp.
        $log_events[] = $log_event;
        $batch_size = $total_size;
      }
    }

    // If we have leftover events from our loop, send them here.
    if (!empty($log_events)) {
      $this->putLogEvents($log_events);
    }

    // Reset the shared log events buffer.
    self::$log_events = [];
  }

  /**
   * Send a log message to CloudWatch. This method will lazily create the log stream it
   * should send to as well as attempt to recover from sequence token issues with the log
   * stream.
   *
   * @param array $log_events A batch of log events
   * @param int $tries The number of retry attempts remaining
   */
  protected function putLogEvents(array $log_events, $tries = 3) {
    if ($tries <= 0) {
      throw new RetryExceededException('Failed to retry sending logs after 3 attempts');
    }

    $logGroup = $this->getLogGroup();
    $logStream = $this->getLogStream();

    $args = [
      'logEvents' => $log_events,
      'logGroupName' => $logGroup,
      'logStreamName' => $logStream,
    ];

    // We don't have a sequence token initially. This _can_ be acceptable to AWS in some
    // circumstances (for example, when we create a new log stream).
    if (isset($this->sequenceToken)) {
      $args['sequenceToken'] = $this->sequenceToken;
    }

    try {
      $result = $this->client->putLogEvents($args);
    } catch (CloudWatchLogsException $e) {
      switch ($e->getAwsErrorCode()) {
        // If AWS rejected our sequence token (either because we didn't have one and
        // needed it, or someone else wrote to the log stream before we did), then fetch
        // the expected token from the exception and try again.
        case 'InvalidSequenceTokenException':
          $this->sequenceToken = $e->get('expectedSequenceToken');
          return $this->putLogEvents($log_events, $tries - 1);

        // Exceptions here are thrown if the log group or log stream don't exist. We
        // currently assume the log group will have already been created, so we simply
        // attempt to create a new log stream and try again.
        case 'ResourceNotFoundException':
          $this->createLogStream();
          return $this->putLogEvents($log_events, $tries - 1);

        // Re-throw any other exceptions we encounter.
        default:
          throw $e;
      }
    }

    // Save the next sequence token.
    $this->sequenceToken = $result['sequenceToken'];
  }

  /**
   * Creates a log stream for CloudWatch Logs.
   */
  protected function createLogStream() {
    $logGroup = $this->getLogGroup();
    $logStream = $this->getLogStream();

    try {
      $this->client->createLogStream([
        'logGroupName' => $logGroup,
        'logStreamName' => $logStream,
      ]);
    }
    catch (CloudWatchLogsException $e) {
      switch ($e->getAwsErrorCode()) {
        // If the log group does not exist, we will get a resource not found
        // exception. We will create the log group, then create the log stream.
        // This scenario is only likely to occur when running with localstack in
        // a local development environment.
        case 'ResourceNotFoundException':
          $this->createLogGroup();
          $this->createLogStream();
          break;

        // If we were not the only request attempting to request this log
        // stream, then AWS will inform us that it already exists. We ignore
        // that error, but re-throw all others - this gives us safe logic for
        // lazy log stream creation while not shadowing other problems (such as
        // bad IAM permissions).
        case 'ResourceAlreadyExistsException':
          break;

        default:
          throw $e;
      }
    }
  }

  /**
   * Creates a log group for CloudWatch Logs.
   */
  protected function createLogGroup() {
    $logGroup = $this->getLogGroup();

    try {
      $this->client->createLogGroup([
        'logGroupName' => $logGroup,
      ]);
    }
    catch (CloudWatchLogsException $e) {
      // If we were not the only request attempting to create this log group,
      // then AWS will inform us that it already exists. We ignore that error,
      // but re-throw all others - this gives us safe logic for lazy log group
      // creation while not shadowing other problems (such as bad IAM
      // permissions).
      if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
        throw $e;
      }
    }
  }

}
