# View Mode Select by Token

This module provides a FieldFormatter plugin that allows for rendering entity reference fields based on a token value. This was first developed for EPA as a custom module, but has since been released as a contrib module, see [https://www.drupal.org/project/view_mode_select_by_token](https://www.drupal.org/project/view_mode_select_by_token).
The idea being this module is to allow a content author the ability to select a "Style" from a dropdown list. The machine name for the options in this "Style" dropdown would correspond to a specific view mode. This in turn can be used when rendering an entity reference field to allow the chosen view mode to affect how the entity displays.

For specific implementation see the Manage Display for the "Before/After Swipe" paragraph type's "Manage Display" tab.
