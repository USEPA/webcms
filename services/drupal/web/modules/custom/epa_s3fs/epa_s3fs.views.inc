<?php

/**
 * Implements hook_views_data().
 */
//function epa_s3fs_views_data() {
//  $data = [];
//
//  // S3FS table.
//  $data['s3fs_file'] = [];
//  $data['s3fs_file']['table']['group'] = t('S3 Files');
//  $data['s3fs_file']['table']['base'] =[
//    'field' => 'uri',
//    'title' => t('S3 Files (s3fs)'),
//    'help' => t('S3 File System records exposed to Views.'),
//  ];
//
//  // URI field
//  $data['s3fs_file']['uri'] = [
//    'title' => t('S3FS URI'),
//    'help' => t('The S3FS URI of the file.'),
//    'field' => [
//      'id' => 'standard',
//    ],
//    'sort' => [
//      'id' => 'standard',
//    ],
//    'filter' => [
//      'id' => 'standard',
//    ],
//  ];
//
//  // The directory field (boolean).
//  $data['s3fs_file']['dir'] =[
//    'title' => t('S3FS URI Directory status'),
//    'help' => t('Directory status boolean value for S3FS URI entry.'),
//    'field' => [
//      'id' => 'boolean',
//    ],
//    'filter' => [
//      'id' => 'boolean',
//      'label' => t('S3FS URI Directory status'),
//      'use_equal' => TRUE,
//    ],
//  ];
//
//  return $data;
//}

//function epa_s3fs_views_data_alter() {
//  $data['file']['s3fs_file'] = [
//    'title' => t('S3FS File'),
//    'help' => t('Details about the file stored in S3FS'),
//
//    'relationship' => [
//      'base' => 's3fs_file',
//      'base field' => 'uri',
//      'field' => 'uri',
//      'id' => 'standard',
//      'label' => t('S3FS File'),
//    ],
//  ];
//}
