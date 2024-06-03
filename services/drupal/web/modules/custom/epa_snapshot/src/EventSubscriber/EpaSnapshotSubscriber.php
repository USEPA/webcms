<?php

namespace Drupal\epa_snapshot\EventSubscriber;

use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EPA Snapshot event subscriber.
 */
class EpaSnapshotSubscriber implements EventSubscriberInterface {

  /**
   * Event subscriber method for modifying HTML.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The ModifyHtmlEvent from Tome.
   *
   * @return void
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

    $event->setHtml($doc->saveHTML());
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
  public function createAlertMarkup(\DOMDocument $doc) {
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TomeStaticEvents::MODIFY_HTML => ['onModifyHTML'],
    ];
  }

}
