<?php

namespace Drupal\epa_workflow\Plugin\Action;

/**
 * Provides the 'Flag content on behalf of user' action.
 *
 * @Action(
 *  id = "epa_flag_on_behalf_of_user",
 *  label = @Translation("Flag content on behalf of user"),
 *  type = "node",
 *  category = @Translation("Custom")
 * )
 */
class EPAFlagUsersAction extends EPAFlagUsersActionBase {

  /**
   * {@inheritDoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\user\Entity\User[] $selected_users */
    $selected_users = $this->configuration['selected_users'];

    if ($entity && !empty($selected_users)) {
      $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());

      // Arrays to keep track of users.
      $flagged_users = [];

      foreach ($selected_users as $user) {
        $flag_service = $this->flagService;
        /** @var \Drupal\flag\Entity\Flag $flag */
        $flag = $flag_service->getFlagById(self::NOTIFICATION_FLAG_ID);
        if (!$flag->isFlagged($entity, $user)) {
          // Flag the entity for the user.
          $flag_service->flag($flag, $entity, $user);
        }
        $flagged_users[] = $user->getAccountName();
      }

      // Create messages based on flagged users.
      $messages = [];
      if (!empty($flagged_users)) {
        $flagged_users_string = implode(', ', $flagged_users);
        $messages[] = $this->t('Successfully flagged node @id for user(s): @users', [
          '@id' => $entity->id(),
          '@users' => trim($flagged_users_string),
        ]);
      }

      // Join messages with line breaks for clarity.
      return implode("\n", $messages);
    }

    return NULL;
  }
}
