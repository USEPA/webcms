<?php

namespace Drupal\epa_content_tracker\Plugin\views\field;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a File object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    $scheme = StreamWrapperManager::getScheme($value);

    // If this is a file do one thing.
    if (in_array($scheme, ['private', 'public'])) {
      $value = $this->fileUrlGenerator->generateAbsoluteString($value);
    }
    // If this is just an alias, do something else.
    elseif (empty($scheme)) {
      $value = Url::fromUri("base:" . $value)->setAbsolute()->toString();
    }

    return $this->sanitizeValue($value, 'url');
  }

}
