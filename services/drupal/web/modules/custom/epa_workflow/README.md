# EPA Workflow

This module's core functionality is to provide a plugin system that allows alterations to nodes during specific content moderation updates. See `epa_workflow_content_moderation_state_update`. These alterations are critical for ensuring that content transitions properly to the next workflow state and sets critical meta information on the node. These alterations are ran via the `EPAModerationManager` and each workflow state has its own implementation that then triggers specific updates to the node when being transitioned to the next workflow state.

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

## Content Expiration
A core tenet of the WebCMS's content authoring workflow is that all content gets reviewed in at least a yearly cycle, if not sooner, and if not reviewed will automatically be un-published as it is deemed to be stale and no longer relevant.

Once a node is published, the content gets a review deadline set on it (see [EPAPublished::setReviewDeadline](src/EPAPublished.php)) that will cause the node to go un-published in the future if no manual action is taken to re-publish the node. To give content authors time to review there are three additional workflow states that occur between a node being published and automatically unpublished. These are meant to give content author teams time to review said content and make any updates that are necessary before re-publishing or deciding to let the content expire on its own.
These automatic transitions are handled via the contrib module [Scheduled Publish](https://www.drupal.org/project/scheduled_publish) and are used heavily in the custom moderation plugins.


