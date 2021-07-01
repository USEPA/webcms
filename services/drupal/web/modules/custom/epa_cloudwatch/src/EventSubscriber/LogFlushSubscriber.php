<?php

namespace Drupal\epa_cloudwatch\EventSubscriber;

use Drupal\epa_cloudwatch\Logger\CloudWatch;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LogFlushSubscriber implements EventSubscriberInterface {
  /**
   * @var \Drupal\epa_cloudwatch\Logger\CloudWatch
   */
  protected $logger;

  public function __construct(CloudWatch $logger) {
    $this->logger = $logger;
  }

  public function onConsoleTerminate(ConsoleTerminateEvent $event) {
    $this->logger->flushLogEvents();
  }

  public function onKernelTerminate(TerminateEvent $event) {
    $this->logger->flushLogEvents();
  }

  public static function getSubscribedEvents() {
    return [
      ConsoleEvents::TERMINATE => 'onConsoleTerminate',
      KernelEvents::TERMINATE => 'onKernelTerminate',
    ];
  }
}
