---
el: .usa-header
title: Header
state: complete
---
See [https://designsystem.digital.gov/components/header/]() and
[https://components.designsystem.digital.gov/components/detail/header--default.html]().

__Variables:__
* is_extended: [boolean] Whether to use the extended header style.
* modifier_classes: [string] Classes to modify the default layout styling.
* nav_label: [string] ARIA label for the primary navigation.

__Blocks:__
* content: Twig block for content. This will include the Navbar and Nav, which
  are part of the USWDS header component.

__Dependencies:__
* [Navbar](../navbar/navbar.md)
* [Nav](../nav/nav.md)
