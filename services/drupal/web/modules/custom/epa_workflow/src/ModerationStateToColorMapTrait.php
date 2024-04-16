<?php

namespace Drupal\epa_workflow;

/**
 * This trait is meant to manage the relationship between a moderation state
 * and a color to use for its box.
 *
 * @see https://forumone.atlassian.net/wiki/spaces/EPA/pages/3806265382/Moderation+State+Color+Map
 */
trait ModerationStateToColorMapTrait {
  public static array $colorMap = [
    'draft' => 'yellow',
    'draft_needs_review' => 'yellow',
    'draft_approved' => 'yellow',
    'published' => 'green',
    'published_needs_review' => 'green',
    'published_expiring' => 'green',
    'published_day_til_expire' => 'green',
    'unpublished' => 'yellow',
  ];

  /**
   * Returns the 'box-color' based on moderation state provided.
   *
   * @param string $moderation_state
   *   The moderation state to get the color for.
   *
   * @return \Exception|string
   *   The string for the matching color or an exception if not a valid state.
   */
  public static function colorToModerationStateMap(string $moderation_state) {
    if (!isset(self::$colorMap[$moderation_state])) {
      return new \Exception("The provided moderation state ($moderation_state) is not valid");
    }

    return self::$colorMap[$moderation_state];
  }
}
