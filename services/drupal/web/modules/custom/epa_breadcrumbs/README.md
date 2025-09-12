# EPA Breadcrumbs

This module provides a custom breadcrumb builder plugin that outputs breadcrumbs
based on the Web Area (Group) Menu of the node.

You can see most of the rules for this in the following Jira ticket https://forumone.atlassian.net/browse/EPAD8-2220.

Each Web Area is allowed to have a menu in it and content authors are allowed to structure their menu however they see fit.


The breadcrumb structure typically follows:
`Home / [Web Area Homepage] / Group Menu Parent Page / Group Menu Child Page / Group Menu Grand Child Page`

The Web Area's Homepage can be found when viewing a Web Area's edit page (/group/7/edit -- see "Homepage" entity reference field).

We then build the rest of the breadcrumbs based on the group's menu with a **max of up to 3** links deep (Menu parent, child, grand child).
