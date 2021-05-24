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
 * layout_builder__layout:
 *   -
 *     plugin: skip_on_empty
 *     method: process
 *     source: panes
 *   -
 *     plugin: single_value
 *   -
 *     plugin: epa_panes_to_lb_section
 *   -
 *     plugin: multiple_values
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
      // determine how many rows there are and how many columns are in each row.
      // The possible panels for this layout are labeled a1..d4.
      // First, let's organize the panes into an structured array.
      $layout_rows = [];
      foreach ($value as $pane) {
        $row_name = substr($pane['panel'], 0, 1);
        $col_name = substr($pane['panel'], 1, 1);

        $layout_rows[$row_name][$col_name] ? array_push($layout_rows[$row_name][$col_name], $pane) : $layout_rows[$row_name][$col_name] = [$pane];
      }

      // Set translation arrays.
      $layouts_by_num_columns = [
        1 => 'epa_one_column',
        2 => 'epa_two_column',
        3 => 'epa_three_column',
        4 => 'epa_four_column',
      ];

      $regions_by_column_number = [
        1 => 'first',
        2 => 'second',
        3 => 'third',
        4 => 'fourth',
      ];

      // Initialize our return array.
      $sections = [];

      foreach ($layout_rows as $layout_row) {
        // The number of children determines the number of columns.
        $num_columns = count($layout_row);
        $layout = $layouts_by_num_columns[$num_columns];

        $section = new Section($layout);

        // Instead of using the column number keys from the $layout_rows array,
        // we should count the number of columns ourselves. This will ensure we
        // don't create empty columns that might have existed in the flexgrid.
        $col_number = 1;

        foreach ($layout_row as $panes) {

          foreach ($panes as $pane) {
            if ($pane['shown']) {
              if ($num_columns == 1) {
                $region = 'main';
              }
              else {
                $region = $regions_by_column_number[$col_number];
              }

              $pane['is_skinny_pane'] = $num_columns >= 3;
              $component = $this->buildSectionComponent($pane, $row, $migrate_executable, $region);
              if ($component) {
                $section->appendComponent($component);
              }
            }
          }

          $col_number++;
        }

        // Add the built section (layout row) to our return array.
        $sections[] = $section;
      }

      return $sections;
    }
    elseif ($layout === 'rd_homepage') {
      // If the D7 layout is 'rd_homepage', we have only one destination layout.
      // The panel names from D7 will map 1:1 to a region in Layout Builder.
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

          $pane['is_skinny_pane'] = !in_array($region, ['main', 'bottom']);
          $component = $this->buildSectionComponent($pane, $row, $migrate_executable, $region);
          if ($component) {
            $section->appendComponent($component);
          }
        }
      }

      return $section;
    }
    elseif ($layout === 'twocol_page') {
      // If the D7 layout is 'twocol_page', we have only one destination layout.
      // For most node types this layout is migrated into fields. For web_areas
      // it is migrated into Layout Builder.
      // The panel names from D7 will map 1:1 to a region in Layout Builder.
      $regions_by_panel_name = [
        'main_col' => 'main',
        'sidebar' => 'sidebar',
      ];

      $layout = 'epa_one_column_sidebar';

      // Create paragraph inline content blocks from each pane and wrap them in
      // SectionComponents to be assigned to the overall Section.
      $section = new Section($layout);

      foreach ($value as $pane) {
        $shown = $pane['shown'];

        if ($shown) {
          $region = $regions_by_panel_name[$pane['panel']];

          $pane['is_skinny_pane'] = $region == 'sidebar';
          $component = $this->buildSectionComponent($pane, $row, $migrate_executable, $region);
          if ($component) {
            $section->appendComponent($component);
          }
        }
      }

      return $section;
    }
  }

  /**
   * Given a pane, build a Section Component.
   *
   * @param array $pane
   *   The pane data.
   * @param \Drupal\migrate\Row $row
   *   The migration row data.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration executable.
   * @param string $region
   *   The region where the Section Component should be placed.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The Section Component with an inline content block.
   */
  private function buildSectionComponent(array $pane, Row $row, MigrateExecutableInterface $migrate_executable, string $region) {
    if ($pane['type'] == 'node_content') {
      // Create a field_block:node:page:field_paragraphs component.
      $component = new SectionComponent($this->uuid->generate(), $region, [
        'id' => 'field_block:node:page:field_paragraphs',
        'label' => 'Body',
        'provider' => 'layout_builder',
        'label_display' => 0,
        'formatter' => [
          'label' => 'hidden',
          'type' => 'entity_reference_revisions_entity_view',
          'settings' => [
            'view_mode' => 'default',
          ],
          'third_party_settings' => [
            'linked_field' => [
              'linked' => 0,
              'type' => 'field',
              'destination' => [
                'field' => '',
                'custom' => '',
              ],
              'advanced' => [
                'title' => '',
                'target' => '',
                'class' => '',
                'rel' => '',
                'text' => '',
              ],
              'token' => '',
            ],
          ],
        ],
        'context_mapping' => [
          'entity' => 'layout_builder.entity',
          'view_mode' => 'view_mode',
        ],
      ]);

    }
    else {
      $paragraphs = $this->transformParagraphs($pane, $row, $migrate_executable);
      // Create a paragraph block.
      $block = $this->entityTypeManager->getStorage('block_content')
        ->create([
          'info' => 'Paragraph Block',
          'type' => 'paragraph',
          'reusable' => 0,
          'field_paragraphs' => $paragraphs,
        ]);

      // Create Block embedded in a Section Component. Passing a serialized
      // Block entity is the key to making this work.
      $component = new SectionComponent($this->uuid->generate(), $region, [
        'id' => 'inline_block:paragraph',
        'label' => 'Paragraph Block',
        'label_display' => FALSE,
        'block_serialized' => serialize($block),
        'context_mapping' => [],
      ]);
    }

    return $component;
  }

}
