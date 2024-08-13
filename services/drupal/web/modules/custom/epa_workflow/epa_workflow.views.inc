<?php

/**
 * @file
 * Provide views data for epa_workflow.module.
 */

/**
 * Implements hook_views_data_alter().
 */
function epa_workflow_views_data_alter(array &$data) {
  $data['danse_event']['reference_id'] = [
    'title' => t('Reference ID'),
    'help' => t('The ID of the related content entity.'),
    'real field' => 'reference',
    'field' => [
      'id' => 'epa_workflow_id',
    ],
    'relationship' => [
      'base' => 'node_field_data',
      'base field' => 'nid',
      'id' => 'standard',
      'field' => 'reference',
    ],
  ];

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

  $data['danse_event']['reference_node_transition'] = [
    'title' => t('Node Transition'),
    'help' => t('The before and after state of the related content entity.'),
    'field' => [
      'id' => 'epa_workflow_node_transition',
    ],
  ];

  $data['danse_event']['reference_node_transition_from'] = [
    'title' => t('Node Transition From'),
    'help' => t('The before state of the related content entity.'),
    'field' => [
      'id' => 'epa_workflow_node_transition_from',
    ],
  ];

  $data['danse_event']['reference_node_transition_to'] = [
    'title' => t('Node Transition To'),
    'help' => t('The after state of the related content entity.'),
    'field' => [
      'id' => 'epa_workflow_node_transition_to',
    ],
  ];

  return $data;
}
