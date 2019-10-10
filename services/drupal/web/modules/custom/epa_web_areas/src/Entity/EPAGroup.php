<?php

namespace Drupal\epa_web_areas\Entity;

use Drupal\group\Entity\Group;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Extends entity for group.
 */
class EPAGroup extends Group {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // If group is updated and group type is web area bulk update associated
    // group content entities.
    $group_type_id = $this->getGroupTypeId();
    if ($update === TRUE && $group_type_id == 'web_area') {
      $class = 'Drupal\epa_web_areas\Entity\EPAGroup';
      $entities = $this->getContentEntities();
      $batch = [
        'title' => $this->t('Updating related content entities path aliases.'),
        'operations' => [
          [
            [$class, 'updateAliases'],
            [$entities],
          ],
        ],
        'error_message' => $this->t('An error occured druing processing'),
        'finished' => [$class, 'finishedUpdateAliases'],
      ];
      batch_set($batch);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupTypeId() {
    return $this->getGroupType()->id();
  }

  /**
   * Batch op to update aliases.
   *
   * @param array $entities
   *   Entities associated with group.
   * @param array $context
   *   Provide messages during batch.
   */
  public static function updateAliases(array $entities, array &$context) {
    if (!isset($context['sandbox']['current'])) {
      $context['sandbox'] = [
        'current' => 0,
        'max' => count($entities),
      ];
      $context['results']['updated'] = 0;
    }
    $sandbox = &$context['sandbox'];

    $limit = 5;
    $entities = array_slice($entities, $sandbox['current'], $limit);

    foreach ($entities as $entity) {
      $saved = \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'update');
      if ($saved) {
        $context['results']['updated']++;
      }
      $sandbox['current']++;
    }

    $context['message'] = t('Processed @current of @total', [
      '@current' => $sandbox['current'],
      '@total' => $sandbox['max'],
    ]);

    $context['finished'] = empty($sandbox['max']) ? 1 : ($sandbox['current'] / $sandbox['max']);
  }

  /**
   * Reports the results of update alias operation.
   *
   * @param bool $success
   *   Whether or not batch finished successfully.
   * @param array $results
   *   Results passed from operations.
   */
  public static function finishedUpdateAliases(bool $success, array $results) {
    // The 'success' parameter means no fatal PHP errors were detected.
    if ($success) {
      $updated = $results['updated'];
      if ($updated) {
        $message = \Drupal::translation()->formatPlural(
          $results['updated'],
          'One alias updated.',
          '@count aliases updated.'
        );
      }
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
