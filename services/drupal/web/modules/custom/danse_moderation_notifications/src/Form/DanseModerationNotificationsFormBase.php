<?php

namespace Drupal\danse_moderation_notifications\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;

/**
 * Class ContentModerationNotificationFormBase.
 *
 * Typically, we need to build the same form for both adding a new entity,
 * and editing an existing entity. Instead of duplicating our form code,
 * we create a base class. Drupal never routes to this class directly,
 * but instead through the child classes of ContentModerationNotificationAddForm
 * and ContentModerationNotificationEditForm.
 *
 * @package Drupal\danse_moderation_notifications\Form
 *
 * @ingroup danse_moderation_notifications
 */
class DanseModerationNotificationsFormBase extends EntityForm {

  /**
   * Update options.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Formstate.
   *
   * @return mixed
   *   Returns the updated options.
   */
  public static function updateWorkflowTransitions(array $form, FormStateInterface &$form_state) {
    return $form['transitions_wrapper'];
  }

  /**
   * Update options.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Formstate.
   *
   * @return mixed
   *   Returns the updated options.
   */
  public static function updateGroupRoles(array $form, FormStateInterface &$form_state) {
    return $form['group_roles_wrapper'];
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   *
   * Builds the entity add/edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the danse_moderation_notifications
   *   add/edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve a list of all possible workflows.
    /** @var \Drupal\workflows\WorkflowInterface[] $workflows */
    $workflows = $this->entityTypeManager->getStorage('workflow')->loadMultiple();

    // Return early if there are no available workflows.
    if (empty($workflows)) {
      $form['no_workflows'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No workflows available. <a href=":url">Manage workflows</a>.', [':url' => Url::fromRoute('entity.workflow.collection')->toString()]),
      ];
      return $form;
    }

    // Get anything we need from the base class.
    $form = parent::buildForm($form, $form_state);

    // Drupal provides the entity to us as a class variable. If this is an
    // existing entity, it will be populated with existing values as class
    // variables. If this is a new entity, it will be a new object with the
    // class of our entity. Drupal knows which class to call from the
    // annotation on our ContentModerationNotification class.
    /** @var \Drupal\danse_moderation_notifications\ContentModerationNotificationInterface $danse_moderation_notifications */
    $danse_moderation_notifications = $this->entity;

    // Build the options array of workflows.
    $workflow_options = [];
    foreach ($workflows as $workflow_id => $workflow) {
      $workflow_options[$workflow_id] = $workflow->label();
    }

    // Default to the first workflow in the list.
    $workflow_keys = array_keys($workflow_options);

    if ($form_state->getValue('workflow')) {
      $selected_workflow = $form_state->getValue('workflow');
    }
    elseif (isset($danse_moderation_notifications->workflow)) {
      $selected_workflow = $danse_moderation_notifications->workflow;
    }
    else {
      $selected_workflow = array_shift($workflow_keys);
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $danse_moderation_notifications->label(),
      '#description' => $this->t('The label for this notification.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $danse_moderation_notifications->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$danse_moderation_notifications->isNew(),
    ];

    // Allow the workflow to be selected, this will dynamically update the
    // available transition lists.
    $form['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#options' => $workflow_options,
      '#default_value' => $selected_workflow,
      '#required' => TRUE,
      '#description' => $this->t('Select a workflow'),
      '#ajax' => [
        'wrapper' => 'workflow_transitions_wrapper',
        'callback' => static::class . '::updateWorkflowTransitions',
      ],
    ];

    // Ajax replaceable fieldset.
    $form['transitions_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="workflow_transitions_wrapper">',
      '#suffix' => '</div>',
    ];

    // Transitions.
    $state_transitions_options = [];
    $state_transitions = $workflows[$selected_workflow]->getTypePlugin()->getTransitions();
    foreach ($state_transitions as $key => $transition) {
      $state_transitions_options[$key] = $transition->label();
    }

    $form['transitions_wrapper']['transitions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Transitions'),
      '#options' => $state_transitions_options,
      '#default_value' => isset($danse_moderation_notifications->transitions) ? $danse_moderation_notifications->transitions : [],
      '#required' => TRUE,
      '#description' => $this->t('Select which transitions triggers this notification.'),
    ];

    // Role selection.
    $roles_options = user_role_names(TRUE);

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#options' => $roles_options,
      '#default_value' => $danse_moderation_notifications->getRoleIds(),
      '#description' => $this->t('Send notifications to all users with these roles.'),
    ];

    // Group module notification functionality.
    if ($this->moduleHandler->moduleExists('group')) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $group_type_storage */
      $group_type_storage = $this->entityTypeManager->getStorage('group_type');
      $form['group_use'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use group membership'),
        '#default_value' => $danse_moderation_notifications->isGroupUse(),
        '#description' => $this->t('Send notifications to members of any related group.'),
      ];

      // Build the options array of group types.
      $group_type_options = array_map(static function (GroupTypeInterface $group_type) {
        return $group_type->label();
      }, $group_type_storage->loadMultiple());

      // Default to the first group in the list.
      $group_type_keys = array_keys($group_type_options);
      $selected_group_type = array_shift($group_type_keys);
      if ($form_state->getValue('group_type')) {
        $selected_group_type = $form_state->getValue('group_type');
      }
      elseif (isset($danse_moderation_notifications->group_type)) {
        $selected_group_type = $danse_moderation_notifications->group_type;
      }

      // Allow the group type to be selected, this will dynamically update the
      // available group role lists.
      $form['group_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Group Types'),
        '#options' => $group_type_options,
        '#default_value' => $selected_group_type,
        '#description' => $this->t('Select a group type'),
        '#ajax' => [
          'wrapper' => 'group_roles_wrapper',
          'callback' => static::class . '::updateGroupRoles',
        ],
        '#states' => [
          'visible' => [
            "input[name='group_use']" => ['checked' => TRUE],
          ],
        ],
      ];

      // Ajax replaceable fieldset.
      $form['group_roles_wrapper'] = [
        '#type' => 'container',
        '#prefix' => '<div id="group_roles_wrapper">',
        '#suffix' => '</div>',
        '#states' => [
          'visible' => [
            "input[name='group_use']" => ['checked' => TRUE],
          ],
        ],
      ];

      // Group Role.
      $state_group_roles = [];
      if ($selected_group_type) {
        $state_group_roles = $this->entityTypeManager
          ->getStorage('group_role')
          ->loadByProperties(['group_type' => $selected_group_type]);
        // Remove internal roles except the member role.
        $state_group_roles = array_filter($state_group_roles, static function (GroupRoleInterface $group_role) {
          return ($group_role->id() == $group_role->getGroupTypeId() . '-member') || !$group_role->isInternal();
        });
      }

      $state_group_role_options = [];
      if (!empty($state_group_roles)) {
        // Build the options array of group types.
        $state_group_role_options = array_map(static function (GroupRoleInterface $role) {
          return $role->label();
        }, $state_group_roles);
      }

      $form['group_roles_wrapper']['group_roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Group Role'),
        '#options' => $state_group_role_options,
        '#default_value' => isset($danse_moderation_notifications->group_roles) ? $danse_moderation_notifications->group_roles : [],
        '#description' => $this->t('Send notifications to all users with these roles.'),
        '#states' => [
          'required' => [
            "input[name='group_use']" => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Send email to the original author?
    // Flag module notification modifications.
    if ($this->moduleHandler->moduleExists('flag')) {
      $form['flag_wrapper'] = [
        '#type' => 'container',
        '#prefix' => '<div id="flag_wrapper">',
        '#suffix' => '</div>',
      ];

      /** @var \Drupal\flag\Entity\Flag[] $flags */
      $flags = $this->entityTypeManager
        ->getStorage('flag')
        ->loadMultiple();
      $flag_options = [];
      foreach ($flags as $flag) {
        $flag_options[$flag->id()] = $flag->label();
      }

      $form['flag_wrapper']['flags'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Flag types'),
        '#options' => $flag_options,
        '#default_value' => isset($danse_moderation_notifications->flags) ? $danse_moderation_notifications->flags : [],
        '#description' => $this->t('Send notifications to all users who have flagged with these flags.'),
      ];
    }

    // Send email to author?
    $form['author'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email the original content author?'),
      '#default_value' => $danse_moderation_notifications->sendToAuthor(),
      '#description' => $this->t('Send notifications to the current author of the content.'),
    ];

    // Send email to the revision author?
    $form['revision_author'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email the revision author?'),
      '#default_value' => $danse_moderation_notifications->sendToRevisionAuthor(),
       '#description' => $this->t('Send notifications to the current author of the content.'),
     ];

    $form['site_mail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable the site email address'),
      '#default_value' => $danse_moderation_notifications->disableSiteMail(),
      '#description' => $this->t('Do not send notifications to the site email address.'),
    ];

    $form['emails'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('Adhoc email addresses'),
      '#default_value' => $danse_moderation_notifications->getEmails(),
      '#description' => $this->t('Send notifications to these email addresses. Separate emails with commas or newlines. You may use Twig templating code in this field.'),
    ];

    // Email subject line.
    $form['subject'] = [
      '#type' => 'textarea',
      '#rows' => 1,
      '#title' => $this->t('Email Subject'),
      '#default_value' => $danse_moderation_notifications->getSubject(),
      '#required' => TRUE,
      '#description' => $this->t('You may use Twig templating code in this field.'),
    ];

    // Email body content.
    $form['body'] = [
      '#type' => 'text_format',
      '#format' => $danse_moderation_notifications->getMessageFormat() ?: filter_default_format(),
      '#title' => $this->t('Email Body'),
      '#default_value' => $danse_moderation_notifications->getMessage(),
      '#description' => $this->t('You may use Twig templating code in this field.'),
    ];

    // Add token tree link if module exists.
    if ($this->moduleHandler->moduleExists('token')) {
      $form['body']['token_tree_link'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => array_unique(['user', $selected_workflow, 'node']),
        '#weight' => 10,
      ];
    }

    // Return the form.
    return $form;
  }

  /**
   * Checks for an existing danse_moderation_notifications.
   *
   * @param string|int $entity_id
   *   The entity ID.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this format already exists, FALSE otherwise.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    // Use the query factory to build a new entity query.
    $query = $this->entityTypeManager->getStorage('danse_moderation_notifications')->getQuery();

    // Query the entity ID to see if its in use.
    $result = $query->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute();

    // We don't need to return the ID, only if it exists or not.
    return (bool) $result;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * To set the submit button text, we need to override actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actins from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Return the result.
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   *
   * Saves the entity. This is called after submit() has built the entity from
   * the form values. Do not override submit() as save() is the preferred
   * method for entity form controllers.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function save(array $form, FormStateInterface $form_state) {
    // EntityForm provides us with the entity we're working on.
    $danse_moderation_notifications = $this->getEntity();

    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class. PHP
    // allows Drupal to do this even if the method is not defined ahead of
    // time.
    $status = $danse_moderation_notifications->save();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      $this->messenger()->addMessage($this->t('Notification <a href=":url">%label</a> has been updated.',
          [
            '%label' => $danse_moderation_notifications->label(),
            ':url' => $danse_moderation_notifications->toUrl('edit-form')->toString(),
          ]
      ));
      $this->logger('danse_moderation_notifications')->notice('Notification has been updated.', ['%label' => $danse_moderation_notifications->label()]);
    }
    else {
      // If we created a new entity...
      $this->messenger()->addMessage($this->t('Notification <a href=":url">%label</a> has been added.',
        [
          '%label' => $danse_moderation_notifications->label(),
          ':url' => $danse_moderation_notifications->toUrl('edit-form')->toString(),
        ]
      ));
      $this->logger('danse_moderation_notifications')->notice('Notification has been added.', ['%label' => $danse_moderation_notifications->label()]);
    }

    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.danse_moderation_notifications.collection');
  }

}
