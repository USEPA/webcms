<?php declare(strict_types = 1);

namespace Drupal\epa_workflow\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Provides an epa publish guidance block.
 *
 * @Block(
 *   id = "epa_publish_guidance",
 *   admin_label = @Translation("EPA Publish Guidance"),
 *   category = @Translation("Custom"),
 * )
 */
final class EpaPublishGuidanceBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new CustomWysiwygBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $content = $config['custom_wysiwyg_body'] ?? '';

    // Render the WYSIWYG content with the text format.
    $renderable = [
      '#type' => 'processed_text',
      '#text' => $content['value'] ?? '',
      '#format' => $content['format'] ?? 'restricted_html',
    ];

    return [
      '#markup' => $this->renderer->renderPlain($renderable),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'custom_wysiwyg_body' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['custom_wysiwyg_body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#format' => isset($config['custom_wysiwyg_body']['format']) ? $config['custom_wysiwyg_body']['format'] : 'restricted_html',
      '#default_value' => isset($config['custom_wysiwyg_body']['value']) ? $config['custom_wysiwyg_body']['value'] : '',
      '#rows' => 10,
      '#required' => TRUE,
      '#allowed_formats' => ['filtered_html', 'restricted_html'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('custom_wysiwyg_body', $form_state->getValue('custom_wysiwyg_body'));
  }

}
