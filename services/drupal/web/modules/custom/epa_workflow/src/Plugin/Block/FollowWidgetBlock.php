<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a follow widget block.
 *
 * @Block(
 *   id = "epa_workflow_follow_widget",
 *   admin_label = @Translation("Follow Widget"),
 *   category = @Translation("Custom")
 * )
 */
class FollowWidgetBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The flag service.
   *
   * @var FlagServiceInterface
   */
  protected $flag;

  /**
   * Constructs a new FollowWidgetBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param FlagServiceInterface $flag
   *   The flag service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagServiceInterface $flag) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flag = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag.flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // @DCG Evaluate the access condition here.
    $condition = TRUE;
    return AccessResult::allowedIf($condition);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
