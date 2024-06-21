<?php

/**
 * @file
 * Provide views data for epa_workflow.module.
 */

/**
 * Implements hook_views_data_alter().
 */
function epa_workflow_views_data_alter(array &$data) {
  $data['danse_event']['reference_web_area'] = [
    'title' => t('Reference Web Area'),
    'help' => t('The web area of the related content entity.'),
    'field' => [
      'id' => 'epa_workflow_web_area',
    ],
  ];

  $data['danse_event']['reference_content_type'] = [
    'title' => t('Reference Content Type'),
    'help' => t('The content type of the related content entity.'),
    'field' => [
      'id' => 'epa_workflow_content_type',
    ],
  ];

  return $data;
}
