## EPA Cloudfront

As part of https://forumone.atlassian.net/browse/EPAD8-1680 it was noted that essentially every edit of the node was causing the Cloudfront Path Invalidate module to invalidate the node. This is not preferred as this balloons costs of Cloudfront as edit upon edit would have to bubble to Cloudfront. This module adjusts this to ensure that Cloudfront only clears the path for a given node when it's moved to "Published" or "Unpublished" status.
