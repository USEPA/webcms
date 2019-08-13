FROM drupal:8.7.6-apache

RUN set -ex \
  && savedAptMark="$(apt-mark showmanual)" \
  && apt-get update \
  && apt-get install --no-install-recommends -y $PHPIZE_DEPS \
  && pecl install xdebug

RUN set -ex \
  && mkdir /tmp/cache \
  && chown www-data:www-data /tmp/cache \
  && chmod 0700 /tmp/cache
