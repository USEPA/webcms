#!/bin/sh

set -e

if test -n "$WEBCMS_BASIC_AUTH" && test "$WEBCMS_BASIC_AUTH" != '.'; then
  # If basic auth credentials were provided, create an htpasswd file from the
  # credentials and replace the auth with an on toggle for nginx.
  echo "$WEBCMS_BASIC_AUTH" >/etc/nginx/htpasswd

  # This should be a realm name but the response 'on' is sufficient for our
  # purposes.
  export WEBCMS_BASIC_AUTH=on
else
  # If credentials weren't provided, explicitly set
  export WEBCMS_BASIC_AUTH=off
fi

# Record status in the logs
echo "Basic auth status: $WEBCMS_BASIC_AUTH"

# Pass execution off to the default entrypoint (among other things, this does
# environment substitution which will consume the WEBCMS_BASIC_AUTH variable and
# others).
exec /docker-entrypoint.sh "$@"
