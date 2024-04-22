<?php

namespace Drupal\Tests\content_moderation_notifications\Kernel;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests with the contrib token module enabled.
 *
 * @group content_moderation_notifications
 *
 * @requires module token
 */
class TokenNotificationsTest extends NotificationsTest {

  use ContentModerationNotificationTestTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'node', 'text', 'token', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'system']);

    $this->createContentType(['type' => 'article']);

    // Setup site email.
    $this->config('system.site')->set('mail', 'admin@example.com')->save();

    $this->enableModeration('node', 'article');
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $workflow->save();
  }

  /**
   * Test token functionality.
   */
  public function testTokens() {
    // Add a notification.
    $notification = $this->createNotification([
      'emails' => 'foo@example.com, bar@example.com',
      'transitions' => [
        'create_new_draft' => 'create_new_draft',
        'publish' => 'publish',
        'archived_published' => 'archived_published',
      ],
      'body' => [
        'value' => 'Test token replacement [node:title]. [content_moderation_notifications:from-state] | [content_moderation_notifications:workflow] | [content_moderation_notifications:to-state]!',
        'format' => 'filtered_html',
      ],
    ]);

    $entity = $this->createNode(['type' => 'article']);

    $this->assertMail('to', 'admin@example.com');
    // The foo@example.com user does not have permission to see this.
    $this->assertBccRecipients('bar@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(1, $this->getMails());

    // Verify token replacement.
    $mail = $this->getMails()[0];
    $this->assertEquals('Test token replacement ' . $entity->label() . ". Draft | Editorial | Draft!\n", $mail['body']);

    // Publish.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $entity->moderation_state = 'published';
    $entity->save();
    $mail = $this->getMails()[0];
    $this->assertEquals('Test token replacement ' . $entity->label() . ". Draft | Editorial | Published!\n", $mail['body']);
  }

  /**
   * Test Twig functionality.
   */
  public function testTwig() {
    // Add a text field to the 'article' content type for adhoc email address.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'adhoc_email',
      'entity_type' => 'node',
      'type' => 'text',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'adhoc_email',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Adhoc email',
    ]);
    $field->save();

    // Add a notification.
    $notification = $this->createNotification([
      'emails' => 'foo@example.com, bar@example.com, {{ entity.adhoc_email.value }}',
      'transitions' => [
        'create_new_draft' => 'create_new_draft',
        'publish' => 'publish',
        'archived_published' => 'archived_published',
      ],
      'body' => [
        'value' => 'Test Twig replacement {{ entity.title.value }}. [content_moderation_notifications:from-state] | [content_moderation_notifications:workflow] | [content_moderation_notifications:to-state]!',
        'format' => 'filtered_html',
      ],
      'subject' => '{{ entity.bundle|title }} with ID {{ entity.id }} Needs review',
    ]);

    // Create admin user with adhoc email address so mail is sent.
    $this->createUser([], NULL, TRUE, ['mail' => 'adhoc1@example.com']);

    $entity = $this->createNode(
      [
        'type' => 'article',
        'adhoc_email' => 'adhoc1@example.com',
      ]
    );

    $this->assertMail('to', 'admin@example.com');
    // The foo@example.com user will not have access to this entity.
    $this->assertBccRecipients('bar@example.com,adhoc1@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', 'Article with ID ' . $entity->id() . ' Needs review');
    $this->assertCount(1, $this->getMails());

    // Verify token and Twig replacement in the message body.
    $mail = $this->getMails()[0];
    $this->assertEquals('Test Twig replacement ' . $entity->label() . ". Draft | Editorial | Draft!\n", $mail['body']);

    // Publish.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $entity->moderation_state = 'published';
    $entity->save();
    $mail = $this->getMails()[0];
    $this->assertEquals('Test Twig replacement ' . $entity->label() . ". Draft | Editorial | Published!\n", $mail['body']);
  }

}
