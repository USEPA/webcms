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

  /**
   * @param $entity
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute($entity = NULL)
  {
    $valid_states = ['published_expiring', 'published_day_til_expire', 'published_needs_review'];
    $content_moderation_state = ContentModerationState::loadFromModeratedEntity($entity)->get('moderation_state')->getString();
    // Return default revision, which will be published or archived.
    // Including in check for 'archived' in the case we ever add this state to the content view, this action will still work as expected.
    if (!$entity->isDefaultRevision() || $content_moderation_state == 'archived') {
      $entity_vids = \Drupal::entityTypeManager()->getStorage('node')->revisionIds($entity);
      foreach ($entity_vids as $vid) {
        if (\Drupal::entityTypeManager()->getStorage('node')->loadRevision($vid)->isPublished()) {
          $entity = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($vid);
        }
      }
    }
    if (in_array($content_moderation_state, $valid_states) && $entity->isPublished()) {
      $new_state = 'published';
      $entity->set('moderation_state', $new_state);
      if ($entity instanceof RevisionLogInterface) {
        $entity->setRevisionLogMessage('Changed moderation state to Published.');
        $entity->setRevisionUserId($this->account->id());
      }
      $entity->save();
    }

    return $this->t('The selected content formerly matching any these states: "Published needs review", Published scheduled for expiration", and "Published, one day until expiration" were republished and state set to "published".');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    return $object->access('update', $account, $return_as_object);
  }

}
