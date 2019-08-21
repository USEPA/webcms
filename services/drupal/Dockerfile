FROM composer:1.8 AS build

WORKDIR /app

RUN composer global require hirak/prestissimo:=0.3.9

COPY ./scripts scripts
COPY ./console console
COPY ./web web
COPY composer.json composer.lock ./

RUN composer install --ignore-platform-reqs

FROM drupal:8.7.6-apache

WORKDIR /var/www/html

RUN set -ex \
  && mkdir /tmp/cache \
  && chown www-data:www-data /tmp/cache \
  && chmod 0700 /tmp/cache

RUN set -ex \
  && apt-get update \
  && apt install -y --no-install-recommends jq \
  && rm -rf /var/apt/lists/*

RUN ls -1A | xargs rm -rf

COPY httpd.conf /etc/apache2/sites-available/000-default.conf
COPY modules.conf /etc/apache2/conf-enabled/

COPY load.environment.php ./
COPY --from=build /app/scripts scripts
COPY --from=build /app/console console
COPY --from=build /app/vendor vendor
COPY --from=build /app/web web
COPY --from=build /app/composer.json /app/composer.lock ./
COPY ./config config

CMD \
  S3_BUCKET="$(echo "$VCAP_SERVICES" | jq -r '.s3[0].bucket')" \
  sed -e s/S3_BUCKET/"$S3_BUCKET"/ \
    /etc/apache2/sites-available/000-default.conf \
    > /etc/apache2/sites-enabled/000-default.conf \
  exec apache2-foreground
