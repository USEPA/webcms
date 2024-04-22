<?php

namespace Drupal\danse_moderation_notifications\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The enable/disable form for content moderation notification entities.
 */
class DisableForm extends EntityConfirmFormBase {

  /**
   * The content moderation notification entity to enable or disable.
   *
   * @var \Drupal\danse_moderation_notifications\ContentModerationNotificationInterface
   */
  protected $notification;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->notification = $this->entity;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->notification->status()) {
      return $this->t('Disable notification %label?', ['%label' => $this->notification->label()]);
    }

    return $this->t('Enable notification %label?', ['%label' => $this->notification->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->notification->status()) {
      return $this->t('Emails will not be sent for this notification when it is disabled.');
    }

    return $this->t('Emails will be sent for this notification when it is enabled.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->notification->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'danse_moderation_notifications_disable_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Toggle enable/disable.
    if ($this->notification->status()) {
      $this->notification->disable();
    }
    else {
      $this->notification->enable();
    }
    $this->notification->save();

    $form_state->setRedirect('entity.danse_moderation_notifications.collection');
  }

}
