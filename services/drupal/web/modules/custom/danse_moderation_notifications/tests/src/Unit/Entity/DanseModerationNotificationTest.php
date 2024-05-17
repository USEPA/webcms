<?php

namespace Drupal\Tests\content_moderation_notifications\Unit\Entity;

use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\content_moderation_notifications\Entity\ContentModerationNotification;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the notification entity.
 *
 * @group content_moderation_notifications
 *
 * @coversDefaultClass \Drupal\content_moderation_notifications\Entity\ContentModerationNotification
 */
class ContentModerationNotificationTest extends UnitTestCase {

  use ProphecyTrait;
  /**
   * Test fixture.
   *
   * @var \Drupal\content_moderation_notifications\Entity\ContentModerationNotification
   */
  protected $notification;

  /**
   * Test data for the fixture.
   *
   * @var array
   */
  protected static $data = [
    'id' => 'foo',
    'roles' => [
      'authenticated' => 'authenticated',
      'biz' => 'biz',
    ],
    'workflow' => 'foo_bar',
    'transitions' => [
      'foo_to_bar',
      'bar_to_foo',
    ],
    'subject' => 'A test notification',
    'body' => [
      'value' => 'Test message body',
      'format' => 'test_format',
    ],
    'author' => TRUE,
    'revision_author' => TRUE,
    'emails' => 'foo@example.com',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();

    $this->notification = new ContentModerationNotification(static::$data, 'content_moderation_notification');
  }

  /**
   * @covers ::sendToAuthor
   */
  public function testSendToAuthor() {
    $this->assertEquals(TRUE, $this->notification->sendToAuthor());
  }

  /**
   * @covers ::sendToRevisionAuthor
   */
  public function testSendToRevisionAuthor() {
    $this->assertEquals(TRUE, $this->notification->sendToRevisionAuthor());
  }

  /**
   * @covers ::getEmails
   */
  public function testGetEmails() {
    $this->assertEquals('foo@example.com', $this->notification->getEmails());
  }

  /**
   * @covers ::getWorkflowId
   */
  public function testGetWorkflowId() {
    $this->assertEquals('foo_bar', $this->notification->getWorkflowId());
  }

  /**
   * @covers ::getRoleIds
   */
  public function testGetRoleIds() {
    $this->assertEquals(static::$data['roles'], $this->notification->getRoleIds());
  }

  /**
   * @covers ::getTransitions
   */
  public function testGetTransitions() {
    $this->assertEquals(static::$data['transitions'], $this->notification->getTransitions());
  }

  /**
   * @covers ::getSubject
   */
  public function testGetSubject() {
    $this->assertEquals(static::$data['subject'], $this->notification->getSubject());
  }

  /**
   * @covers ::getMessage
   */
  public function testGetMessage() {
    $this->assertEquals(static::$data['body']['value'], $this->notification->getMessage());
  }

  /**
   * @covers ::getMessageFormat
   */
  public function getMessageFormat() {
    $this->assertEquals(static::$data['body']['format'], $this->notification->getMessageFormat());
  }

  /**
   * @covers ::preSave
   */
  public function testPreSave() {
    $data = static::$data;
    $data['roles']['not_set'] = 0;
    $data['transitions']['not_set'] = 0;

    $notification = new ContentModerationNotification($data, 'content_moderation_notification');

    // Mock out some necessary services.
    $container = new ContainerBuilder();
    $entity_type = $this->prophesize(EntityTypeInterface::class)->reveal();
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getDefinition('content_moderation_notification')->willReturn($entity_type);
    $container->set('entity_type.manager', $entity_type_manager->reveal());

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->moduleExists('group')->willReturn(FALSE);
    $container->set('module_handler', $module_handler->reveal());

    \Drupal::setContainer($container);

    $storage = $this->prophesize(EntityStorageInterface::class);
    $query = $this->prophesize(QueryInterface::class);
    $query->execute()->willReturn([]);
    $query->condition('uuid', NULL)->willReturn($query->reveal());
    $storage->getQuery()->willReturn($query->reveal());
    $storage->loadUnchanged('foo')->willReturn($notification);
    $notification->preSave($storage->reveal());

    $this->assertEquals(static::$data['roles'], $notification->getRoleIds());
    $this->assertEquals(static::$data['transitions'], $notification->getTransitions());
  }

}
