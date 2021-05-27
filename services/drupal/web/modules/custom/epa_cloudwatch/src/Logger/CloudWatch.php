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
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

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
  }

  /**
   * Lazily instantiates a CloudWatchLogsClient object.
   */
  protected function getClient() {
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

    // Convert the message timestamp from seconds to milliseconds as required by AWS.
    $timestamp = $context['timestamp'] * 1000;

    // Create a JSON message object. The values here are based on what the dblog module
    // records.
    $payload = json_encode([
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
    ]);

    $this->putLogMessage($payload, $timestamp);
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
   * Send a log message to CloudWatch. This method will lazily create the log stream it
   * should send to as well as attempt to recover from sequence token issues with the log
   * stream.
   *
   * @param string $message A log message
   * @param int $timestamp The timestamp of the message
   * @param int $tries The number of retry attempts remaining
   */
  protected function putLogMessage($message, $timestamp, $tries = 3) {
    if ($tries <= 0) {
      throw new RetryExceededException('Failed to retry sending logs after 3 attempts');
    }

    $client = $this->getClient();

    $logGroup = $this->getLogGroup();
    $logStream = $this->getLogStream();

    $args = [
      'logEvents' => [
        [ 'message' => $message, 'timestamp' => $timestamp ],
      ],
      'logGroupName' => $logGroup,
      'logStreamName' => $logStream,
    ];

    // We don't have a sequence token initially. This _can_ be acceptable to AWS in some
    // circumstances (for example, when we create a new log stream).
    if (isset($this->sequenceToken)) {
      $args['sequenceToken'] = $this->sequenceToken;
    }

    try {
      $result = $client->putLogEvents($args);
    } catch (CloudWatchLogsException $e) {
      switch ($e->getAwsErrorCode()) {
        // If AWS rejected our sequence token (either because we didn't have one and
        // needed it, or someone else wrote to the log stream before we did), then fetch
        // the expected token from the exception and try again.
        case 'InvalidSequenceTokenException':
          $this->sequenceToken = $e->get('expectedSequenceToken');
          return $this->putLogMessage($message, $timestamp, $tries - 1);

        // Exceptions here are thrown if the log group or log stream don't exist. We
        // currently assume the log group will have already been created, so we simply
        // attempt to create a new log stream and try again.
        case 'ResourceNotFoundException':
          $this->createLogStream();
          return $this->putLogMessage($message, $timestamp, $tries - 1);

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
    $client = $this->getClient();
    $logGroup = $this->getLogGroup();
    $logStream = $this->getLogStream();

    try {
      $client->createLogStream([
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
    $client = $this->getClient();
    $logGroup = $this->getLogGroup();

    try {
      $client->createLogGroup([
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
