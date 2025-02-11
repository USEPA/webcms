# EPA Workflow

This module's functionality is to provide the necessary "glue" to orchestrate several contrib modules together (scheduled publish, content moderation notifications, workflow) to provide an automated content review scheduling system. This is done via a plugin system that manages alterations to nodes during content moderation inserts and updates. See `epa_workflow_content_moderation_state_insert` and `epa_workflow_content_moderation_state_update`. These alterations are critical for ensuring that content transitions properly to the next workflow state and sets critical meta information on the node. These alterations are ran via the `EPAModerationManager` (see below) and each workflow state has its own implementation that then triggers specific business rule logic to the node when being transitioned to the next workflow state.

For a high level overview of the workflow process review the flow diagram in the docs folder [here](docs/EPA-worfklow-diagram.png)

## Workflow
All content follows the following workflow states.

- Draft
- Draft, needs review
- Draft, approved
- Published
- Published, expiring within 3 weeks
- Published, expiring within 1 week
- Published, expiring within 1 day
- Unpublished

The three "Published, expiring within..." states are automated states that are set based on a `field_review_deadline`. This field is not capable of being authored by anyone and is set programmatically when a node is published. This field is essentially our "timer" we'll have for determining when a content should be retired from the site. More on this below in the "Content Expiration" section.

## Content Expiration
A core tenet of the WebCMS's content authoring workflow is that all content gets reviewed in at least a yearly cycle, and if not reviewed will automatically be un-published as it is deemed to be stale and no longer relevant.

Once a node is published, the content gets a review deadline set on it (see [EPAPublished::setReviewDeadline](src/EPAPublished.php)) that will cause the node to go un-published in the future if no manual action is taken to re-publish the node.

To give content authors time to review there are three additional workflow states that occur between a node being published and automatically unpublished. These are meant to give Web Areas authors time to review said content and make any updates that are necessary before re-publishing or deciding to let the content expire on its own.

These automatic transitions are handled via the contrib module [Scheduled Publish](https://www.drupal.org/project/scheduled_publish) and are used heavily in the custom moderation plugins.

## Schedule Publish Customizations
This module provides a service decorator that replaces the scheduled_publish cron service with our own (see [EPAScheduledPublishCron](src/EPAScheduledPublishCron.php)). The scheduled_publish module works by providing a cron job that when triggered will iterate over all entities that contain a "scheduled_publish" field type and checks if the date on that scheduled_publish field is in the past. However, it only does this on the current_revision of a node and not necessarily the latest_revision of a node.

Our override adds additional logic to also ensure that we act on the latest_revision as well as the current_revision as they may have different scheduled transitions. As an example a node that is currently published will have a scheduled transition for moving from "Published" to "Published, expiring within 3 weeks". However, content authors can create newer (forward) revisions which can have their own scheduled publication date which we also want to respect.

## Scheduling publication
All content types in the system share a common `field_scheduled_transition` field which is a `Scheduled publish` field type. This field is used in conjunction with `field_publish_date` (which all nodes also have) that enable content authors the ability to schedule publishing in the future.

Content authors do not have the ability to set any scheduled transitions themselves, but they can set the `field_publish_date` to a date they would like to have their node published. This field is checked when transitioning the workflow state specifically to `Draft, approved`. If a value is set for this `field_publish_date` field it will schedule a transition to `Published` on the set date.

## Content Moderation Notifications
As content moves between moderation states we want to inform relevant users about those content changes. To achieve this we leverage the [Content Moderation Notifications](https://drupal.org/project/content_moderation_notifications) contrib module for sending email notifications. This custom module provides additional functionality to the Content Moderation Notifications module in the form of alterations to the configuration form to enable sending notifications to users of a specific group role, users who have flagged the node, and original node author or revision author.

This module provides a service decorator that overrides the default service (`Drupal\content_moderation_notifications\Notification`) provided by Content Moderation Notifications and supplies our own. This is to ensure that notifications were not generated during the migration process and to ensure that notifications aren't duplicated by checking the "isSyncing" property because we are altering an entity that would require an additional save.

## EPA Moderation Manager
The `EPAModerationManager` is the plugin manager and orchestrator that is called to run it's `processEntity()` method every time a `content_moderation_state` entity is created or updated. This plugin manager holds definitions for the 6 other plugins which are tracked by matching their `moderationName` property to the machine name of a corresponding workflow state (`published`, `published_needs_review`, `draft_approved`, etc.).

For a more high level overview of the process see the workflow diagram in the docs [here](docs/EPA-worfklow-diagram.png).

## Debugging
For debugging purposes we do not want to have to wait up to a year for the content to make its way through the automated unpublishing process. To circumvent this, we've added a boolean field to users (`field_workflow_debugger`) that when set to TRUE, will make it so that review deadline is 2 days in the future from when said debugging user publishes the node. This
