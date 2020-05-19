<?php
namespace Drupal\epa_archive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Node\NodeInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use \Drupal\Core\File\FileSystemInterface;
use \Drupal\Core\File\FileSystem;


/**
 * Provides route responses for the archive creation.
 */
class ArchiveController extends ControllerBase {

  /**
   * Symfony\Component\HttpFoundation\Request definition.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * \Drupal\Core\File\FileSystemInterface definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;


  /**
   * Constructor for our class.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \GuzzleHttp\Client $http_client
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   */
  public function __construct(Request $request, Client $http_client, LoggerInterface $logger, FileSystemInterface $file_system) {
    $this->request = $request;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('http_client'),
      $container->get('logger.factory')->get('epa_archive'),
      $container->get('file_system')
    );
  }

  /**
   * Returns a zip file of the parent node.
   *
   * @return array
   *   A zip file of the parent node.
   */
  public function createArchive($nid) {

    return [
      '#markup' => $zip = $this->archive_node_export($nid),
    ];
  }

  function archive_node_export($nid) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    // Bail early if the node isn't published
    if (!$node->isPublished()) {
      throw new AccessDeniedHttpException();
    }

    $url = $node->toURL()->toString();

    // docker URLs aren't working so hard coding a URL for testing.
    $url = "https://www.epa.gov";
    $this->logger->notice( 'Exporting %:url.', ['%url' => $url]);

    $method = 'GET';
    $options = [];

    try {
      $response = $this->httpClient->request($method, $url, $options);
      $code = $response->getStatusCode();
      if ($code == 200) {
        $tempnam = $this->fileSystem->tempnam('temporary://', 'epa_archive_');
        $export_uri = $tempnam . '_1';
        $export_dir = $this->fileSystem->realpath($export_uri);

        exec("cd " . dirname($export_dir) . " && wget --execute robots=off --restrict-file-names=windows --no-host-directories --timestamping --convert-links --adjust-extension --directory-prefix=" . basename($export_dir) . " --recursive --level=1 --page-requisites -I /sites,/epafiles,/misc $url", $output, $return);

        // Bail out if we had an error during the wget call.
        if ($return != 0) {
          $this->logger->notice('Error while exporting: %return', array('%return' => $return));

          throw new NotFoundHttpException();
        }

        $export_uri_filename = $export_uri . '.zip';
        $export_filename = $this->fileSystem->realpath($export_uri_filename);
        $zip = new ZipArchive();
        $res = $zip->open($export_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res === TRUE) {
          $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($export_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {

          // Skip directories (they would be added automatically)
          if (!$file->isDir()) {

            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($export_dir) + 1);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
          }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        // Deliver file
        if (file_exists($export_uri_filename)) {

          // Record this download
          $machine_name = _epa_core_get_machine_name_alias($node);
          $timestamp = \Drupal\Core\Datetime\DateFormatter::format(time(), 'custom', 'Y-m-d_H-i');
          $headers = [
            'Content-Type' => mime_header_encode('application/zip'),
            'Cache-Control' => 'private',
            'Content-Disposition' => 'attachment; filename="' . $machine_name . '_' . $timestamp . '.zip"',
          ];

          new BinaryFileResponse(export_uri_filename, 200, $headers);
        }
      }
      else {
        $this->logger->notice('Error while exporting node: %title - %id', array('%title' => $node->title, '%id' => $node->nid));
      }
      }
      else {
        $this->logger->notice('Could not export page at %url.  Non-200 response code received: %code', array('%url' => $url, '%code' => $response->code));
      }
      throw new NotFoundHttpException();
    }
    catch (RequestException $e) {
      $this->logger->notice('Could not export page at %url.' . $e );
    }
  }

}
