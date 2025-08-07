<?php
namespace Drupal\media_inline_embed;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\media_library\MediaLibraryEditorOpener;
use Drupal\media_library\MediaLibraryState;

class MediaInlineEmbedEditorOpener extends MediaLibraryEditorOpener {
  /**
   * {@inheritdoc}
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    $filter_format_id = $state->getOpenerParameters()['filter_format_id'];
    $filter_format = $this->filterStorage->load($filter_format_id);
    if (empty($filter_format)) {
      return AccessResult::forbidden()
        ->addCacheTags(['filter_format_list'])
        ->setReason("The text format '$filter_format_id' could not be loaded.");
    }
    $filters = $filter_format->filters();
    return $filter_format->access('use', $account, TRUE)
      ->andIf(AccessResult::allowedIf($filters->has('media_inline_embed') && $filters->get('media_inline_embed')->status === TRUE));
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids) {
    $selected_media = $this->mediaStorage->load(reset($selected_ids));

    $response = new AjaxResponse();
    $values = [
      'attributes' => [
        'data-entity-type' => 'media',
        'data-entity-uuid' => $selected_media->uuid(),
      ],
    ];

    // Set 'data-view-mode' attribute if a default view mode is configured
    // for the filter format.
    $filter_format = $this->filterStorage->load($state->getOpenerParameters()['filter_format_id']);
    if ($filter_format && $filter_format->filters('media_inline_embed')) {
      $filter = $filter_format->filters('media_inline_embed');
      $default_view_mode = $filter->settings['default_view_mode'];
      if ($default_view_mode) {
        $values['attributes']['data-view-mode'] = $default_view_mode;
      }
    }

    $response->addCommand(new EditorDialogSave($values));

    return $response;
  }
}
