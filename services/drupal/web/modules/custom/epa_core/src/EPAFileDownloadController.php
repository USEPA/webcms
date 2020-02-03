<?php

namespace Drupal\epa_core;

use Drupal\system\FileDownloadController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Extend system FileDownloadController.
 */
class EPAFileDownloadController extends FileDownloadController {

  /**
   * {@inheritdoc}
   *
   * If current user is anonymous, make a decision about whether to
   * serve file based on the associated media.
   */
  public function download(Request $request, $scheme = 'private') {
    $user = $this->currentUser();
    if ($user->isAnonymous()) {
      $entity_type_manager = $this->entityTypeManager();
      $target = $request->query->get('file');
      $uri = $scheme . '://' . $target;

      if ($this->streamWrapperManager->isValidScheme($scheme) && file_exists($uri)) {
        // We should only get one result.
        $files = $entity_type_manager->getStorage('file')->loadByProperties(['uri' => $uri]);
        $file = reset($files);
        $media = $entity_type_manager->getStorage('media')->load($file->id());

        // Throw 404 if file accessibility is true else serve the file.
        if ($media->hasField('field_file_accessibility') && !empty($media->field_file_accessibility->value)) {
          throw new NotFoundHttpException();
        }
      }
    }
    $response = parent::download($request, $scheme);
    return $response;
  }

}
