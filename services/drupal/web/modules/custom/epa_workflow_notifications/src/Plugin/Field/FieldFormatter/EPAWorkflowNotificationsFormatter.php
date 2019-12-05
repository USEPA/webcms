<?php

namespace Drupal\epa_workflow_notifications\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\epa_workflow_notifications\Plugin\Field\FieldType\EPAWorkflowNotificationsItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'epa_workflow_notifications_formatter'.
 *
 * @FieldFormatter(
 *   id = "epa_workflow_notifications_formatter",
 *   label = @Translation("EPA workflow notifications formatter"),
 *   field_types = {
 *     "epa_workflow_notifications"
 *   }
 * )
 */
class EPAWorkflowNotificationsFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * EPAWorkflowNotificationsFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any Third party settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, array $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, EntityTypeManager $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->logger = $logger_factory->get('epa_workflow_notifications');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Implement default settings.
      'date_format' => 'html_datetime',
      'text_pattern' => '%notification_type% - %date%',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [
      'date_format' => [
        '#title' => $this->t('Date format'),
        '#type' => 'select',
        '#options' => $this->getDateFormats(),
        '#default_value' => $this->getSetting('date_format'),
      ],
      'text_pattern' => [
        '#title' => $this->t('Text replace pattern'),
        '#type' => 'textfield',
        '#default_value' => $this->getSetting('text_pattern'),
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      '#markup' => $this->t('Displays date in a custom format')
        ->render(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    $strDateFormat = $this->getSetting('date_format');
    $strTextPattern = $this->getSetting('text_pattern');

    foreach ($items as $delta => $item) {
      /**
       * @var $item \Drupal\epa_workflow_notifications\Plugin\Field\FieldType\EPAWorkflowNotificationsItem
       */
      $rawValue = $item->getValue();
      $dateTime = $rawValue['value'];
      $notificationType = $rawValue['notification_type'];
      $elements[$delta] = [
        '#markup' => $this->parseData($dateTime, $strDateFormat, $notificationType, $strTextPattern),
      ];
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item): string {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return nl2br(Html::escape($item->value));
  }

  /**
   * Get available date formats.
   *
   * @return array
   *   Array of date formats.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getDateFormats(): array {
    $formats = [];
    $dateFormats = $this->entityTypeManager->getStorage('date_format')
      ->loadMultiple();
    foreach ($dateFormats as $dateFormat) {
      $formats[$dateFormat->id()] = $dateFormat->get('label');
    }
    return $formats;
  }

  /**
   * Process data for display.
   *
   * @param string $strDateTime
   *   Date to display.
   * @param string $strDateFormat
   *   Format to display date.
   * @param string $notificationType
   *   The notifcation type to display.
   * @param string $pattern
   *   The pattern for display.
   *
   * @return string
   *   String to be displayed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function parseData(string $strDateTime, string $strDateFormat, string $notificationType, string $pattern) {
    $date = $this->parseDate($strDateTime, $strDateFormat);
    return str_replace(['%notification_type%', '%date%'], [
      $notificationType,
      $date,
    ], $pattern);
  }

  /**
   * Process date for display.
   *
   * @param string $strDateTime
   *   Date to display.
   * @param string $strDateFormat
   *   Format to display date.
   *
   * @return \DateTime
   *   Date as datetime object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function parseDate(string $strDateTime, string $strDateFormat) {
    $dateFormat = $this->entityTypeManager->getStorage('date_format')
      ->load($strDateFormat);
    if ($dateFormat !== NULL) {
      $pattern = $dateFormat->getPattern();
      $drupalDateTime = DrupalDateTime::createFromFormat(EPAWorkflowNotificationsItem::DATETIME_STORAGE_FORMAT, $strDateTime);
      return $drupalDateTime->format($pattern);
    }
    $this->logger->error($this->t('Date format: @date_format could not be found!', ['@date_format' => $this->getSetting('date_format')]));
    return NULL;
  }

}
