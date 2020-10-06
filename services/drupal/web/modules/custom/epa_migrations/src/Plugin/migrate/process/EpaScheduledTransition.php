<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Translate scheduled transition data.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_scheduled_transition
 *     source: vid
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_scheduled_transition"
 * )
 */
class EpaScheduledTransition extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The drupal_7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7Connection;

  /**
   * Constructs an EpaScheduledTransition plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $d7_database
   *   The drupal_7 database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager, Connection $d7_database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->d7Connection = $d7_database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('epa_migrations.d7_database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $epa_workflow_schedule = $this->d7Connection->select('epa_workflow_schedule', 'ews')
      ->fields('ews', ['date', 'event'])
      ->condition('ews.vid', $value)
      ->execute()
      ->fetch();

    if ($epa_workflow_schedule) {

      $moderation_state_map = [
        'alert_stakeholders' => 'published_expiring',
        'scheduled_publish' => 'published',
        'scheduled_queue_for_review' => 'published_needs_review',
        'scheduled_unpublish' => 'unpublished',
        'unpublish' => 'unpublished',
      ];

      $d8_moderation_state = $moderation_state_map[$epa_workflow_schedule->event];

      $import_date = strtotime('now');

      // Set the workflow date and formatted workflow date.
      $workflow_date = $epa_workflow_schedule->date;
      $formatted_workflow_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $workflow_date);

      // Postpone all future scheduled transitions, except for scheduled_publish
      // events and unpublish events that are the result of an expiration date.
      if ($workflow_date >= $import_date) {

        if ($epa_workflow_schedule->event == 'unpublish') {
          $expiration_date = $this->d7Connection->select('field_data_field_expiration_date', 'fdfed')
            ->fields('fdfed', ['field_expiration_date_value'])
            ->condition('fdfed.revision_id', $value)
            ->execute()
            ->fetchField();

          $expiration_date = \DateTime::createFromFormat('Y-m-d H:i:s', $expiration_date, new \DateTimeZone('America/New_York'));

          if ($expiration_date && $expiration_date->getTimestamp() == $epa_workflow_schedule->date) {
            // Don't change the unpublish date.
            return [
              [
                'moderation_state' => $d8_moderation_state,
                'value' => $formatted_workflow_date,
              ],
            ];
          }
        }

        if ($epa_workflow_schedule->event == 'scheduled_publish') {
          // Don't change the scheduled publish date.
          return [
            [
              'moderation_state' => $d8_moderation_state,
              'value' => $formatted_workflow_date,
            ],
          ];
        }
        else {
          // Postpone the scheduled transition date.
          $postponed_workflow_date = strtotime('+60 days', $workflow_date);
          $formatted_postponed_workflow_date = gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $postponed_workflow_date);
          return [
            [
              'moderation_state' => $d8_moderation_state,
              'value' => $formatted_postponed_workflow_date,
            ],
          ];
        }
      }
    }
    else {
      return [];
    }

  }

}
