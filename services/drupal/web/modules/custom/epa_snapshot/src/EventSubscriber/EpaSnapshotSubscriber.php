<?php

namespace Drupal\epa_snapshot\EventSubscriber;

use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * EPA Snapshot event subscriber.
 */
class EpaSnapshotSubscriber implements EventSubscriberInterface {

  /**
   * The request stacks service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Excluded path patterns.
   *
   * @var array
   */
  protected $excludedPatterns = [
    '/entity:webform_submission:/',
    '/entity:user:/',
    '/entity:taxonomy_term:/',
    '/entity:danse_event:/',
    '/entity:danse_notification:/',
    '/entity:danse_notification_action:/',
    '/\/forms\//',
    '/\/perspectives\/search\//',
    '/\/faqs\/search\//',
    '/\/publicnotices\/notices-search\//',
    '/\/newsreleases\/search\//',
    '/\/speeches\/search\//',
    '/\/webguide\//',
    '/\/search-central\//',
    '/\/drupaltraining\//',
    '/\/web-analytics\//',
    '/\/webcmstraining\//',
    '/\/social-media-guide\//',
  ];

  /**
   * Host address to explicitly strip.
   *
   * @var string
   */
  protected $hostString = 'https://www.epa.gov';

  /**
   * Snapshot host address.
   *
   * @var string
   */
  protected $snapshotHost = 'https://19january2025snapshot.epa.gov';

  /**
   * Constructs an EpaSnapshotSubscriber object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  /**
   * Event subscriber method for collecting paths.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The CollectPathsEvent from Tome.
   *
   * @return void
   *   Nothing.
   */
  public function onCollectPaths(CollectPathsEvent $event) {
    $excluded_patterns = $this->excludedPatterns;

    // Get the paths from the event with metadata to text excluded patterns
    // against the path and the original path.
    $paths = $event->getPaths(TRUE);

    // Remove paths that match the excluded patterns.
    foreach ($paths as $path => $metadata) {
      foreach ($excluded_patterns as $pattern) {
        if (preg_match($pattern, $path) || (isset($metadata['original_path']) && preg_match($pattern, $metadata['original_path']))) {
          unset($paths[$path]);
        }
      }
    }

    $event->replacePaths($paths);
  }

  /**
   * Event subscriber method for modifying HTML.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The ModifyHtmlEvent from Tome.
   *
   * @return void
   *   Nothing.
   */
  public function onModifyHtml(ModifyHtmlEvent $event) {
    $html = $event->getHtml();

    // Not utilizing \Drupal\Component\Utility\Html as that assumes we're using
    // HTML snippets and not full HTML page markup.
    $doc = new \DOMDocument();
    // Ignore warnings during HTML soup loading, ensure parser doesn't add HTML
    // or Body tags and does not add a DOCTYPE. This is already included.
    @$doc->loadHTML($html, LIBXML_NOBLANKS | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Find the <section class="usa-banner"> element as we'll use that as
    // our 'landmark' for where to place the alert markup. This is the "An
    // official website of the United States Government" banner found on all
    // pages.
    $xpath = new \DOMXPath($doc);

    // Update metatags.
    $this->updateMetaTags($xpath);

    // Strip the host string from anchor tags.
    $this->stripHostStringFromAnchors($xpath);

    // Add the alert markup.
    $this->addAlertMarkup($xpath, $doc);

    // Disable form elements except paths to be excluded.
    $this->removeFormElements($xpath);

    // Add the search markup.
    $this->addSearchMarkup($xpath, $doc);

    $event->setHtml($doc->saveHTML());
  }

  /**
   * Helper method to replace references to the site url.
   *
   * @param \DOMXPath $xpath
   *   The DOMXPath object.
   *
   * @return void
   *   Nothing.
   */
  protected function updateMetaTags(\DOMXPath $xpath) {
    // Set the host url used for search and replace.
    $host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    // Get link meta tags with href attribute starting with host.
    $links = $xpath->query("//link[starts-with(@href,'$host')]");
    $this->stripAttributeTextFromList($links, 'href', $host, $this->snapshotHost);

    // Get link meta tags with content attribute starting with host.
    // Strip the host from the content attribute.
    $metatags = $xpath->query("//meta[starts-with(@content,'$host')]");
    $this->stripAttributeTextFromList($metatags, 'content', $host, $this->snapshotHost);
  }

  /**
   * Helper method to strip text element attributes in a node list.
   *
   * @param \DOMNodeList $items
   *   The node list to process.
   * @param string $attribute
   *   The attribute of which values to strip.
   * @param string $text
   *   The the text to strip.
   * @param string $replacement
   *   The replacement text.
   */
  protected function stripAttributeTextFromList(\DOMNodeList $items, string $attribute, string $text, $replacement = '') {
    // Get meta tags with content attribute starting with host.
    if (!empty($items)) {
      foreach ($items as $item) {
        /** @var \DOMElement $item */
        $item->setAttribute($attribute, str_replace($text, $replacement, $item->getAttribute($attribute)));
      }
    }
  }

  /**
   * Helper method to check and set alert markup.
   *
   * @param \DOMXPath $xpath
   *   The DOMXPath object.
   * @param \DOMDocument $doc
   *   The DOM Document we're working with.
   *
   * @return void
   *   Nothing.
   */
  protected function addAlertMarkup(\DOMXPath $xpath, \DOMDocument &$doc) {
    $section = $xpath->query('//section[@class="usa-banner"]')->item(0);

    if ($section) {
      $alert = $this->createAlertMarkup($doc);

      // If there's an element after the section (nextSibling) we'll insert our
      // markup before that sibling. Otherwise, we'll append it to the section's
      // parent node.
      if ($section->nextSibling) {
        $section->parentNode->insertBefore($alert, $section->nextSibling);
      }
      else {
        $section->parentNode->appendChild($alert);
      }
    }
  }

  /**
   * Helper method to check and set alert markup.
   *
   * @param \DOMXPath $xpath
   *   The DOMXPath object.
   * @param \DOMDocument $doc
   *   The DOM Document we're working with.
   *
   * @return void
   *   Nothing.
   */
  protected function addSearchMarkup(\DOMXPath $xpath, \DOMDocument &$doc) {
    $search_drawer = $xpath->query('//div[@id="header-search-drawer"]')->item(0);

    if ($search_drawer) {
      $search_form = $this->createSearchMarkup($doc);

      $search_drawer->appendChild($search_form);
    }
  }

  /**
   * Helper method to check and set alert markup.
   *
   * @param \DOMXPath $xpath
   *   The DOMXPath object.
   *
   * @return void
   *   Nothing.
   */
  protected function removeFormElements(\DOMXPath $xpath) {
    $forms = $xpath->query('//form');
    $snapshot_inputs = $xpath->query('//input[@name="site" and @value="snapshot2025"]');
    $snapshot_input = $snapshot_inputs->item(0);

    foreach ($forms as $form) {
      if (!empty($snapshot_input) && $form->isSameNode($snapshot_input->parentNode->parentNode)) {
        continue;
      }
      $form->parentNode->removeChild($form);
    }
  }

  /**
   * Helper method to generate snapshot alert markup.
   *
   * @param \DOMDocument $doc
   *   The DOM Document we're working with.
   *
   * @return \DOMDocumentFragment|false
   *   The alert markup DOM Document fragment.
   */
  protected function createAlertMarkup(\DOMDocument $doc) {
    $fragment = $doc->createDocumentFragment();
    $markup = '
    <div class="js-view-dom-id-epa-alerts--public" style="display: block; box-sizing: border-box;">
        <div class="view view--public-alerts view--display-default js-view-dom-id-js-view-dom-id-public_alerts_default" data-once="ajax-pager">
            <div class="usa-site-alert usa-site-alert--slim usa-site-alert--emergency" data-alert="250089-277888">
                <div class="usa-alert">
                    <div class="usa-alert__body">
                        <div class="u-visually-hidden">Emergency</div>
                        <div class="usa-alert__content">
                            <div class="usa-alert__text">
                                <p>This is not the current EPA website. To navigate to the current EPA website, please go to
                                <a href="https://www.epa.gov" data-once="external-links protected-links">www.epa.gov</a>.
                                This website is historical material reflecting the EPA website as it existed on January 19, 2025.
                                This website is no longer updated and links to external websites and some internal pages may not work.
                                <a href="/home/january-19-2025-snapshot">More information</a> »</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    $fragment->appendXML($markup);
    return $fragment;
  }

  /**
   * Helper method to generate snapshot search markup.
   *
   * @param \DOMDocument $doc
   *   The DOM Document we're working with.
   *
   * @return \DOMDocumentFragment|false
   *   The alert markup DOM Document fragment.
   */
  protected function createSearchMarkup(\DOMDocument $doc) {
    $fragment = $doc->createDocumentFragment();
    $markup = '
    <form class="usa-search usa-search--small usa-search--epa" method="get" action="https://search.epa.gov/epasearch">
      <div role="search">
        <label class="usa-sr-only" for="search-box">Search</label>
        <input class="usa-input" id="search-box" type="search" name="querytext" placeholder="Search EPA.gov"/>
        <button class="button" type="submit" aria-label="Search">
          <svg class="icon usa-search__submit-icon" aria-hidden="true">
            <use href="/themes/epa_theme/images/sprite.artifact.svg#magnifying-glass"></use>
          </svg> <span class="usa-search__submit-text">Search</span>
        </button>
        <input type="hidden" name="areaname" value=""/>
        <input type="hidden" name="areacontacts" value=""/>
        <input type="hidden" name="areasearchurl" value=""/>
        <input type="hidden" name="typeofsearch" value="epa"/>
        <input type="hidden" name="result_template" value=""/>
        <input type="hidden" name="site" value="snapshot2025"/>
      </div>
    </form>';

    $fragment->appendXML($markup);
    return $fragment;
  }

  /**
   * Helper method to strip anchor tags of $hostString.
   *
   * @param \DOMXPath $xpath
   *   The DOMXPath object.
   *
   * @return void
   *   Nothing.
   */
  protected function stripHostStringFromAnchors(\DOMXPath $xpath) {
    $anchors = $xpath->query('//a');

    foreach ($anchors as $anchor) {
      /** @var \DOMElement $anchor */
      $href = $anchor->getAttribute('href');
      $anchor->setAttribute('href', str_replace($this->hostString, '', $href));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['onModifyHTML'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['onCollectPaths', -3];
    return $events;
  }

}
