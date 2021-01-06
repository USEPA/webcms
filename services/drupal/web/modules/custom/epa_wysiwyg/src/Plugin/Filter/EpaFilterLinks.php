<?php

namespace Drupal\epa_wysiwyg\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\filter\Annotation\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\node\Entity\Node;

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
class EpaFilterLinks extends FilterBase {

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
          $url = Url::fromUri("internal:". $href);
          if ($url->isRouted() && $url->getRouteName() == 'entity.node.canonical' && $parameters = $url->getRouteParameters()) {
              $element->setAttribute('href', $url->toString());
              $entity = Node::load($parameters['node']);
              // The processed text now depends on:
              $result
                // - the generated URL (which has undergone path & route
                // processing)
                ->addCacheableDependency($url)
                // - the linked entity (whose URL and title may change)
                ->addCacheableDependency($entity);
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
