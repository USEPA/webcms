<?php

/**
 * Bulk republish the current published revision for
 * content in these states:
 * Published, needs review
 * Published, scheduled for expiration
 * Published, one day until expiration
 *
 * @Action(
 *   id = "epa_workflow_bulk_republish",
 *   label = @Translation("Bulk Republish"),
 *   type = "",
 *   confirm = TRUE,
 *   requirements = {
 *   "_permission" = "administer nodes",
 *     "_custom_access" = TRUE,
 *   },
 * )
 */


namespace Drupal\epa_workflow\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\Entity\ContentModerationState;

/**
 * Action description.
 *
 * @Action(
 *   id = "epa_workflow_bulk_republish",
 *   label = @Translation("Bulk Republish"),
 *   type = ""
 * )
 */
class EPABulkRepublishAction extends ViewsBulkOperationsActionBase
{

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL)
  {
    // Do some processing..
    $valid_states = ['published_expiring', 'published_day_til_expire', 'published_needs_review', ];
    $content_moderation_state = ContentModerationState::loadFromModeratedEntity($entity)->get('moderation_state')->getString();

    // Don't return anything for a default completion message, otherwise return translatable markup.
    return $this->t('The selected content in "Published needs review", Published scheduled for expiration", and "Published, one day until expiration" states were republished.');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    // If certain fields are updated, access should be checked against them as well.
    // @see Drupal\Core\Field\FieldUpdateActionBase::access().
    return $object->access('update', $account, $return_as_object);
  }

}
