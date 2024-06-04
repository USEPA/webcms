<?php

namespace Drupal\epa_workflow\Plugin\Block;

use Drupal\content_moderation\Form\EntityModerationForm;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an epa content moderation form block.
 *
 * @Block(
 *   id = "epa_workflow_content_moderation_form",
 *   admin_label = @Translation("EPA Content Moderation Form"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = TRUE, label = @Translation("Node"))
 *   }
 * )
 */
class EpaContentModerationFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new EpaContentModerationFormBlock instance.
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
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $entity = $this->getContextValue('node');
    // Get the current moderation state name and if available get the review deadline
    if (!$entity->get('moderation_state')->isEmpty()) {
      $current_state = $entity->get('moderation_state')->value;
      /** @var \Drupal\workflows\WorkflowInterface $workflow */
      $workflow = \Drupal::service('content_moderation.moderation_information')
        ->getWorkflowForEntity($entity);

      $current = [
        '#type' => 'item',
        '#title' => t('Moderation state'),
        '#markup' => $workflow->getTypePlugin()
          ->getState($current_state)
          ->label(),
      ];
    }

    if ($entity->hasField('field_review_deadline')) {
      $review_deadline = $entity->get('field_review_deadline')->value;
    }

    $form = $this->formBuilder
      ->getForm(EntityModerationForm::class, $entity);

    $form['revision_log']['#resizable'] = 'none';
    $form['revision_log']['#rows'] = 10;
    $form['revision_log']['#cols'] = 10;

    $build['content'] = [
      '#theme' => 'epa_content_moderation_form',
      '#content_moderation_form' => $form,
    ];
    return $build;
  }

}
