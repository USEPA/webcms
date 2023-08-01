<?php

/**
 * Set all card groups to default values that don't already have a value set.
 */
function epa_core_post_update_9001(&$sandbox) {
  // Update all card group paragraphs to set default image style value
  // if it does not already have a value.
  $card_group_ids = \Drupal::entityQuery('paragraph')
    ->condition('type', 'card_group')
    ->notExists('field_image_style')
    ->execute();

  \Drupal::messenger()->addMessage(count($card_group_ids) . ' card_groups will be updated to set default field_image_style value.');

  $card_groups = \Drupal::entityTypeManager()
    ->getStorage('paragraph')
    ->loadMultiple($card_group_ids);

  /** @var \Drupal\paragraphs\Entity\Paragraph $card_group */
  foreach ($card_groups as $card_group) {
    $card_group->set('field_image_style', 'exdent');
    $card_group->save();
  }

  // Update all card group paragraphs to set default title placement value
  // if it does not already have a value.
  $card_groups_ids2 = \Drupal::entityQuery('paragraph')
    ->condition('type', 'card_group')
    ->notExists('field_title_placement')
    ->execute();

  \Drupal::messenger()->addMessage(count($card_groups_ids2) . ' card_groups will be updated to set default field_title_placement value.');

  $card_groups2 = \Drupal::entityTypeManager()
    ->getStorage('paragraph')
    ->loadMultiple($card_groups_ids2);

  /** @var \Drupal\paragraphs\Entity\Paragraph $card_group */
  foreach ($card_groups2 as $card_group) {
    $card_group->set('field_title_placement', 'media-first');
    $card_group->save();
  }

  // Update all card paragraphs to set the card alignment field if it does not
  // already have a value.
  $card_ids = \Drupal::entityQuery('paragraph')
    ->condition('type', 'card')
    ->notExists('field_flag_card_alignment')
    ->execute();

  \Drupal::messenger()->addMessage(count($card_ids) . ' cards will be updated to set default field_flag_card_alignment value.');

  $cards = \Drupal::entityTypeManager()
    ->getStorage('paragraph')
    ->loadMultiple($card_ids);

  foreach ($cards as $card) {
    $card->set('field_flag_card_alignment', 'default');
    $card->save();
  }
}
