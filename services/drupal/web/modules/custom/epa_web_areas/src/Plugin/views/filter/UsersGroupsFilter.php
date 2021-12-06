<?php

namespace Drupal\epa_web_areas\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters nodes based on user's groups
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("epa_web_areas_users_groups")
 */
class UsersGroupsFilter extends InOperator implements ContainerFactoryPluginInterface {

  protected $valueFormType = 'checkbox';

  /**
   * Group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * UsersGroupsFilter constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupMembershipLoaderInterface $group_membership_loader) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupMembershipLoader = $group_membership_loader;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return \Drupal\epa_web_areas\Plugin\views\filter\UsersGroupsFilter|\Drupal\views\Plugin\views\PluginBase
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('group.membership_loader')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Filter on Groups the current user belongs to.');
    $this->definition['options callback'] = [$this, 'generateOptions'];
    $this->currentDisplay = $view->current_display;
  }

  /**
   * {@inheritDoc}
   */
  public function query() {
    if (!empty($this->value)) {
      $groups = $this->getAllGroupsByUser();
      if (empty($groups)) $groups = [-1];
      $this->ensureMyTable();
      $this->query->addWhere($this->options['group'], $this->tableAlias . '.' . $this->realField, $groups, 'IN');
    }
  }

  public function valueForm(&$form, FormStateInterface $form_state) {
    $default_value = $this->value;
    $exposed = $form_state->get('exposed');

    if ($exposed) {
      $identifier = $this->options['expose']['identifier'];
      $form['value']['#type'] = 'checkbox';
      $form['value'] = [
        '#type' => 'checkbox',
        '#title' => 'Limit content to my web areas',
        '#default_value' => $default_value,
      ];
      $user_input = $form_state->getUserInput();
      if (!isset($user_input[$identifier])) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }
  }


  /**
   * Helper to generate options for our filter.
   *
   * @return array
   *   Array of options.
   */
  public function generateOptions(): array {
    return [
      0 => $this->t('All web areas'),
      1 => $this->t('In my Web Areas'),
    ];
  }

  /**
   * Gets all Group entity IDs for the currently logged in user.
   *
   * @return array
   *   Group IDs the current user is a member of.
   */
  private function getAllGroupsByUser(): array {
    $groups = [];
    $memberships = $this->groupMembershipLoader->loadByUser();
    foreach ($memberships as $group) {
      $groups[] = $group->getGroup()->id();
    }

    return $groups;
  }

}
