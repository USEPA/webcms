<?php

namespace Drupal\epa_elasticsearch\Plugin\search_api\backend;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\SearchApiElasticsearchBackend;
use Drupal\search_api\Query\QueryInterface;

/**
 * EPA Elasticsearch Search API Backend definition.
 *
 * @SearchApiBackend(
 *   id = "epa_elasticsearch",
 *   label = @Translation("EPA Elasticsearch"),
 *   description = @Translation("Index items using an Elasticsearch server. Has customizations unique to EPA.")
 * )
 */

class EpaSearchApiElasticsearchBackend extends SearchApiElasticsearchBackend {
  /**
   * Allow custom changes before sending a search query to Elasticsearch.
   *
   * This allows subclasses to apply custom changes before the query is sent to
   * Elasticsearch.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(QueryInterface $query) {
    $test = 'test';
//    if (!empty($query->getKeys())) {
//      $now = new DrupalDateTime('now');
//      $queryString = $params['body']['query'];
//
//      // Add relevancy criteria according to the item's date.
//      $params['body']['query'] = [
//        'function_score' => [
//          'score_mode' => 'sum',
//          'boost_mode' => 'multiply',
//          'functions' => [[
//            'weight' => 1,
//          ], [
//            'weight' => 5,
//            'gauss' => [
//              'custom_date' => [
//                'origin' => $now->format('Y-m-d'),
//                'scale' => '31d',
//                'decay' => 0.5,
//              ],
//            ],
//          ], [
//            'weight' => 2,
//            'gauss' => [
//              'custom_date' => [
//                'origin' => $now->format('Y-m-d'),
//                'scale' => '356d',
//                'decay' => 0.5,
//              ],
//            ],
//          ],
//          ],
//          'query' => $queryString,
//        ],
//      ];
//    }
  }
}
