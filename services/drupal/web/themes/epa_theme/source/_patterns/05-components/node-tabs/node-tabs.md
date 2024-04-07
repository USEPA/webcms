---
el: .node-tabs
title: Node Tabs
state: in-progress
---

__Variables:__
* is_demo: [boolean] Whether to show extra demo examples.
* modifier_classes: [string] Classes to modify the default component styling.
* tabs: [array] List items. Each item is an object containing:
  * title: [string] The title of the item.
  * url: [string] The item's URL if available.
  * icon: [string] The name of the icon, if there is one.
  * is_active: [boolean] True / False is the item is active.
  * children: [array] Children of the item.
  	* title: [string] The title of the item.
    * content: [object] The content of the item
  	* url: [string] The item's URL if available.
  	* is_active: [boolean] True / False is the item is active.
