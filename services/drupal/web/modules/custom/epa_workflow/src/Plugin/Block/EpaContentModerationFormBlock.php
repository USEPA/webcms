<?php

namespace Drupal\epa_workflow\Plugin\Block;

use DateTimeZone;
use Drupal\content_moderation\Form\EntityModerationForm;
use Drupal\content_moderation\ModerationInformation;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\epa_workflow\ModerationStateToColorMapTrait;
use Drupal\node\NodeInterface;
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
  use ModerationStateToColorMapTrait;

  /**
   * The date time formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The content moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformationService;


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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatter $date_formatter, FormBuilderInterface $form_builder, ModerationInformation $moderation_information_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
    $this->formBuilder = $form_builder;
    $this->moderationInformationService = $moderation_information_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('form_builder'),
      $container->get('content_moderation.moderation_information'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getContextValue('node');

    $form = $this->formBuilder
      ->getForm(EntityModerationForm::class, $node);

    try {
      $moderation_state_id = $node->get('moderation_state')->getString();
      $box_color = $this->moderationStateToColorMap($moderation_state_id);
    }
    catch (\Exception $e) {
      // @todo log some error
      $box_color = 'yellow';
    }

    // @todo: Only display the review_deadline if the revision is published

    $build['content'] = [
      '#theme' => 'epa_content_moderation_form',
      '#box_color' => $box_color,
      '#current_state' => $this->getModerationStateLabel() ?? $this->t('No Workflow'),
      '#content_moderation_form' => $form,
      '#last_modified' => $this->buildLastModifiedByString($node),
      '#review_deadline' => $this->buildReviewByString($node),
      '#scheduled_publish' => $this->buildScheduledPublishString() ?? NULL,
      '#help_text' => Markup::create($this->t('This represents a moderation state. <a target="_blank" href=":url">Learn more about moderation states here ></a>', [':url' => 'https://www.epa.gov/webcmstraining/detailed-workflows-webcms']))
    ];
    return $build;
  }

  /**
   * Returns the workflow transition label based off the passed workflow state
   * machine name, or if none supplied, the given node's current state.
   *
   * @param string $state_key
   *   (Optional) The state key
   *
   * @return string
   *   The moderation state label.
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function getModerationStateLabel($state_key = '') {
    /** @var NodeInterface $node */
    $node = $this->getContextValue('node');
    $workflow = $this->moderationInformationService
      ->getWorkflowForEntity($node);

    if ($state_key == '') {
      $state_key = $node->get('moderation_state')->value;
    }

    try {
      return $workflow->getTypePlugin()
        ->getState($state_key)
        ->label();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Returns a "last authored on..." text for the current node revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function buildLastModifiedByString(NodeInterface $node) {
    return $this->t("Last modified on @date by @user",
      [
        '@date' => $this->buildFormalDatetimeString($node->getRevisionCreationTime()),
        '@user' => $node->getRevisionUser()->toLink(NULL, 'canonical',
            [
              'attributes' => [
                'class' => [
                  'my-class',
                ],
              ],
            ]
          )->toString(),
      ]
    );
  }

  public function buildReviewByString(NodeInterface $node) {
    /** @var \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem $review_deadline_timestamp */
    $review_deadline = $node->get('field_review_deadline')[0];
    if ($review_deadline) {
      /** @var \Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601 $value */
      $value  = $review_deadline->get('value');
      $timestamp = $value->getDateTime()->getTimestamp();
      return $this->t("Review by @review_deadline", ['@review_deadline' => $this->buildFormalDatetimeString($timestamp)]);
    }

    return '';

  }

  /**
   * Formats a timestamp to a specified format ('formal_datetime' by default).
   *
   * @param int $timestamp
   *   The timestamp to transform.
   * @param string $format
   *   (Optional) Defaults to our 'formal_datetime' format, i.e. September 12, 2024, 5:19 PM EDT.
   *
   * @return string
   *   The formatted datetime string.
   */
  public function buildFormalDatetimeString(int $timestamp, string $format = 'formal_datetime'): string {
    return $this->dateFormatter->format($timestamp, $format);
  }

  /**
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|void
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function buildScheduledPublishString() {
    $next_publish = $this->getNextScheduledPublish($this->getContextValue('node'));
    if ($next_publish) {
      if ($next_publish['moderation_state'] == 'published') {
        $scheduled_datetime = $next_publish['value'];
        $date_time = new DrupalDateTime($scheduled_datetime, new DateTimeZone('UTC'));
        return $this->t('Scheduled to Publish on @date',
          [
            '@date' => $this->buildFormalDatetimeString($date_time->getTimestamp()),
          ]
        );
      }
    }
  }


  /**
   * Helper method to return the next scheduled publish date value.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to look at for upcoming scheduled publish rows.
   *
   * @return array|null
   *   The first (next) scheduled publish value otherwise null if none.
   */
  public function getNextScheduledPublish(NodeInterface $node) {
    $scheduled_transitions = $node->get('field_scheduled_transition')->getValue();
    if (!empty($scheduled_transitions) && is_array($scheduled_transitions)) {
      return $scheduled_transitions[0];
    }

    return NULL;
  }
}
