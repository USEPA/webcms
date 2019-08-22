FROM php:7.3-cli-alpine

RUN set -ex \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS coreutils freetype-dev libjpeg-turbo-dev postgresql-dev libzip-dev \
  && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/ \
  && docker-php-ext-install -j $(nproc) gd opcache pdo_mysql pdo_pgsql zip \
  && runDeps="$(\
    scanelf --needed --nobanner --format '%n#p' --recursive /usr/local \
      | tr ',' '\n' \
      | sort -u \
      | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
  )" \
  && apk add --virtual .docker-phpexts-rundeps $runDeps \
  && apk del .build-deps \
  && apk add --no-cache mysql-client openssh rsync