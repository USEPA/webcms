# EPA Media
This module contains the various customizations needed for WebCMS content authors. Some high level goals of this are:
1. Obscure the existance of files by instead of throwing a 403 if trying to access a private file, throw a 404 instead
2. Allow media marked as "private" to be viewed when accessing a page with an "[Access Unpublished](https://www.drupal.org/project/access_unpublished)" token.
