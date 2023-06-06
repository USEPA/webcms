<?php

namespace Drupal\epa_wysiwyg\Controller;

use Drupal\ckeditor5\Controller\CKEditor5MediaController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function GuzzleHttp\json_decode;

class EpaWysiwygCKEditor5MediaController extends CKEditor5MediaController {
  public function mediaEntityMetadata(Request $request) {
    $response =  parent::mediaEntityMetadata($request);
    $data = $response->getContent();
    $data = json_decode($data, TRUE);
    $uuid = $request->query->get('uuid');

    // Access is enforced on route level.
    // @see \Drupal\ckeditor5\Controller\CKEditor5MediaController::access().
    if (!$media = $this->entityRepository->loadEntityByUuid('media', $uuid)) {
      throw new NotFoundHttpException();
    }

    // Adding the URL string to edit media items to metadata.
    $data['edit_url'] = $media->toUrl('edit-form')->toString();

    // Adding file URL to image source metadata.
    $image_field = $this->getMediaImageSourceFieldName($media);
    if ($image_field) {
      $file_id = $media->{$image_field}->target_id;
      if ($file_id) {
        $file = $this->entityTypeManager()
          ->getStorage('file')
          ->load($file_id);
        $data['imageSourceMetadata']['filepath'] = $file->createFileUrl();
      }
    }

    $response->setData($data);

    return $response;
  }

}
