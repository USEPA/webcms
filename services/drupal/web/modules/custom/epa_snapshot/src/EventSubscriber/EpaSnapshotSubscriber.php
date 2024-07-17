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

    // Add the alert markup.
    $this->addAlertMarkup($xpath, $doc);

    // Disable all form elements.
    $this->removeFormElements($xpath, $doc);

    // Add search listing replacement.
    $this->addListingReplacement($xpath, $doc);

    $event->setHtml($doc->saveHTML());
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
  protected function removeFormElements(\DOMXPath $xpath, \DOMDocument &$doc) {
    $forms = $xpath->query('//form');

    foreach ($forms as $form) {
      $form->parentNode->removeChild($form);
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
  protected function addListingReplacement(\DOMXPath $xpath, \DOMDocument &$doc) {
    // Select the listing wrapper.
    $listing_wrapper = $xpath->query('//div[contains(@class, "epa-listing-page")]//div[contains(@class, "l-sidebar-first")]')->item(0);

    // If listing wrapper replace page title and listing wrapper.
    if ($listing_wrapper) {
      $title = $xpath->query('//h1')->item(0);
      $title->nodeValue = 'January 19, 2025 Snapshot';

      $listing_replacement = $this->createListingReplacementMarkup($doc);
      $listing_wrapper->parentNode->replaceChild($listing_replacement, $listing_wrapper);
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
   * Helper method to generate snapshot listing page replacement.
   *
   * @param \DOMDocument $doc
   *   The DOM Document we're working with.
   *
   * @return \DOMDocumentFragment|false
   *   The alert markup DOM Document fragment.
   */
  protected function createListingReplacementMarkup(\DOMDocument $doc) {
    $fragment = $doc->createDocumentFragment();
    $markup = '
      <p>
        You have reached the help page for the January 19, 2025 Web Snapshot. This is
        not the current EPA website.
        <strong>To navigate to the current EPA website, please go to
          <a href="https://www.epa.gov">www.epa.gov</a></strong>.
      </p>

      <h2>What is included in the Web Snapshot?</h2>

      <p>
        The Web Snapshot consists of static content, such as webpages and reports in
        Portable Document Format (PDF), as that content appeared on EPA\'s website as
        of January 19, 2025.
      </p>

      <h2>What is not included in the Web Snapshot?</h2>

      <p>
        There are technical limitations to what could be included in the Web Snapshot.
        For example, many of the links from EPA\'s website are to databases that are
        updated with new information on a regular basis. These databases are not part
        of the static content that comprises the Web Snapshot. Links in the Web
        Snapshot to dynamic databases will take you to the current version of the
        database. Searches there will yield the latest data, rather than the data that
        would have been returned for a search conducted on January 19, 2025. Alerts
        should appear when you are leaving the Web Snapshot and linking elsewhere.
      </p>
      <p>Contact us and other forms have all been disabled.</p>

      <p>
        Links may have been broken in the website as it appeared on January 19, 2025.
        Those links will appear as broken on the Web Snapshot. Likewise, links which
        may have been working on January 19, 2025 but are now no longer active will
        appear as broken links in the Web Snapshot.
      </p>

      <p>
        Finally, certain dynamic collections of content were not included in the
        Snapshot due to their size. Those collections remain available on the current
        EPA website at the following links:
      </p>

      <ul>
        <li>
          EPA\'s Searchable News Releases: available at
          <a href="https://www.epa.gov/newsreleases/search"
            >https://www.epa.gov/newsreleases/search</a
          >
        </li>
        <li>
          EPA\'s older News Releases, available at
          <a href="https://archive.epa.gov/epapages/newsroom_archive/"
            >https://archive.epa.gov/epapages/newsroom_archive/</a
          >
        </li>
      </ul>';

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
