<?php

namespace Drupal\epa_workflow\Plugin\Action;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagService;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

abstract class EPAFlagUsersActionBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  const NOTIFICATION_FLAG_ID = 'notification_opt_in';

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagService
   */
  protected $flagService;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * EPAFlagUsersActionBase constructor.
   *
   * @param \Drupal\flag\FlagService $flag_service
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagService $flag_service, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'selected_users' => NULL,
    ];
  }

  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($account->hasPermission('execute watch content on behalf of user')) {
      return AccessResult::allowed();
    }

    $grp_membership_service = \Drupal::service('group.membership_loader');
    /** @var \Drupal\group\GroupMembership[] $grps */
    $grps = $grp_membership_service->loadByUser($account);

    foreach ($grps as $grp) {
      if ($grp->hasPermission('execute watch content on behalf of user')) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['selected_users'] = [
      '#type' => 'entity_autocomplete',
      '#tags' => TRUE,
      '#title' => t('Select user(s) to watch on behalf of'),
      '#target_type' => 'user',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Gets the full user object and stores that in the configuration instead.
    foreach ($this->configuration['selected_users'] as $key => $value) {
      $user = $this->entityTypeManager->getStorage('user')->load($value['target_id']);
      $this->configuration['selected_users'][$key] = $user;
    }
  }

  /**
   * {@inheritDoc}
   */
  abstract public function execute();


  /**
   * {@inheritDoc}
   */
  public static function finished($success, array $results, array $operations): ?RedirectResponse {
    if ($success) {
      foreach ($results['operations'] as $item) {
        // Default fallback to maintain backwards compatibility:
        // if api version equals to "1" and type equals to "status",
        // previous message is displayed, otherwise we display exactly what's
        // specified in the action.
        if ($item['type'] === 'status' && $results['api_version'] === '1') {
          $message = static::translate('@operation.', [
            '@operation' => $item['message'],
          ]);
        }
        else {
          $message = new FormattableMarkup('@message', [
            '@message' => $item['message'],
          ]);
        }
        static::message($message, $item['type']);
      }
      $batch = &batch_get();
      /** @var \Drupal\Core\Url $redirect_url */
      $redirect_url = $batch['batch_redirect'];
      return new RedirectResponse($redirect_url->toString());
    }
    else {
      $message = static::translate('Finished with an error.');
      static::message($message, 'error');
    }
    return NULL;
  }

}
