<?php
namespace Drupal\epa_elasticsearch\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\elasticsearch_connector\Event\BuildSearchParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EpaElasticsearchEventSubscriber implements EventSubscriberInterface {
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['elasticsearch_connector.build_searchparams'] = ['buildSearchParams'];
    return $events;
  }

  /**
   * React to BuildSearchParamsEvent event.
   *
   * @param \Drupal\elasticsearch_connector\Event\BuildSearchParamsEvent $event
   *   The event.
   */
  public function buildSearchParams(BuildSearchParamsEvent $event) {
    $params = $event->getElasticSearchParams();

    // For searches against the news release index weight recent results more
    // heavily.
    if (!empty($params['index']) && substr($params['index'], -13) == 'news_releases' && !empty($params['body']['query'])) {
      $now = new DrupalDateTime('now');
      $queryString = $params['body']['query'];

      // Add relevancy criteria according to the item's date.
      $params['body']['query'] = [
        'function_score' => [
          'score_mode' => 'sum',
          'boost_mode' => 'multiply',
          'functions' => [
            [
              'weight' => 1,
            ],
            [
              'weight' => 10, // Boost documents released within the last 6
              // months from between 1.1 and 11. Beyond 6 months documents will
              // get boosted approximately the same amount,  between 1 and 1.1.
              // This boost will get multiplied by their query score to get
              // the relevance score.
              'gauss' => [
                'field_release' => [
                  'origin' => $now->format('Y-m-d'),
                  'scale' => '183d', // 6 months
                  'decay' => 0.1,
                ],
              ],
            ],
          ],
          'query' => $queryString,
        ],
      ];
      $event->setElasticSearchParams($params);
    }
  }
}
