<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\epa_migrations\EpaMediaWysiwygTransformTrait;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes [[{"type":"media","fid":"1234",...}]] tokens in content.
 *
 * Based on the filter provided in media_migrations. Updated to use
 * drupal-media rather then drupal-entity tags.
 *
 * These style tokens come from media_wysiwyg module. The regex it uses to match
 * them for reference is:
 *
 * /\[\[.+?"type":"media".+?\]\]/s
 *
 * @code
 * # From this
 * [[{"type":"media","fid":"1234",...}]]
 *
 * # To this
 * <drupal-media
 *   alt="Override alt text"
 *   data-align="center"
 *   data-caption="Caption text"
 *   data-entity-type="media"
 *   data-entity-uuid="1234"
 *   data-view-mode="medium"></drupal-media>
 *
 * # or this
 * <drupal-inline-media
 * data-align="center"
 * data-entity-type="media"
 * data-entity-uuid="1234"></drupal-inline-media>
 * @endcode *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: epa_media_wysiwyg_filter
 *     source: foo
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "epa_media_wysiwyg_filter"
 * )
 */
class EpaMediaWysiwygFilter extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  use EpaMediaWysiwygTransformTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs an EpaMediaWysiwygFilter plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Extract content in case this is an array with value and format keys.
    $value['value'] ? $content = $value['value'] : $content = $value;

    $this->transformWysiwyg($content, $this->entityTypeManager);
  }

}
