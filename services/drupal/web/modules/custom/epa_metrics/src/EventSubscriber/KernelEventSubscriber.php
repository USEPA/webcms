<?php

namespace Drupal\epa_metrics\EventSubscriber;

use Drupal\epa_metrics\MetricLog;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

class KernelEventSubscriber implements EventSubscriberInterface {
  public function onKernelTerminate(PostResponseEvent $event) {
    $request = $event->getRequest();

    $log = new MetricLog(time(), 'WebCMS/Drupal', [
      'Environment' => getenv('WEBCMS_ENV_NAME'),
    ]);

    $log->putProperty('request', $request->getPathInfo());

    $log->putMetric('RequestTime', microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'], 'Seconds');
    $log->putMetric('PeakMemory', memory_get_peak_usage(), 'Bytes');

    // Record some GC statistics: these can be used to determine if we're potentially
    // seeing memory pressure.
    $stats = gc_status();

    $log->putMetric('GCRuns', $stats['runs'], 'Count');
    $log->putMetric('GCCollected', $stats['collected'], 'Bytes');

    $log->send();
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::TERMINATE => 'onKernelTerminate',
    ];
  }
}
