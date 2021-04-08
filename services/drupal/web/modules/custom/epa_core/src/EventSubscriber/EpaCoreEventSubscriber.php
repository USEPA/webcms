<?php

namespace Drupal\epa_core\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subscribing an event.
 */
class EpaCoreEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Request object.
   *
   * @var Request
   */
  protected $request;

  /**
   * Response object.
   *
   * @var \Symfony\Component\HttpFoundation\Response
   */
  protected $response;


  /**
   * Constructs an EpaCoreEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('seckit.settings');
  }

  /**
   * Executes actions on the request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Event Response Object.
   */
  public function onKernelRequest(GetResponseEvent $event) {
    $this->request = $event->getRequest();

    // Execute necessary functions.
    if ($this->config->get('seckit_csrf.origin')) {
      $this->seckitOrigin($event);
    }
  }

  /**
   * Aborts HTTP request upon invalid 'Origin' HTTP request header.
   *
   * This has been added to augment the existing behavior of the Seckit module.
   * Seckit fails to reject GET requests with an "invalid" Origin header set.
   * This is insufficiently-restrictive for the purposes of EPA's app scan tool.
   * Here we add the rejection of HEAD and GET requests if a query parameter is
   * set (since that could imply this response is a result of a form submission).
   */
  public function seckitOrigin($event) {
    // Allow requests without an 'Origin' header, or with a 'null' origin.
    $origin = $this->request->headers->get('Origin');
    if (!$origin || $origin === 'null') {
      return;
    }

    // Only operate on GET and HEAD requests.
    $method = $this->request->getMethod();
    if (!in_array($method, ['GET', 'HEAD'], TRUE)) {
      return;
    }

    // Only operate on requests where there are query parameters.
    if (empty($_GET)) {
      return;
    }

    // Allow requests from whitelisted Origins.
    global $base_root;

    $whitelist = explode(',', $this->config->get('seckit_csrf.origin_whitelist'));
    // Default origin is always allowed.
    $whitelist[] = $base_root;
    $whitelist = array_values(array_filter(array_map('trim', $whitelist)));
    if (in_array($origin, $whitelist, TRUE)) {
      return;
      // n.b. RFC 6454 allows Origins to have more than one value (each
      // separated by a single space).  All values must be on the whitelist
      // (order is not important).  We intentionally do not handle this
      // because the feature has been confirmed as a design mistake which
      // user agents do not utilise in practice.  For details, see
      // http://lists.w3.org/Archives/Public/www-archive/2012Jun/0001.html
      // and https://www.drupal.org/node/2406075
    }
    // The Origin is invalid, so we deny the request.
    $event->setResponse(new Response($this->t('Access denied'), Response::HTTP_FORBIDDEN));
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 100];
    return $events;
  }

}
