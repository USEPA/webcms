<?php

namespace Drupal\epa_workflow\Plugin\Action;

/**
 * Provides the 'Unflag content on behalf of user' action.
 *
 * @Action(
 *  id = "epa_unflag_on_behalf_of_user",
 *  label = @Translation("Unflag content on behalf of user"),
 *  type = "node",
 *  category = @Translation("Custom")
 * )
 */
class EPAUnflagUsersAction extends EPAFlagUsersActionBase {

  /**
   * {@inheritDoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\user\Entity\User[] $selected_users */
    $selected_users = $this->configuration['selected_users'];

    if ($entity && !empty($selected_users)) {
      $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());

      // Arrays to keep track of users.
      $unflagged_users = [];

      foreach ($selected_users as $user) {
        $flag_service = $this->flagService;
        /** @var \Drupal\flag\Entity\Flag $flag */
        $flag = $flag_service->getFlagById(self::NOTIFICATION_FLAG_ID);
        // Flag the entity for the user.
        $flag_service->unflag($flag, $entity, $user);
        $unflagged_users[] = $user->getAccountName();
      }

      // Create messages based on unflagged users.
      $messages = [];
      if (!empty($unflagged_users)) {
        $unflagged_users_string = implode(', ', $unflagged_users);
        $messages[] = $this->t('Successfully unflagged node @id for user(s): @users', [
          '@id' => $entity->id(),
          '@users' => trim($unflagged_users_string),
        ]);
      }

      // Join messages with line breaks for clarity.
      return implode("\n", $messages);
    }

    return NULL;
  }
}
