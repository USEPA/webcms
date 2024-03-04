<?php

/**
 * @file
 */

use Drupal\group\Entity\Group;
use Drupal\media\Entity\Media;
use Drupal\search_api\Entity\Server;

/**
 *
 */
function _epa_core_populate_search_index_queue() {
  $queue = \Drupal::queue('epa_search_text_indexer');
  // Query all current revisions that lack a value in the search text field.
  $current_revs = \Drupal::database()->query(
    "SELECT vid
           FROM {node} n
           LEFT JOIN {node_revision__field_search_text} nf
           ON n.vid = nf.revision_id WHERE nf.revision_id IS NULL")
    ->fetchCol();

  $latest_revs = \Drupal::database()->query(
    "SELECT n.nid, n.vid as vid
          FROM {node_revision} n
          INNER JOIN
              (SELECT nid,
                   max(vid) AS latest_vid
              FROM {node_revision}
              GROUP BY  nid) nr_latest
              ON n.vid = nr_latest.latest_vid
          LEFT JOIN {node_revision__field_search_text} nf
              ON n.vid = nf.revision_id
          WHERE nf.revision_id IS NULL")
    ->fetchCol(1);

  // Remove current revs from latest revs.
  $latest_revs = array_diff($latest_revs, $current_revs);

  $current_revs = array_fill_keys($current_revs, 'current');
  $latest_revs = array_fill_keys($latest_revs, 'latest');
  $revisions = $current_revs + $latest_revs;

  \Drupal::logger('epa_core')->notice('Queueing ' . count($revisions) . ' revisions that need to have their search text field populated');

  foreach ($revisions as $vid => $type) {
    $queue->createItem(['vid' => $vid, 'type' => $type]);
  }
}

/**
 * Populates the search text fields for existing content.
 */
function epa_core_deploy_0001_populate_search_text(&$sandbox) {
  _epa_core_populate_search_index_queue();
}

/**
 * Sets terms with empty description to global term description token.
 */
function epa_core_deploy_0001_update_term_descriptions(&$sandbox) {
  $text = 'This page shows all of the pages at epa.gov that are tagged with \[term:name\] at this time.';
  if (!isset($sandbox['total'])) {
    // Query all terms that don't have a description set.
    $result = \Drupal::database()->query(
      'SELECT tid FROM taxonomy_term_field_data
        WHERE description__value IS NULL OR
              description__value = :value OR
              description__value REGEXP :regex', [':value' => 'This page shows all of the pages at epa.gov that are tagged with [term:name] at this time.', ':regex' => '^<p>This page shows all of the pages at epa\\.gov that are tagged with \\[term:name\\] at this time\\.<\\/p>[[:space:]]*$'])
      ->fetchCol();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_core')->notice($sandbox['total'] . ' terms with outdated descriptions.');
  }

  // Query 500 at a time for batch.
  $tids = \Drupal::database()->query(
    'SELECT tid FROM taxonomy_term_field_data
        WHERE description__value IS NULL OR
              description__value = :value OR
              description__value REGEXP :regex
            LIMIT 500;', [':value' => 'This page shows all of the pages at epa.gov that are tagged with [term:name] at this time.', ':regex' => '^<p>This page shows all of the pages at epa\\.gov that are tagged with \\[term:name\\] at this time\\.<\\/p>[[:space:]]*$'])
    ->fetchCol();

  if (empty($tids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($tids);

  foreach ($terms as $term) {
    $term->set('description', ['value' => '[term:term-description]', 'format' => 'filtered_html']);
    $term->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_core')->notice($sandbox['current'] . ' terms descriptions updated.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Explicitly sets each taxonomy term to have its path set by pathauto then re-saves
 * terms to ensure they get the latest generated path.
 */
function epa_core_deploy_0002_update_term_path(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Query all terms that don't have a description set.
    $result = \Drupal::database()->query(
      'SELECT tid FROM taxonomy_term_field_data;')
      ->fetchCol();

    $sandbox['total'] = count($result);
    $sandbox['current'] = 0;

    \Drupal::logger('epa_core')->notice($sandbox['total'] . ' term paths to be updated.');
  }

  // Query 500 at a time for batch.
  $tids = \Drupal::database()->query(
    'SELECT tid FROM taxonomy_term_field_data
        ORDER BY tid ASC
        LIMIT 500
        OFFSET ' . $sandbox['current'] . ';')
    ->fetchCol();

  if (empty($tids)) {
    $sandbox['#finished'] = 1;
    return;
  }

  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($tids);

  foreach ($terms as $term) {
    $term->path->pathauto = 1;
    $term->save();
    $sandbox['current']++;
  }

  \Drupal::logger('epa_core')->notice($sandbox['current'] . ' term paths updated.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Tag all search indexes as needing reindexing due to the changes to our
 * processor and field settings.
 */
function epa_core_deploy_refresh_indexes() {
  _epa_core_refresh_indexes('localhost');
}

/**
 * Helper function to refresh all search indexes on a server.
 */
function _epa_core_refresh_indexes($server_name) {
  $localhost = Server::load($server_name);
  foreach ($localhost->getIndexes() as $index) {
    $index->reindex();
  }
}

/**
 * Updating the field definition config for node__field_press_office cardinality
 * to be 1.
 */
function epa_core_deploy_0003_update_node_field_press_office_cardinality() {
  $manager = \Drupal::entityDefinitionUpdateManager();
  $storage_definition = $manager->getFieldStorageDefinition('field_press_office', 'node');
  $storage_definition->setCardinality(1);
  $manager->updateFieldStorageDefinition($storage_definition);
}

/**
 * Moves images on banner slides to banner image field
 * and creates banner image entity where necessary.
 */
function epa_core_deploy_0003_update_banner_slide_images(&$sandbox) {
  $prefixes = ['paragraph_revision', 'paragraph'];

  $replacements = [
    ':group_type' => 'web_area-group_node-%',
    ':value' => 'banner_slide',
  ];

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = 0;
    $sandbox['current'] = 0;
    $sandbox['images_created'] = 0;
    // Query all images that are being used with banner slides.
    foreach ($prefixes as $prefix) {
      $result = \Drupal::database()->query(
        'SELECT DISTINCT fi.field_image_target_id
FROM {' . $prefix . '__field_image} AS fi
LEFT JOIN {file_managed} AS fm
    ON fm.fid = fi.field_image_target_id
LEFT JOIN {' . $prefix . '__field_banner_image} AS pfb
    ON fi.revision_id = pfb.revision_id
LEFT JOIN {paragraph_revision__field_banner_slides} AS fbs
    ON fi.revision_id = fbs.field_banner_slides_target_revision_id
LEFT JOIN {node_revision__field_banner} AS nfb
    ON nfb.field_banner_target_revision_id = fbs.revision_id
LEFT JOIN {group_content_field_data} gfd
    ON gfd.entity_id = nfb.entity_id
        AND gfd.type LIKE :group_type
WHERE pfb.revision_id IS NULL
        AND fi.bundle = :value
        AND gid IS NOT NULL;', $replacements)->fetchCol();

      $sandbox['total'] += count($result);

    }

    \Drupal::logger('epa_core')->notice($sandbox['total'] . ' image files associated with banner slides.');
  }

  foreach ($prefixes as $prefix) {

    $files = \Drupal::database()->query(
      'SELECT DISTINCT fi.field_image_target_id,
         fi.field_image_alt,
         fm.filename,
         fm.langcode,
         fi.entity_id,
         fi.revision_id,
         gfd.gid
FROM {' . $prefix . '__field_image} AS fi
LEFT JOIN {file_managed} AS fm
    ON fm.fid = fi.field_image_target_id
LEFT JOIN {' . $prefix . '__field_banner_image} AS pfb
    ON fi.revision_id = pfb.revision_id
LEFT JOIN {paragraph_revision__field_banner_slides} AS fbs
    ON fi.revision_id = fbs.field_banner_slides_target_revision_id
LEFT JOIN {node_revision__field_banner} AS nfb
    ON nfb.field_banner_target_revision_id = fbs.revision_id
LEFT JOIN {group_content_field_data} gfd
    ON gfd.entity_id = nfb.entity_id
        AND gfd.type LIKE :group_type
WHERE pfb.revision_id IS NULL
        AND fi.bundle = :value
        AND gid IS NOT NULL
        LIMIT 500;', $replacements)
      ->fetchAll();

    foreach ($files as $file) {
      $banner_media = \Drupal::database()->query(
        'SELECT entity_id
          FROM media__field_media_image
          WHERE field_media_image_target_id = ' . $file->field_image_target_id . '
            AND bundle = :value;', [':value' => 'banner_image'])
        ->fetchCol();

      if (empty($banner_media)) {
        $image_media = Media::create([
          'bundle' => 'banner_image',
          'uid' => \Drupal::currentUser()->id(),
          'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
          'field_media_image' => [
            'target_id' => $file->field_image_target_id,
            'alt' => $file->field_image_alt,
            'title' => $file->filename,
          ],
        ]);
        $image_media->save();
        $langcode = $image_media->language()->getId();
        $banner_image_target_id = $image_media->id();
        if (!empty($file->gid)) {
          $group = Group::load($file->gid);
          $group->addContent($image_media, 'group_media:' . $image_media->bundle());
        }
        $sandbox['images_created']++;
      }
      else {
        $banner_image_target_id = $banner_media[0];
        $langcode = $file->langcode;
        if (empty($langcode)) {
          $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
        }
      }
      $connection = \Drupal::service('database');
      $result = $connection->insert($prefix . '__field_banner_image')
        ->fields([
          'bundle' => 'banner_slide',
          'deleted' => 0,
          'entity_id' => $file->entity_id,
          'revision_id' => $file->revision_id,
          'langcode' => $langcode,
          'delta' => 0,
          'field_banner_image_target_id' => $banner_image_target_id,
        ])
        ->execute();
      $sandbox['current']++;
    }
  }

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
    \Drupal::logger('epa_core')->notice('Banner slide image update complete');
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }

  \Drupal::logger('epa_core')->notice($sandbox['current'] . ' images processed / ' . $sandbox['images_created'] . ' banner images created.');

}

/**
 * Setting default values for field_image_style, field_title_placement, and field_flag_card_alignment
 */
function epa_core_deploy_0004_set_card_field_default_values(&$sandbox) {
  $database = \Drupal::database();
  $prefixes = [
    'paragraph__' => 'paragraphs_item',
    'paragraph_revision__' => 'paragraphs_item_revision'
  ];
  foreach ($prefixes as $table_prefix => $item_table) {
    // It seems there is some issue that there are multiple revisions of the same paragraph in the same table
    // Going to delete those records first
    $item_table = $item_table . '_field_data';

    $field_table = $table_prefix . 'field_image_style';
    $database->query("DELETE f FROM $field_table as f LEFT JOIN $item_table as i on f.revision_id = i.revision_id WHERE i.revision_id IS NULL");

    // Update all card_group paragraph revision and non-revision items to set field_image_style if a value is not already set.
    $missing_image_styles_paragraphs = $database->query("SELECT DISTINCT p.id, p.revision_id, i.revision_id AS field_revision_id FROM $item_table AS p LEFT JOIN $field_table AS i ON p.id = i.entity_id AND p.revision_id = i.revision_id WHERE p.type = 'card_group' AND i.field_image_style_value IS NULL")->fetchAll();
    foreach ($missing_image_styles_paragraphs as $paragraph) {
      if (empty($paragraph->field_revision_id)) {
        // Record does not exist in field table.
        try {
          $database->insert($field_table)->fields([
            'bundle' => 'card_group',
            'deleted' => 0,
            'entity_id' => $paragraph->id,
            'revision_id' => $paragraph->revision_id,
            'langcode' => 'en',
            'delta' => 0,
            'field_image_style_value' => 'exdent'
          ])->execute();
        }
        catch (\Exception $e) {
          \Drupal::logger('epa_core')->error("Error inserting record into $field_table table: " . $e->getMessage());
        }
      }
      else {
        // Record does exist but is null, run update instead.
        try {
          $database->update($field_table)->fields([
            'field_image_style_value' => 'exdent'
          ])->execute();
        }
        catch (\Exception $e) {
          \Drupal::logger('epa_core')->error("Error updating record into $field_table table: " . $e->getMessage());
        }

      }
    }

    // Update all card_group paragraph revision and non-revision items to set field_title_placement if a value is not already set.
    $field_table = $table_prefix . 'field_title_placement';
    $database->query("DELETE f FROM $field_table as f LEFT JOIN $item_table as i on f.revision_id = i.revision_id WHERE i.revision_id IS NULL");

    $missing_title_placement_paragraphs = $database->query("SELECT DISTINCT p.id, p.revision_id, i.revision_id AS field_revision_id FROM $item_table AS p LEFT JOIN $field_table AS i ON p.revision_id = i.revision_id WHERE p.type = 'card_group' AND i.field_title_placement_value IS NULL")->fetchAll();
    foreach ($missing_title_placement_paragraphs as $paragraph) {
      if (empty($paragraph->field_revision_id)) {
        // Record does not exist in field table.
        try {
          $database->insert($field_table)->fields([
            'bundle' => 'card_group',
            'deleted' => 0,
            'entity_id' => $paragraph->id,
            'revision_id' => $paragraph->revision_id,
            'langcode' => 'en',
            'delta' => 0,
            'field_title_placement_value' => 'media-first'
          ])->execute();
        }
        catch (\Exception $e) {
          \Drupal::logger('epa_core')->error("Error inserting record into $field_table table: " . $e->getMessage());
        }
      }
      else {
        // Record does exist but is null, run update.
        try {
          $database->update($field_table)->fields([
            'field_title_placement_value' => 'media-first'
          ])->execute();
        }
        catch (\Exception $e) {
          \Drupal::logger('epa_core')->error("Error updating record into $field_table table: " . $e->getMessage());
        }
      }

    }

    // Update all card paragraph revision and non-revision items to set field_flag_card_alignment if a value is not already set.
    $field_table = $table_prefix . 'field_flag_card_alignment';
    $database->query("DELETE f FROM $field_table as f LEFT JOIN $item_table as i on f.revision_id = i.revision_id WHERE i.revision_id IS NULL");
    $missing_flag_alignment_paragraphs = $database->query("SELECT DISTINCT p.id, p.revision_id, i.revision_id as field_revision_id FROM $item_table AS p LEFT JOIN $field_table AS i ON p.revision_id = i.revision_id WHERE p.type = 'card' AND i.field_flag_card_alignment_value IS NULL")->fetchAll();
    foreach ($missing_flag_alignment_paragraphs as $paragraph) {
      if (empty($paragraph->field_revision_id)) {
        // Record does not exist in field table.
        try {
          $database->insert($field_table)->fields([
            'bundle' => 'card',
            'deleted' => 0,
            'entity_id' => $paragraph->id,
            'revision_id' => $paragraph->revision_id,
            'langcode' => 'en',
            'delta' => 0,
            'field_flag_card_alignment_value' => 'default'
          ])->execute();
        }
        catch (\Exception $e) {
          \Drupal::logger('epa_core')->error("Error inserting record into $field_table table: " . $e->getMessage());
        }
      }
      else {
        // Record does exist but is null, run update.
        try {
          $database->update($field_table)->fields([
            'field_flag_card_alignment_value' => 'default'
          ])->execute();
        }
        catch (\Exception $e) {
          \Drupal::logger('epa_core')->error("Error updating record into $field_table table: " . $e->getMessage());
        }

      }
    }
  }
}

/**
 * Setting notification_opt_in flag for node author, author of the latest
 * revision, or author of the current revision
 */
function epa_core_deploy_0055_set_watch_flag(&$sandbox) {
  if (!isset($sandbox['total'])) {
    // Get all nodes.
    $nodes = \Drupal::database()->query(
      "SELECT node.nid,
         latest.revision_uid,
         node.uid,
         current.revision_uid
FROM node_revision AS latest
LEFT JOIN node_field_data AS node
    ON latest.nid = node.nid
LEFT JOIN node_revision AS current
    ON node.vid = current.vid
WHERE latest.vid IN
    (SELECT MAX(vid)
    FROM node_revision
    GROUP BY  nid);")
      ->fetchAll();

    $sandbox['total'] = count($nodes);
    $sandbox['current'] = 0;
    \Drupal::logger('epa_core')
      ->notice('Queueing ' . count($nodes) . ' nodes to be updated with notification_opt_in flag');
  }

  // Query 500 at a time for batch.
  $nodes = \Drupal::database()->query(
    "SELECT node.nid,
         latest.revision_uid as latest_uid,
         node.uid,
         current.revision_uid
FROM node_revision AS latest
LEFT JOIN node_field_data AS node
    ON latest.nid = node.nid
LEFT JOIN node_revision AS current
    ON node.vid = current.vid
WHERE latest.vid IN
    (SELECT MAX(vid)
    FROM node_revision
    GROUP BY  nid) LIMIT 500
        OFFSET " . $sandbox['current'] . ";")
    ->fetchAll();

  foreach ($nodes as $data) {
    _epa_core_set_notification_opt_in_flag($data);
    $sandbox['current']++;
  }

  \Drupal::logger('epa_core')->notice($sandbox['current'] . ' nodes updated.');

  if ($sandbox['current'] >= $sandbox['total']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
}

/**
 * Sets flag for node owner, current and latest revision owner for an entity.
 *
 * @param $data
 *
 * @return void
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function _epa_core_set_notification_opt_in_flag($data) {
  $flag_id = 'notification_opt_in';
  if ($data->nid) {
    if ($data->uid) {
      if (!_epa_core_get_notification_opt_in_flag($flag_id, $data->nid, $data->uid)) {
        $flagging = \Drupal::entityTypeManager()
          ->getStorage('flagging')
          ->create([
            'uid' => $data->uid,
            'flag_id' => $flag_id,
            'entity_id' => $data->nid,
            'entity_type' => 'node',
            'global' => 0,
          ]);
        $flagging->save();
      }
    }
    if ($data->revision_uid) {
      if (!_epa_core_get_notification_opt_in_flag($flag_id, $data->nid, $data->revision_uid)) {
        $flagging = \Drupal::entityTypeManager()
          ->getStorage('flagging')
          ->create([
            'uid' => $data->revision_uid,
            'flag_id' => $flag_id,
            'entity_id' => $data->nid,
            'entity_type' => 'node',
            'global' => 0,
          ]);
        $flagging->save();
      }
    }
    if ($data->latest_uid) {
      if (!_epa_core_get_notification_opt_in_flag($flag_id, $data->nid, $data->latest_uid)) {
        $flagging = \Drupal::entityTypeManager()
          ->getStorage('flagging')
          ->create([
            'uid' => $data->latest_uid,
            'flag_id' => $flag_id,
            'entity_id' => $data->nid,
            'entity_type' => 'node',
            'global' => 0,
          ]);
        $flagging->save();
      }
    }
  }
}

/**
 * Check if user is already flagged to entity.
 *
 * @param $flag_id
 * @param $entity_id
 * @param $uid
 *
 * @return array
 */
function _epa_core_get_notification_opt_in_flag($flag_id, $entity_id, $uid) {
  $current_flag = \Drupal::database()->query(
    "SELECT *
            FROM {flagging}
            WHERE uid=" . $uid . "
            AND flag_id='" . $flag_id . "'
            AND entity_id=" . $entity_id . ";")->fetchAll();
  return $current_flag;
}
