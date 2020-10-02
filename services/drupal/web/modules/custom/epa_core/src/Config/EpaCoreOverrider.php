<?php

namespace Drupal\epa_core\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;


class EpaCoreOverrider implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    $notifications_enabled = Settings::get('epa_workflow_notifications_enabled', FALSE);
    if (isset($names[0]) && strpos($names[0], 'content_moderation_notifications.content_moderation_notification.') === 0) {
      foreach ($names as $name) {
        $overrides[$name] = ['status' => $notifications_enabled];
      }
    }
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'EpaCoreOverrider';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }
}
