<?php

namespace Drupal\epa_rss\Plugin\views\style;

use Drupal\views\Plugin\views\style\Rss;
use Drupal\Core\Url;

/**
 * Default style plugin to render an EPA Faceted RSS feed.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "epa_faceted_rss",
 *   title = @Translation("EPA Faceted RSS Feed"),
 *   help = @Translation("Generates a faceted RSS feed from a view."),
 *   theme = "views_view_rss",
 *   display_types = {"feed"}
 * )
 */
class EpaFacetedRss extends Rss {

  /**
   * {@inheritDoc}
   */
  public function attachTo(array &$build, $display_id, Url $feed_url, $title) {
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;

    $rss_path = $this->view->getUrl()->toString();

    // This makes a big assumption that the rss path is identical to root path
    // but just with /rss tacked on.
    // ex. newsreleases/search & newsreleases/search/rss.
    $current_path = Url::fromRoute('<current>')->toString();
    $length = strlen($rss_path) - 4;
    $facets = substr($current_path, $length);

    $url = Url::fromUserInput($rss_path . $facets, $url_options)->toString();

    // Add the RSS icon to the view.
    $this->view->feedIcons[] = [
      '#theme' => 'feed_icon',
      '#url' => $url,
      '#title' => $title,
    ];

    // Attach a link to the RSS feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => 'application/rss+xml',
      'title' => $title,
      'href' => $url,
    ];
  }

  /**
   * Return an array of additional XHTML elements to add to the channel.
   *
   * @return array
   *   A render array.
   */
  protected function getChannelElements() {
    // Set the atom:link tag to the current route, rather than the view's
    // configured path, which won't have the facets.
    $url_options['absolute'] = TRUE;
    $url = Url::fromRoute('<current>', [], $url_options)->toString();
    return [
      [
        'namespace' => ['xmlns:atom' => 'http://www.w3.org/2005/Atom'],
        '#type' => 'html_tag',
        '#tag' => 'atom:link',
        '#attributes' => [
          'href' => $url,
          'rel' => 'self',
          'type' => 'application/rss+xml',
        ],
      ],
    ];
  }

}
