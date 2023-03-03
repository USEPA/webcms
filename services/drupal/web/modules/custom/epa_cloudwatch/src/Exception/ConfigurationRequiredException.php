<?php

namespace Drupal\epa_cloudwatch\Exception;

/**
 *
 */
class ConfigurationRequiredException extends \Exception {

  /**
   *
   */
  public function __construct(string $item, ?\Throwable $previous = NULL) {
    $message = 'The configuration item epa_cloudwatch.' . $item . ' is required.';
    parent::__construct($message, NULL, $previous);
  }

}
