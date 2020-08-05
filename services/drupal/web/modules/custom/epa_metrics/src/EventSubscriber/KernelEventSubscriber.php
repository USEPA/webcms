<?php

namespace Drupal\epa_metrics\EventSubscriber;

use Liuggio\StatsdClient\Sender\SocketSender;
use Liuggio\StatsdClient\Service\StatsdService;
use Liuggio\StatsdClient\StatsdClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

class KernelEventSubscriber implements EventSubscriberInterface {
  /**
   * @var StatsdService
   */
  private $service;

  public function __construct() {
    $sender = new SocketSender('127.0.0.1', 8125, 'udp');
    $client = new StatsdClient($sender);
    $this->service = new StatsdService($client);
  }

  public function onKernelTerminate(PostResponseEvent $event) {
    // Record the amount of time we spent in PHP
    $duration = microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'];
    $this->service->timing('Drupal.RequestTime', $duration * 1000);

    // Record peak memory usage
    $memory = memory_get_peak_usage();
    $this->service->gauge('Drupal.PeakMemory', $memory);

    $this->service->flush();
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::TERMINATE => 'onKernelTerminate',
    ];
  }
}
