<?php

namespace Drupal\f1_sso\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SSOController extends ControllerBase {
  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Factory method for dependency injection container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  public function userLogin() {
    $destination = Url::fromRoute('samlauth.saml_controller_login');

    $destination_url = $this->requestStack->getCurrentRequest()->query->get('destination');

    // Remove the destination parameter to avoid it redirecting in the middle of things
    $this->requestStack->getCurrentRequest()->query->remove('destination');

    $destination->setOption('query', [
      'destination' => $destination_url,
    ]);

    return RedirectResponse::create($destination->toString());
  }
}
