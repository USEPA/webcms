FROM composer:1.8 AS build

WORKDIR /app

RUN composer global require hirak/prestissimo:=0.3.9

COPY ./scripts scripts
COPY ./console console
COPY ./web web
COPY composer.json composer.lock ./

RUN composer install \
  --ignore-platform-reqs \
  --no-dev \
  --optimize-autoloader

FROM drupal:8.7.6-fpm-alpine

# CloudFoundry overwrites this port with a random number - we're doing this instead of
# the more usual EXPOSE 80 directive because we already have port 9000 listed, and CF
# only supports a single EXPOSE directive.
ENV PORT 80

WORKDIR /var/www/html

RUN set -ex \
  # Remove the contents of /var/www/html
  && ls -1A | xargs rm -r \
  # Create a cache directory for Twig that's outside of sites/default/files
  && mkdir /tmp/cache \
  && chown www-data:www-data /tmp/cache \
  && chmod 0700 /tmp/cache \
  # Install needed utilities
  && apk add --no-cache jq nginx tini \
  && mkdir -p /run/nginx /var/log/php-fpm \
  # Switch php-fpm to a Unix socket. It needs to be world-writable because the
  # nginx APK creates an 'nginx' user.
  # Doing this avoids issues where CloudFoundry assigns 9000 to $PORT, which would
  # otherwise be taken by PHP-FPM.
  # We also redirect logs to files (see the entrypoint script).
  && { \
    echo '[global]'; \
    echo 'error_log = /var/log/php-fpm/error.log'; \
    echo '[www]'; \
    echo 'listen = /var/run/fpm.sock'; \
    echo 'listen.mode = 0666'; \
    echo 'access.log = /var/log/php-fpm/access.log'; \
  } | tee /usr/local/etc/php-fpm.d/zz-docker.conf

COPY load.environment.php ./
COPY --from=build /app/scripts scripts
COPY --from=build /app/console console
COPY --from=build /app/vendor vendor
COPY --from=build /app/web web
COPY --from=build /app/composer.json /app/composer.lock ./
COPY ./config config

# Add the entrypoint script
COPY scripts/cloudfoundry/entrypoint.sh /entrypoint.sh

# Copy cron scripts into the /etc/periodic directories (these ship with busybox cron by default)
COPY scripts/cloudfoundry/cron-drush.sh /etc/periodic/hourly/cron-drush
COPY scripts/cloudfoundry/cron-truncate.sh /etc/periodic/daily/cron-truncate
RUN chmod +x /etc/periodic/hourly/cron-drush /etc/periodic/daily/cron-truncate

COPY nginx.conf /etc/nginx/conf.d/default.conf

# Ensure drush is available in the $PATH
ENV PATH /usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/var/www/html/vendor/bin

ENTRYPOINT [ "tini", "-g", "-s", "--" ]

CMD ["sh", "/entrypoint.sh"]
