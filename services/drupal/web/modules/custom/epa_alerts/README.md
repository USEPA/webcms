# EPA Alerts

This module provides two custom blocks, "EPA internal alerts" and "EPA public alerts" that are used for displaying the "Alert" block type content entities.

_Note: Currently the internal alerts block is not in use as we're relying on a view for displaying the internal alerts within the admin theme._

This block is then paired with custom javascript in the `epa_theme` to dynamically load any alert content and display it on all pages. See https://github.com/USEPA/webcms/blob/main/services/drupal/web/themes/epa_theme/js/src/epa-alerts.es6.js

Essentially what this javascript is doing is using Drupal Ajax commands to execute a view (`/admin/structure/views/view/public_alerts`), parse the results, and then display the alerts in the shape of a [USWDS alert](https://designsystem.digital.gov/components/alert/). The view itself is responsible for finding the correct alerts to display based on publish status and date range set on the alert itself.
