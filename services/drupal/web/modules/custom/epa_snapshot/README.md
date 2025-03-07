# EPA Tome

[Tome](https://www.drupal.org/project/tome) is the Drupal contrib module that can make a static backup of a Drupal site. EPA is utilizing this for their most recent (2025) snapshot site.

This supplemental module has an event listener that subscribes to a couple Tome events for making required modifications to the markup Tome generates.

The big alterations these event subscribers make are:
1. Excluding specific routes from being statically generated
2. Modifying the markup on pages per EPA's requirements
   1. Add a banner to all pages notifying users they are looking at a snapshot of the site.
   2. Modify all the metatags to point to the new snapshot domain
   3. Convert any absolute URLs that point to `https://epa.gov` to be relative paths.
   4. Removes all `<form>` elements given all form actions will not work.
   5. Add custom search markup that connects users to https://search.epa.gov/epasearch/ rather than an in-site search.

