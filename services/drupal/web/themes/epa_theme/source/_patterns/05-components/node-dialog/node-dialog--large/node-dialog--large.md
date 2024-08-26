---
el: .node-dialog--large
title: Node Dialog (Large)
state: in-progress
---
The node dialog fields contain the dropdowns for the drupal local tasks

__Variables:__
* is_demo: [boolean] Whether to show extra demo examples.
* modifier_classes: [string] Classes to modify the default component styling.
* nodeDialog: [array] Dialog items in a list. Each item contains:
	* title: [string] The title of the item
	* content: [object] The content of the item
	* url: [string] The item's URL if available
	* is_active: [boolean] True / False if the item is active
	* item_classes: [string] Classes to modify the default item styling