<?php

namespace Drupal\epa_content_tracker\Plugin\views\field;

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;

/**
 * Field handler to generate urls to files or aliases, based on how they are
 * stored in EPA's tracker table.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("epa_content_alias")
 */
class EpaContentAlias extends FieldPluginBase {
  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    $scheme = StreamWrapperManager::getScheme($value);

    // If this is a file do one thing
    if (in_array($scheme,['private','public'])) {
      $value = file_create_url($value);
    }
    // If this is just an alias, do something else
    elseif (empty($scheme)) {
      $value = Url::fromUri("base:". $value)->setAbsolute()->toString();
    }

    return $this->sanitizeValue($value, 'url');
  }

}
