<?php

namespace Drupal\epa_node_export\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\epa_core\Utility\EpaCoreHelper;
use Drupal\node\NodeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

/**
 * Provides route responses for the archive creation.
 */
class NodeExportController extends ControllerBase {
  use StringTranslationTrait;

  /**
   * The date formatter interface.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The epa core helper service.
   *
   * @var \Drupal\epa_core\Utility\EpaCoreHelper
   */
  protected $epaCoreHelper;

  /**
   * The file system interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The http client from Guzzle.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructor for the ArchiveController class.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter interface.
   * @param \Drupal\epa_core\Utility\EpaCoreHelper $epa_core_helper
   *   The epa core helper service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system interface.
   * @param \GuzzleHttp\Client $http_client
   *   The http client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger interface.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   */
  public function __construct(DateFormatterInterface $date_formatter, EpaCoreHelper $epa_core_helper, FileSystemInterface $file_system, Client $http_client, LoggerInterface $logger, Request $request, Settings $settings) {
    $this->dateFormatter = $date_formatter;
    $this->epaCoreHelper = $epa_core_helper;
    $this->fileSystem = $file_system;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->request = $request;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('epa_core.helper'),
      $container->get('file_system'),
      $container->get('http_client'),
      $container->get('logger.factory')->get('epa_node_export'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('settings')
    );
  }

  /**
   * Builds the title for the admin node export page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return string
   *   The title string.
   */
  public function getExportAdminPageTitle(NodeInterface $node) {
    return $this->t('Export "@title"', ['@title' => $node->label()]);
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Allow access to authenticated users if this node is published.
    if ($account->isAuthenticated()) {
      $node = $this->request->attributes->get('node');
      if ($node->isPublished()) {
        return AccessResult::allowed();
      }
    }
    // Default to deny access to the route.
    return AccessResult::forbidden();
  }

  /**
   * Builds the admin node export page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   The render array for the admin page.
   */
  public function buildExportAdminPage(NodeInterface $node) {
    return [
      'export_notice' => [
        '#type' => 'markup',
        '#markup' => $this->t('<p>Click the button below to generate a zip file with a standalone copy of this page and any necessary support files.</p>
        <p><em>Do not navigate away from this page while the zip file is being generated. It will download automatically when the process is complete.</em></p>'),
      ],
      'export' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('epa_node_export.create', ['node' => $node->id()]),
        '#title' => $this->t('Generate export file'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      '#cache' => [
        'contexts' => [
          'user.roles:authenticated',
        ],
      ],
    ];
  }

  /**
   * Builds the response to return to the browser.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The zipped file for download.
   */
  public function createExportFile(NodeInterface $node) {
    global $base_secure_url;

    $url = $node->toURL('canonical', [
      'absolute' => TRUE,
      'base_url' => $this->settings->get('epa_node_export.base_url', $base_secure_url),
    ])->toString();

    try {
      $response = $this->httpClient->get($url);
      $code = $response->getStatusCode();
      if ($code == 200) {
        $tempnam = $this->fileSystem->tempnam('temporary://', 'epa_archive_');
        $export_uri = $tempnam . '_1';
        $export_dir = $this->fileSystem->realpath($export_uri);

        exec("cd " . dirname($export_dir)
          . " && wget --execute robots=off --restrict-file-names=windows --no-host-directories --timestamping --convert-links --adjust-extension --directory-prefix="
          . basename($export_dir) . " --content-on-error --no-verbose --recursive --level=1 --page-requisites -I /core,/libraries,/modules,/s3fs-css,/s3fs-js,/sites,/system,/themes,/sites,/misc -X /themes/epa_theme/pattern-lab "
          . $url . ' 2>&1', $wget_output, $wget_status);

        // Log the output of wget if we hit an error (non-zero status code).
        if (!empty($wget_status)) {
          $log_contents = implode("\n", $wget_output);
          $this->logger->notice(t('Errors occurred while exporting node %node_title. <br/><strong>System Output:</strong> <br/><pre>@wget_output</pre>', [
            '%node_title' => $node->label(),
            '@wget_output' => $log_contents,
          ]));

          // Export the log to file so it will be included in the zip file.
          file_put_contents($export_dir . '/export-error-log.txt', $log_contents);
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

              // Get real and relative path for current file.
              $filePath = $file->getRealPath();
              $relativePath = substr($filePath, strlen($export_dir) + 1);

              // Add current file to archive.
              $zip->addFile($filePath, $relativePath);
            }
          }

          // Zip archive will be created only after closing object.
          $zip->close();

          // Deliver file to the browser for download.
          if (file_exists($export_uri_filename)) {

            // Record this download.
            $machine_name = $this->epaCoreHelper->getEntityMachineNameAlias($node);
            $timestamp = $this->dateFormatter->format(time(), 'custom', 'Y-m-d_H-i');
            $headers = [
              'Content-Type' => 'application/zip',
              'Cache-Control' => 'private',
              'Content-Disposition' => 'attachment; filename="' . $machine_name . '_' . $timestamp . '.zip"',
            ];

            return new BinaryFileResponse($export_uri_filename, 200, $headers);
          }
        }
        else {
          $this->logger->notice('Error while exporting node: @title - @id', ['@title' => $node->label(), '@id' => $node->id()]);
        }
      }
      else {
        $this->logger->notice('Could not export page at @url.  Non-200 response code received: @code', ['@url' => $url, '@code' => $response->code]);
      }

    }
    catch (RequestException $e) {
      $this->logger->notice('Could not export page at @url.', ['@url' => $url]);
      watchdog_exception('notice', $e);
    }

    // If no reponse was returned, then something went wrong. Return a 404.
    throw new NotFoundHttpException();
  }

}
