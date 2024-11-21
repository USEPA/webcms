<?php

namespace Drupal\epa_workflow\Form;

use Drupal\block\Entity\Block;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class EPAContentModerationEntityModerationForm implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\Core\Render\Renderer $renderer
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, Renderer $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('renderer'),
    );
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    $this->addCustomSubmitHandler($form);
    $node = $form_state->get('entity');

    if ($node) {
      $this->addGuidanceBlocks($form, $node);
      $this->addPublishDateField($form, $node);
    }

    $this->tweakFormClasses($form);
    $this->adjustStateOptions($form);
    $this->adjustRevisionLog($form);
  }

  /**
   * Adds custom submit handler based on the current route.
   */
  private function addCustomSubmitHandler(array &$form) {
    $route = $this->requestStack->getCurrentRequest()->attributes->get('_route');
    if (in_array($route, ['entity.node.canonical', 'entity.node.edit_form'])) {
      $form['#submit'] = ['_epa_workflow_node_view_moderation_form_submit'];
    }
  }

  /**
   * Adds guidance blocks based on the current moderation state.
   */
  private function addGuidanceBlocks(array &$form, $node) {
    $current_state = $node->moderation_state->value;
    $state_blocks = $this->getStateBlockMappings();

    foreach ($state_blocks as $block_id => $states) {
      if (in_array($current_state, $states['states'])) {
        $this->addGuidanceBlock($form, $block_id, $states);
      }
    }
  }

  /**
   * Helper to map states to corresponding block IDs.
   */
  private function getStateBlockMappings() {
    return [
      'epa_draft_needs_approval' => [
        'visible' => 'draft_needs_review',
        'states' => ['draft_approved', 'draft']
      ],
      'epa_draft_approved_guidance' => [
        'visible' => 'draft_approved',
        'states' => ['draft', 'draft_needs_review']
      ],
      'epa_publish_guidance' => [
        'visible' => 'published',
        'states' => ['draft', 'draft_needs_review', 'draft_approved']
      ],
      'epa_republish_guidance' => [
        'visible' => 'published',
        'states' => [
          'published_needs_review', 'published_expiring',
          'published_day_til_expire', 'unpublished',
        ]
      ]
    ];
  }

  /**
   * Adds a specific guidance block to the form.
   */
  private function addGuidanceBlock(array &$form, $block_id, $states) {
    $rendered_block_markup = $this->renderBlockMarkup($block_id);

    $form[$block_id] = [
      '#type' => 'item',
      '#markup' => $rendered_block_markup,
      '#states' => [
        'visible' => [':input[name="new_state"]' => ['value' => $states['visible']]],
        'invisible' => [':input[name="new_state"]' => ['!value' => $states['visible']]],
      ],
    ];
  }

  /**
   *
   */
  /**
   * Adds the publish date field container if needed.
   *
   * @param array $form
   *   The Form API render array.
   * @param \Drupal\node\NodeInterface $node
   *   The node for this content moderation form.
   *
   * @return void
   */
  private function addPublishDateField(array &$form, NodeInterface $node) {
    $field_definition = $node->getFieldDefinition('field_publish_date');
    $form['publish_date'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [':input[name="new_state"]' => ['value' => 'draft_approved']],
      ],
      'field_publish_date' => [
        '#type' => 'datetime',
        '#title' => 'Date Time',
        '#date_increment' => 1,
        '#date_timezone' => 'America/New_York',
        '#description' => $field_definition->getDescription() . '<p><strong>Note: </strong>Specify time in EDT</p>',
      ],
    ];
  }

  /**
   * Applies various CSS class tweaks to the form.
   *
   * @param array $form
   *   The Form API render array.
   *
   * @return void
   */
  private function tweakFormClasses(array &$form) {
    $form['new_state']['#title'] = t('Change state to');
    $form['new_state']['#wrapper_attributes']['class'][] = 'epa-content-moderation__fancy-select';

    $form['workflow_508_compliant']['#wrapper_attributes']['class'][] = 'epa-content-moderation__form-item';
    if (isset($form['submit'])) {
      $form['submit']['#attributes']['class'][] = 'button--primary';
    }

    if (isset($form['revision_log'])) {
      $form['revision_log']['#wrapper_attributes']['class'][] = 'epa-content-moderation__form-item';
    }
  }

  /**
   * Adjusts options for the state dropdown.
   *
   * @param array $form
   *   The Form API render array.
   *
   * @return void
   */
  private function adjustStateOptions(array &$form) {
    if (isset($form['new_state']['#options']['draft'])) {
      $form['new_state']['#options']['draft'] = t('Create new Draft');
    }

    $form['current']['#required'] = TRUE;
    $form['current']['#value'] = NULL;
    $form['new_state']['#required'] = TRUE;
  }

  /**
   * Adjusts the revision log field to meet new requirements.
   *
   * @param array $form
   *
   * @return void
   */
  private function adjustRevisionLog(array &$form) {
    if (isset($form['revision_log'])) {
      $form['revision_log']['#title'] = t('Revision notes');
      $form['revision_log']['#type'] = 'textarea';
      unset($form['revision_log']['#size']);
    }
  }

  /**
   * Renders a block's markup by ID.
   */
  private function renderBlockMarkup($block_id) {
    $block = Block::load($block_id);
    if ($block) {
      $block_content = $this->entityTypeManager
        ->getViewBuilder('block')
        ->view($block);
      return (string) $this->renderer->renderRoot($block_content);
    }
    return FALSE;
  }
}
