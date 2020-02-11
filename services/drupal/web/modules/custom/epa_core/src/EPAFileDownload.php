<?php

namespace Drupal\epa_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Helper class for file downloads.
 */
class EPAFileDownload {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EPAFileDownload Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, StreamWrapperManagerInterface $stream_wrapper_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Function to test if file should be seen anonymously.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   */
  public function privateFileCheck(Request $request, $scheme) {
    $user = $this->currentUser;
    if ($user->isAnonymous()) {
      $entity_type_manager = $this->entityTypeManager;
      $target = $request->query->get('file');
      $uri = $scheme . '://' . $target;

      if ($this->streamWrapperManager->isValidScheme($scheme) && file_exists($uri)) {
        // We should only get one result.
        $files = $entity_type_manager->getStorage('file')->loadByProperties(['uri' => $uri]);
        $file = reset($files);
        $media = $entity_type_manager->getStorage('media')->load($file->id());

        // Throw 404 if file accessibility is true else serve the file.
        if (isset($media) && $media->hasField('field_file_accessibility') && !empty($media->field_file_accessibility->value)) {
          throw new NotFoundHttpException();
        }
      }
    }
  }

}
