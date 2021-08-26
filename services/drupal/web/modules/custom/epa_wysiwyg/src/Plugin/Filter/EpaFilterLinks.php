<?php

namespace Drupal\epa_wysiwyg\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\filter\Annotation\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EpaFilterLinks
 * @Filter(
 *   id = "epa_filter_links",
 *   title = @Translation("Replace links of type node/[id] with aliases"),
 *   description = @Translation("Replaces canonical links with aliased paths."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE
 * )
 * @package Drupal\epa_wysiwyg\Plugin\Filter
 */
class EpaFilterLinks extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a EpaFilterLinks Filter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param $plugin_id
   *   The plugin_id for the plugin instance.
   * @param $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity repository service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
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
   * Performs the filter processing.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cacheability metadata and placeholders.
   *
   * @see \Drupal\filter\FilterProcessResult
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//a[@href]') as $element) {
      /** @var \DOMElement $element */
      try {
        $href = $element->getAttribute('href');

        // @todo: improve this to support any type of entity. Will require
        // more intelligently loading routes.
        if (strpos($href, '/node/') === 0) {
          // We have a node path. Attempt to load the node.
          $nid = explode('node/', $href)[1];
          if ($nid) {
            $entity = $this->entityTypeManager
              ->getStorage('node')
              ->load($nid);
            if ($entity) {
              $url = $entity->toUrl()->toString(TRUE);
              $element->setAttribute('href', $url->getGeneratedUrl());
              $result
                // - the generated URL (which has undergone path & route
                // processing)
                ->addCacheableDependency($url)
                // - the linked entity (whose URL and title may change)
                ->addCacheableDependency($entity);
            }
          }
        }
      } catch (\Exception $e) {
        watchdog_exception('epa_filter_links', $e);
      }
    }

    $result->setProcessedText(Html::serialize($dom));
    return $result;
  }

}
