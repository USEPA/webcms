<?php

namespace Drupal\epa_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\token\TokenEntityMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a blank plugin needed to exist for the upgrade to Drupal 10.
 *
 * The token_formatters module had their plugin previously named "tokenized_field_formatter".
 * However, updating to the D10 version that plugin ID was changed. This causes
 * issues on deployment as the plugin is expected to still exist. This empty plugin
 * exists to fulfill that upgrade requirement.
 *
 * After D10 has been promoted to live remove this plugin.
 *
 * @FieldFormatter(
 *   id = "tokenized_field_formatter",
 *   label = @Translation("Tokenized text"),
 *   description = @Translation("Display tokenized text as an optional link with tokenized destination."),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions"
 *   }
 * )
 */
class EPARemoveThisPluginAfterUpgrade extends FormatterBase {


  public function viewElements(FieldItemListInterface $items, $langcode) {
    return [];
  }

}
