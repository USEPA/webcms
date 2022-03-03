<?php

namespace Drupal\markdownifier\Plugin\Field\FieldFormatter;

use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;

/**
 * Plugin implementation of the 'Render entity revision to Markdown' formatter.
 *
 * @FieldFormatter(
 *   id = "markdownifier_render_entity_revisions_to_markdown",
 *   label = @Translation("Render entity revision to Markdown"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class RenderEntityRevisionsToMarkdownFormatter extends EntityReferenceRevisionsEntityFormatter {

  use RenderToMarkdownFormatterTrait;

}
