<?php

namespace Drupal\epa_wysiwyg\Controller;

use Drupal\ckeditor5\Controller\CKEditor5MediaController;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\MediaInterface;
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
    $file_field = $this->getMediaSourceFieldName($media);
    if ($file_field) {
      $file_id = $media->{$file_field}->target_id;
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

  /**
   * Gets the name of a media item's source field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item being embedded.
   *
   * @return string|null
   *   The name of the source field configured for the media item, or
   *   NULL if the source field is not an image field.
   */
  protected function getMediaSourceFieldName(MediaInterface $media) {
    $field_definition = $media->getSource()
      ->getSourceFieldDefinition($media->bundle->entity);
    $item_class = $field_definition->getItemDefinition()->getClass();
    if (is_a($item_class, FileItem::class, TRUE)) {
      return $field_definition->getName();
    }
    return NULL;
  }

}
