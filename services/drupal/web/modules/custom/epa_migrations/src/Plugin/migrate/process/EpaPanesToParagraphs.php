<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\epa_migrations\EpaCoreHtmlPaneToParagraph;
use Drupal\epa_migrations\EpaCoreListPaneToParagraph;
use Drupal\epa_migrations\EpaFieldablePanelsPaneToParagraph;
use Drupal\epa_migrations\EpaNodeContentPaneToParagraph;
use Drupal\epa_migrations\EpaTransformParagraphsTrait;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Given a set of panes, returns paragraph entity references.
 *
 * @MigrateProcessPlugin(
 *   id = "epa_panes_to_paragraphs"
 * )
 *
 * To convert a an array of panes to paragraph entity ids for a field, do the
 * following:
 *
 * @code
 * field_paragraphs:
 *   plugin: epa_panes_to_paragraphs
 *   source: main_col_panes
 * @endcode
 */
class EpaPanesToParagraphs extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  // Helper function to transform paragraphs to panes.
  use EpaTransformParagraphsTrait;

  /**
   * The service to transform node_content panes.
   *
   * @var \Drupal\epa_migrations\EpaNodeContentPaneToParagraph
   */
  protected $epaNodeContentTransformer;

  /**
   * The service to transform epa_core_html_pane panes.
   *
   * @var \Drupal\epa_migrations\EpaCoreHtmlPaneToParagraph
   */
  protected $epaCoreHtmlPaneTransformer;

  /**
   * The service to transform epa_core_*_list_pane panes.
   *
   * @var \Drupal\epa_migrations\EpaCoreListPaneToParagraph
   */
  protected $epaCoreListPaneTransformer;

  /**
   * The service to transform fieldable_panels_pane panes.
   *
   * @var \Drupal\epa_migrations\EpaFieldablePanelsPaneToParagraph
   */
  protected $epaFieldablePanelsPaneTransformer;

  /**
   * Construct an epa_panes_to_paragraphs process plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\epa_migrations\EpaNodeContentPaneToParagraph $epa_node_content_transformer
   *   The service to transform node_content panes.
   * @param \Drupal\epa_migrations\EpaCoreHtmlPaneToParagraph $epa_core_html_pane_transformer
   *   The service to transform epa_core_html_pane panes.
   * @param \Drupal\epa_migrations\EpaCoreListPaneToParagraph $epa_core_list_pane_transformer
   *   The service to transform epa_core_*_list_pane panes.
   * @param \Drupal\epa_migrations\EpaFieldablePanelsPaneToParagraph $epa_fieldable_panels_pane_transformer
   *   The service to transform fieldable_panels_pane panes.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EpaNodeContentPaneToParagraph $epa_node_content_transformer, EpaCoreHtmlPaneToParagraph $epa_core_html_pane_transformer, EpaCoreListPaneToParagraph $epa_core_list_pane_transformer, EpaFieldablePanelsPaneToParagraph $epa_fieldable_panels_pane_transformer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->epaNodeContentTransformer = $epa_node_content_transformer;
    $this->epaCoreHtmlPaneTransformer = $epa_core_html_pane_transformer;
    $this->epaCoreListPaneTransformer = $epa_core_list_pane_transformer;
    $this->epaFieldablePanelsPaneTransformer = $epa_fieldable_panels_pane_transformer;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('epa_migrations.node_content_pane_to_paragraph'),
      $container->get('epa_migrations.core_html_pane_to_paragraph'),
      $container->get('epa_migrations.core_list_pane_to_paragraph'),
      $container->get('epa_migrations.fieldable_panels_pane_to_paragraph')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraph_ids = [];

    // The value passed into this process is an array of database rows, selected
    // in the epa_node source plugin.
    foreach ($value as $pane) {
      $shown = $pane['shown'];

      if ($shown) {
        $paragraphs = $this->transformParagraphs($pane, $row, $migrate_executable);

        foreach ($paragraphs as $paragraph) {
          $paragraph_ids[] = [
            'target_id' => $paragraph->id(),
            'target_revision_id' => $paragraph->getRevisionId(),
          ];
        }
      }
    }
    return $paragraph_ids;

  }

}
