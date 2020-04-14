<?php

namespace Drupal\epa_migrations\Plugin\migrate\process;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\epa_migrations\EpaCoreHtmlPaneToParagraph;
use Drupal\epa_migrations\EpaCoreListPaneToParagraph;
use Drupal\epa_migrations\EpaFieldablePanelsPaneToParagraph;
use Drupal\epa_migrations\EpaNodeContentPaneToParagraph;
use Drupal\epa_migrations\EpaTransformParagraphsTrait;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Given a set of panes, returns a layout builder section.
 *
 * @MigrateProcessPlugin(
 *   id = "epa_panes_to_lb_section"
 * )
 *
 * To convert an array of panes to blocks laid out in a layout builder
 * section, do the following:
 *
 * @code
 * field_paragraphs:
 *   plugin: epa_panes_to_lb_section
 *   source: panes
 * @endcode
 */
class EpaPanesToLbSection extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
   * Uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EpaNodeContentPaneToParagraph $epa_node_content_transformer, EpaCoreHtmlPaneToParagraph $epa_core_html_pane_transformer, EpaCoreListPaneToParagraph $epa_core_list_pane_transformer, EpaFieldablePanelsPaneToParagraph $epa_fieldable_panels_pane_transformer, UuidInterface $uuid, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->epaNodeContentTransformer = $epa_node_content_transformer;
    $this->epaCoreHtmlPaneTransformer = $epa_core_html_pane_transformer;
    $this->epaCoreListPaneTransformer = $epa_core_list_pane_transformer;
    $this->epaFieldablePanelsPaneTransformer = $epa_fieldable_panels_pane_transformer;
    $this->uuid = $uuid;
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('epa_migrations.fieldable_panels_pane_to_paragraph'),
      $container->get('uuid'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $layout = $row->getSourceProperty('layout');
    if ($layout === 'flexgrid') {
      // If the D7 layout is 'flexible_grid', inspect pane panels so we can
      // determine how many columns there are and which layout builder layout to
      // use. The possible panels for this layout are labeled a1..d4.
      $panel_names = array_column($value, 'panel');

      // Extract the number from the panel name.
      $panel_names = array_map(function ($value) {
        return substr($value, 1, 1);
      },
      $panel_names);

      // The highest number determines the number of columns.
      asort($panel_names);
      $num_columns = end($panel_names);

      $layouts_by_num_columns = [
        1 => 'epa_one_column',
        2 => 'epa_two_column',
        3 => 'epa_three_column',
        4 => 'epa_four_column',
      ];

      $layout = $layouts_by_num_columns[$num_columns];

      $regions_by_column_number = [
        1 => 'first',
        2 => 'second',
        3 => 'third',
        4 => 'fourth',
      ];

      // Create paragraph inline content blocks from each pane and wrap them in
      // SectionComponents to be assigned to the overall Section.
      $section = new Section($layout);

      foreach ($value as $pane) {
        $shown = $pane['shown'];

        if ($shown) {
          if ($num_columns == 1) {
            $region = 'main';
          }
          else {
            $column_number = substr($pane['panel'], 1, 1);
            $region = $regions_by_column_number[$column_number];
          }

          $paragraphs = $this->transformParagraphs($pane, $row, $migrate_executable);

          if ($paragraphs) {
            $component = $this->buildSectionComponent($paragraphs, $region);
            $section->appendComponent($component);
          }
        }
      }

      return $section;
    }
    elseif ($layout === 'rd_homepage') {
      // If the D7 layout is 'rd_homepage', we have only one destination layout.
      // The panel names from D7 will map 1:1 to a region in Layout Builder.
      $panel_names = array_column($value, 'panel');

      $regions_by_panel_name = [
        'main_col' => 'main',
        'rda1' => 'a1',
        'rda2' => 'a2',
        'rdb1' => 'b1',
        'rdb2' => 'b2',
        'rdc1' => 'c1',
        'rdc2' => 'c2',
        'bottom' => 'bottom',
        'sidebar' => 'sidebar',
      ];

      $layout = 'epa_resource_directory';

      // Create paragraph inline content blocks from each pane and wrap them in
      // SectionComponents to be assigned to the overall Section.
      $section = new Section($layout);

      foreach ($value as $pane) {
        $shown = $pane['shown'];

        if ($shown) {
          $region = $regions_by_panel_name[$pane['panel']];

          $paragraphs = $this->transformParagraphs($pane, $row, $migrate_executable);

          if ($paragraphs) {
            $component = $this->buildSectionComponent($paragraphs, $region);
            $section->appendComponent($component);
          }
        }
      }

      return $section;
    }
  }

  /**
   * Given an array of paragraphs, build a Section Component.
   *
   * @param array $paragraphs
   *   The paragraphs to assign this component.
   * @param string $region
   *   The region where the Section Component should be placed.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The Section Component with an inline content block.
   */
  private function buildSectionComponent(array $paragraphs, string $region) {
    // Build a  Block entity.
    $block = $this->entityTypeManager->getStorage('block_content')
      ->create([
        'info' => 'Paragraph Block',
        'type' => 'paragraph',
        'reusable' => 0,
        'field_paragraphs' => $paragraphs,
      ]
    );

    // Create Block embedded in a Section Component. Passing a serialized
    // Block entity is the key to making this work.
    $component = new SectionComponent($this->uuid->generate(), $region, [
      'id' => 'inline_block:paragraph',
      'label' => 'Paragraph Block',
      'label_display' => 'false',
      'block_serialized' => serialize($block),
      'context_mapping' => [],
    ]);

    return $component;
  }

}
