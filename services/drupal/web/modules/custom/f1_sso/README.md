# Forum One - SSO customizations

This module provides a few customizations around the login process to ensure that a user logging in via saml is redirected to the "My Web Areas" page (/admin/content/my-web-areas) if a destination parameter is not already set. The key to this working is based on the value of an environment variable value `WEBCMS_SAML_FORCE_SAML_LOGIN`, being set in the `settings.php`.

Additionally, as part of the route subscriber in this module we block access to the user's password reset route, and Drupal's standard login route.

