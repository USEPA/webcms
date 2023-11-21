<?php

namespace Drupal\epa_web_areas\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_clone\EntityCloneSettingsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AllowedBulkChangeNodesForm extends ConfigFormBase implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The allowed entity type IDs that we can bulk change.
   *
   * @var string[]
   */
  protected $allowedEntityTypes = [
    'node',
    'media',
  ];

  protected $conditionalAllowedEntityBundles = [
    'node' => [
      'perspective',
      'speeches',
      'news_release',
    ],
    'media' => [],
  ];

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\entity_clone\EntityCloneSettingsManager $entity_clone_settings_manager
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'epa_allowed_bulk_change_nodes';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['epa_web_areas.allowed_bulk_change'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('epa_web_areas.allowed_bulk_change');

    foreach ($this->allowedEntityTypes as $entity_type_id) {
      $bundles = [];
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if ($entity_type->hasKey('bundle')) {
        $bundle_entity_type_id = $entity_type->getBundleEntityType();
        $storage = $this->entityTypeManager->getStorage($bundle_entity_type_id);
        $bundle_entities = $storage->loadMultiple();

        foreach ($bundle_entities as $bundle_entity) {
          if (in_array($bundle_entity->id(), $this->conditionalAllowedEntityBundles[$entity_type_id])) {
            $bundles[$bundle_entity->id()] = $bundle_entity->label() . " - <em>this bundle is enabled on a per Web Area basis and can only be changed if the target Web Area also allows this bundle.</em>";
          }
          else {
            $bundles[$bundle_entity->id()] = $bundle_entity->label();
          }
        }
      }

      $form['allowed_bundles'][$entity_type_id] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Allowed bulk change for bundles of @entity_type', ['@entity_type' => $entity_type->getLabel()]),
        '#options' => $bundles,
        '#default_value' => $config->get($entity_type_id) ?? NULL,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('epa_web_areas.allowed_bulk_change');
    foreach ($this->allowedEntityTypes as $type) {
      $config->set($type, $form_state->getValue($type));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
