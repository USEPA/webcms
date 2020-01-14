<?php

namespace Drupal\epa_workflow\Plugin\Field\FieldFormatter;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\scheduled_publish\Plugin\Field\FieldFormatter\ScheduledPublishGenericFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extend plugin implementation of the 'scheduled_publish_generic_formatter'.
 *
 * @FieldFormatter(
 *   id = "scheduled_publish_generic_formatter",
 *   label = @Translation("EPA Scheduled publish generic formatter"),
 *   field_types = {
 *     "scheduled_publish"
 *   }
 * )
 *
 * @todo Move to notifications module.
 */
class EPAScheduledPublishGenericFormatter extends ScheduledPublishGenericFormatter {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_channel_factory, EntityTypeManager $entity_type_manager, ModerationInformationInterface $moderation_information) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $logger_channel_factory, $entity_type_manager);
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    foreach ($items as $delta => $item) {
      $rawValue = $item->getValue();
      $moderationState = $rawValue['moderation_state'];
      $elements[$delta]['#markup'] = $this->parseModerationLabel($moderationState, $elements[$delta]['#markup']);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseModerationLabel(string $moderationState, string $markup) {
    $field_target_type = $this->fieldDefinition->getTargetEntityTypeId();
    $field_target_bundle = $this->fieldDefinition->getTargetBundle();
    $workflow = $this->moderationInformation->getWorkflowForEntityTypeAndBundle($field_target_type, $field_target_bundle);
    $moderation_label = $workflow->getTypePlugin()->getState($moderationState)->label();
    return str_replace('%moderation_label%', $moderation_label, $markup);
  }

}
