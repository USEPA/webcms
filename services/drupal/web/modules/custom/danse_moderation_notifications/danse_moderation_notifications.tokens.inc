<?php

/**
 * @file
 * Content moderation notifications token support.
 */

use Drupal\danse_moderation_notifications\Tokens;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function danse_moderation_notifications_token_info() {
  return Tokens::info();
}

/**
 * Implements hook_tokens().
 */
function danse_moderation_notifications_tokens($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  return \Drupal::service('class_resolver')
    ->getInstanceFromDefinition(Tokens::class)
    ->getTokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
