FROM drupal:8.7.6-fpm-alpine

RUN set -ex \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
  && pecl install xdebug \
  && apk del .build-deps \
  && apk add --no-cache tini nginx \
  && mkdir -p /run/nginx

ENTRYPOINT [ "tini", "-g", "--" ]
