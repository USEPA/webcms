<?php

/**
 * @file
 * Provide views data for epa_workflow.module.
 */

/**
 * Implements hook_views_data().
 */
function epa_workflow_views_data_alter(array &$data) {
  $data['danse_event']['reference_web_area'] = [
    'title' => t('Reference Web Area'),
    'help' => t('The web area of the related content entity.'),
    'field' => [
      'id' => 'epa_workflow_web_area',
    ],
  ];

  return $data;
}
