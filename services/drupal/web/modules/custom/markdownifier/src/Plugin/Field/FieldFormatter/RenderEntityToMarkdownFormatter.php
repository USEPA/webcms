<?php

namespace Drupal\markdownifier\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;

/**
 * Plugin implementation of the 'Render entity to Markdown' formatter.
 *
 * @FieldFormatter(
 *   id = "markdownifier_render_entity_to_markdown",
 *   label = @Translation("Render entity to Markdown"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class RenderEntityToMarkdownFormatter extends EntityReferenceEntityFormatter {

  use RenderToMarkdownFormatterTrait;

}
