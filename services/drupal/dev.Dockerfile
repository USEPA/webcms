FROM drupal:8.7.6-fpm-alpine

RUN set -ex \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
  && pecl install xdebug \
  && apk del .build-deps

RUN set -ex \
  && apk add --no-cache tini nginx jq \
  && mkdir -p /run/nginx \
  && mkdir -p /var/log/php-fpm \
  && { \
    echo '[global]'; \
    echo 'error_log = /var/log/php-fpm/error.log'; \
    echo '[www]'; \
    echo 'listen = /var/run/fpm.sock'; \
    echo 'listen.mode = 0666'; \
    echo 'access.log = /var/log/php-fpm/access.log'; \
    echo 'php_admin_value[upload_max_filesize] = 1G'; \
    echo 'php_admin_value[post_max_size] = 1G'; \
  } | tee /usr/local/etc/php-fpm.d/zz-docker.conf \
  && { \
    echo 'memory_limit = -1'; \
  } | tee /usr/local/etc/php/php-cli.ini

COPY scripts/cloudfoundry/entrypoint.sh /entrypoint.sh
COPY scripts/cloudfoundry/cron-drush.sh /etc/periodic/hourly/cron-drush
COPY scripts/cloudfoundry/cron-truncate.sh /etc/periodic/daily/cron-truncate
RUN chmod +x /etc/periodic/hourly/cron-drush /etc/periodic/daily/cron-truncate

# Ensure drush is available in the $PATH
ENV PATH /usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/var/www/html/vendor/bin

ENTRYPOINT [ "tini", "-g", "--" ]
