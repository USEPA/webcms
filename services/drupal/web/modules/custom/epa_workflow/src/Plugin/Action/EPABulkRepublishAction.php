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

use Drupal\Core\Entity\EntityBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\Core\Entity\RevisionLogInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Action description.
 *
 * @Action(
 *   id = "epa_workflow_bulk_republish",
 *   label = @Translation("Bulk Republish"),
 *   type = ""
 * )
 */
class EPABulkRepublishAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface
{

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * ConvertEnquiryToBooking constructor.
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param AccountInterface $account
   */
  public function __construct(
    array            $configuration,
                     $plugin_id,
                     $plugin_definition,
    AccountInterface $account
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  public function execute($entity = NULL)
  {
    // TODO retrieve node (latest revision) get current revision from that then get the moderation state from that.
    // Do some processing..
    $valid_states = ['published_expiring', 'published_day_til_expire', 'published_needs_review'];
    if (!$entity->isDefaultRevision()) {
      // Should return default revision, which will be published or archived, and I believe we don't have archived content on the site, but double check this in the db.
      // TODO this is throwing an error I think on 'default'
      $entity = EntityBase::load($entity);
    }
    $content_moderation_state = ContentModerationState::loadFromModeratedEntity($entity)->get('moderation_state')->getString();
    // TODO check for 'archived' state then handle appropriately if it exists.
    if (in_array($content_moderation_state, $valid_states)) {
      $new_state = 'published';
      $entity->set('moderation_state', $new_state);
      if ($entity instanceof RevisionLogInterface) {
        $entity->setRevisionLogMessage('Changed moderation state to Published.');
        $entity->setRevisionUserId($this->account->id());
      }
      $entity->save();
    }
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
