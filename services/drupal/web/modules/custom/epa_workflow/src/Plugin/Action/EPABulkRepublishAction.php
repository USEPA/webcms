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
 *   label = @Translation("Republish content"),
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
    // Ensure we're acting on the default revision
    if (!$entity->isDefaultRevision()) {
      $entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->load($entity->id());
    }

    // We only want to allow re-publishing when the default revision is published but not actually in the "published" state.
    // i.e., we should be able to republish items that are in "Published, Needs Review"
    if ($entity->isPublished() && $entity->moderation_state->value !== 'published') {
      $entity->set('moderation_state', 'published');
      if ($entity instanceof RevisionLogInterface) {
        $entity->setRevisionLogMessage('Bulk re-publishing.');
        $entity->setRevisionUserId($this->account->id());
      }
      $entity->save();
      return $this->t('Content has been re-published.');
    }

    return $this->t('Only Published content that has entered the review cycle can be re-published..');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    return $object->access('update', $account, $return_as_object);
  }

}
