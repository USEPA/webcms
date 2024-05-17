<?php

namespace Drupal\Tests\content_moderation_notifications\Kernel;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Test sending of notifications for moderation state changes.
 *
 * @group content_moderation_notifications
 */
class NotificationsTest extends KernelTestBase {

  use AssertMailTrait;
  use ContentModerationNotificationCreateTrait;
  use ContentModerationNotificationTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'content_moderation_notifications',
    'content_moderation_notifications_test',
    'entity_test',
    'filter',
    'filter_test',
    'system',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installConfig(['content_moderation', 'filter_test']);
    $this->installSchema('system', ['sequences']);

    // Setup site email.
    $this->config('system.site')->set('mail', 'admin@example.com')->save();

    // Attach workflow to entity test.
    $this->enableModeration();

    // Add an admin user with the email 'bar@example.com'.
    $this->createUser([], NULL, TRUE, ['mail' => 'bar@example.com']);

    // Create a normal user with the email 'foo@example.com'.
    $this->createUser(['view test entity'], NULL, FALSE, ['mail' => 'foo@example.com']);

    // Create an anonymous user role. This isn't done by installing the user
    // module's config, as that triggers user registration emails, etc.
    Role::create(['id' => 'anonymous', 'status' => TRUE])->save();

    // Create the User entity for UID 1. This is necessary for the getOwner()
    // method to work as expected (which gets called once we start using the
    // 'author' flag in the notification.
    $this->setUpCurrentUser();
  }

  /**
   * Test sending of emails.
   */
  public function testEmailDelivery() {
    // No emails should be sent for content without notifications.
    $entity = EntityTestRev::create();
    $entity->save();
    $this->assertEmpty($this->getMails());

    // Add a notification.
    $long_email = $this->randomMachineName(128) . '@example.com';
    $notification = $this->createNotification([
      'emails' => 'foo@example.com, bar@example.com' . "\r\n" . $long_email,
      'transitions' => [
        'create_new_draft' => 'create_new_draft',
        'publish' => 'publish',
        'archived_published' => 'archived_published',
      ],
    ]);

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('draft', $entity->moderation_state->value);
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    // The adhoc emails should only include the admin user and the normal user.
    $this->assertFalse($entity->access('view', User::getAnonymousUser()));
    $this->assertBccRecipients('foo@example.com,bar@example.com');

    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(1, $this->getMails());

    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    // Only admin and the normal user with 'view' access should be emailed.
    $this->assertBccRecipients('foo@example.com,bar@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(2, $this->getMails());

    // Add anonymous ability to view test entities and resend.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('anonymous');
    $role->grantPermission('view test entity');
    $role->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    // Since anonymous users can view, the long email with no corresponding user
    // account should receive a notice.
    $this->assertBccRecipients('foo@example.com,bar@example.com,' . $long_email);
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(3, $this->getMails());

    // No mail should be sent for irrelevant transition.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('published', $entity->moderation_state->value);
    $entity->moderation_state = 'archived';
    $entity->save();
    $this->assertCount(3, $this->getMails());

    // Verify alter hook is functioning.
    // @see content_moderation_notifications_test_content_moderation_notification_mail_data_alter
    \Drupal::state()->set('content_moderation_notifications_test.alter', TRUE);
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $this->assertEquals('archived', $entity->moderation_state->value);
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients('altered@example.com,foo' . $entity->id() . '@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(4, $this->getMails());

    // Do not send notifications to the site email address if settings enabled.
    $notification->set('site_mail', TRUE)->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', '');
    $this->assertBccRecipients('altered@example.com,foo' . $entity->id() . '@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(5, $this->getMails());

    // Send notication to the site email address if settings disabled.
    $notification->set('site_mail', FALSE)->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients('altered@example.com,foo' . $entity->id() . '@example.com');
    $this->assertMail('id', 'content_moderation_notifications_content_moderation_notification');
    $this->assertMail('subject', PlainTextOutput::renderFromHtml($notification->getSubject()));
    $this->assertCount(6, $this->getMails());

    // Turn off the alter hook again.
    \Drupal::state()->set('content_moderation_notifications_test.alter', FALSE);
    // Enable the send-to-author setting and clear out the custom ad-hoc emails.
    $notification->set('author', TRUE)->set('emails', '')->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $owner = $entity->getOwner();
    $this->assertMail('from', 'admin@example.com');
    $this->assertMail('to', 'admin@example.com');
    $this->assertBccRecipients($owner->getEmail());
    $this->assertCount(7, $this->getMails());

    // Block the $owner user and try again.
    $owner->block()->save();
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test_rev')->loadUnchanged($entity->id());
    $entity->moderation_state = 'published';
    $entity->save();
    $this->assertCount(7, $this->getMails());
  }

  /**
   * Helper method to assert the Bcc recipients.
   *
   * @param string $recipients
   *   The expected recipients.
   */
  protected function assertBccRecipients($recipients) {
    $mails = $this->getMails();
    $mail = end($mails);
    $this->assertNotEmpty($mail['headers']['Bcc']);
    $this->assertEquals($recipients, $mail['headers']['Bcc']);
  }

}
