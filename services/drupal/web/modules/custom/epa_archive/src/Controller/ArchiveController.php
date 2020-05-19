<?php
namespace Drupal\epa_archive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;

/**
 * Provides route responses for the archive creation.
 */
class ArchiveController extends ControllerBase {

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
    //$this->logger->notice( 'Exporting %:url.', ['%url' => $url]);

    $tempnam = tempnam('temporary://', 'epa_workflow_export_');
    $export_uri = $tempnam . '_1';
    //$export_dir = \Drupal\Core\File\FileSystem::realpath($export_uri);

    $export_dir = $export_uri;
    exec("cd " . dirname($export_dir) . " && wget --execute robots=off --restrict-file-names=windows --no-host-directories --timestamping --convert-links --adjust-extension --directory-prefix=" . basename($export_dir) . " --recursive --level=1 --page-requisites -I /sites,/epafiles,/misc $url", $output, $return);

    // Bail out if we had an error during the wget call.
    if ($return != 0) {
      //$this->logger->notice('Error while exporting: %return', array('%return' => $return));

      throw new NotFoundHttpException();
    }

    $export_uri_filename = $export_uri . '.zip';
    $export_filename = \Drupal\Core\File\FileSystem::realpath($export_uri_filename);
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
      //$this->logger->notice('Could not export page at %url.  Non-200 response code received: %code', array('%url' => $url, '%code' => $response->code));
    }
    throw new NotFoundHttpException();
  }

}
