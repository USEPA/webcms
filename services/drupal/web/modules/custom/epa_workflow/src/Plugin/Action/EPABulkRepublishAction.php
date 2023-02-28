<?php

namespace Drupal\epa_workflow\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\RevisionLogInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Action description.
 * Bulk republish the current published revision for
 * content in these states:
 * Published, needs review
 * Published, scheduled for expiration
 * Published, one day until expiration
 *
 * @Action(
 *   id = "epa_workflow_bulk_republish",
 *   label = @Translation("Republish"),
 *   type = "",
 *   confirm = TRUE,
 *   requirements = {
 *     "_permission" = "execute the bulk republish action",
 *   }
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
   * The moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Moderation state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected $validator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ConvertEnquiryToBooking constructor.
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param AccountInterface $account
   * @param ModerationInformationInterface $moderation_info
   * @param $validator
   */
  public function __construct(
    array            $configuration,
                     $plugin_id,
                     $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $account,
    ModerationInformationInterface $moderation_info,
    StateTransitionValidationInterface $validator
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->moderationInfo = $moderation_info;
    $this->validator = $validator;
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
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation')
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
    // Ensure we're acting on the default revision
    if (!$entity->isDefaultRevision()) {
      $entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->load($entity->id());
    }
      $entity->set('moderation_state', 'published');
      if ($entity instanceof RevisionLogInterface) {
        $entity->setRevisionLogMessage('Bulk re-publishing.');
        $entity->setRevisionUserId($this->account->id());
        $entity->setRevisionCreationTime(\Drupal::time()
          ->getRequestTime());
      }
      $entity->save();
      return $this->t('Content has been re-published.');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object,  AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object->isDefaultRevision()) {
      $object = \Drupal::entityTypeManager()->getStorage($object->getEntityTypeId())->load($object->id());
    }
    if (!$object || !$object instanceof ContentEntityInterface) {
      $result = AccessResult::forbidden('Not a valid entity.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    $object = $this->loadLatestRevision($object);
    // Let content moderation do its job. See content_moderation_entity_access()
    // for more details.
    $access = $object->access('update', $account, TRUE);

    $to_state_id = 'published';
    $workflow = $this->moderationInfo->getWorkflowForEntity($object);
    $from_state = $workflow->getTypePlugin()->getState($object->moderation_state->value);
    // We only want to allow re-publishing when the default revision is published but not actually in the "published" state.
    // i.e., we should be able to republish items that are in "Published, Needs Review"
    if ($object->isPublished() && $from_state !== 'published') {
      // Make sure we can make the transition.
      if ($from_state->canTransitionTo($to_state_id)) {
        $to_state = $workflow->getTypePlugin()->getState($to_state_id);
        // Let the validator do the access check.
        // This not only checks if the transition is valid but
        // also checks if the user have permission to do
        // the transition. While it does repeat some of the access checks
        // this validator can be overridden by groups.
        $valid = $this->validator->isTransitionValid($workflow, $from_state, $to_state, $account, $object);
        if ($valid) {
          // The user has permission to
          // perform the transition. Set to allow if they also have update
          // access.
          $result = AccessResult::allowed()->andIf($access);
        } else {
          // The user does not have permission to perform the
          // transition. In keeping consistent with the previous
          // code return neutral.
          $result = AccessResult::neutral()->andIf($access);
        }
      } else {
        $result = AccessResult::forbidden('No valid transition found.');
      }
    }
    else {
      $result = AccessResult::forbidden('Only Published content that has entered the review cycle can be re-published..');
    }

    $result->addCacheableDependency($workflow);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Loads the latest revision of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The latest revision of content entity.
   */
  protected function loadLatestRevision(ContentEntityInterface $entity) {
    $entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $original_entity =
      ($revision_id = $entity_storage->getLatestTranslationAffectedRevisionId($entity->id(), $entity->language()->getId())) ?
        $entity_storage->loadRevision($revision_id)->getTranslation($entity->language()->getId()) :
        NULL;
    return $original_entity;
  }


}
