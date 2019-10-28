<?php

/**
 * @file
 * Contains \Drupal\image_popup\Controller\AddDefinitions.
 */

namespace Drupal\epa_wysiwyg\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\epa_wysiwyg\Plugin\CKEditorPlugin\EPAAddDefinitions;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AddDefinitionsController.
 */
class AddDefinitionsController extends ControllerBase {

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructor for our class.
   */
  public function __construct(Client $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Prints available terms.
   */
  public function getTerms() {
    // Check that required parameter is present.
    if (!isset($_POST['text'])) {
      return;
    }

    $url = Url::fromUri(EPAAddDefinitions::SERVICE_ENDPOINT, ['query' => ['callback' => 'CKEDITOR.dictionaryCallback']]);
    $post_data = UrlHelper::buildQuery(['text' => $_POST['text']]);

    try {
      $response = $this->httpClient()->post($url, $post_data);
      $data = $response->getBody();
    }
    catch (RequestException $e) {
      watchdog_exception('epa_wysiwyg', $e->getMessage());
    }

    return new Response($data, 200);
  }

}
