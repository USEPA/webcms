---
el: .usa-banner
title: Government Banner
state: complete
---
See [https://designsystem.digital.gov/components/banner/](),
[https://components.designsystem.digital.gov/components/detail/banner.html](),
and [https://designsystem.digital.gov/components/header/]().

__Variables:__
* banner_text: [string] Text displayed next to the flag.
* has_expanded: [boolean] Whether the banner has expanded content.
* banner_action: [string] Linked text used to hide and show expanded content.
* expanded_blocks: [object] Object containing optional blocks displayed if has_expanded is true.
  * icon: [string] Path to icon file.
  * icon_alt: [string] Alt text for icon.
  * heading: [string] Block heading.
  * content: [string] Block contain. Can contain HTML markup.

__Dependencies:__
* [Layout Grid](../../04-layouts/grid/grid.md)
* [Accordion](../accordion/accordion.md)
* [Media Block](../media-block/media-block.md)
