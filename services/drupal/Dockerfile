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
  && ls -1A | xargs rm -r \
  && mkdir /tmp/cache \
  && chown www-data:www-data /tmp/cache \
  && chmod 0700 /tmp/cache \
  && apk add --no-cache jq nginx tini \
  && mkdir -p /run/nginx

COPY nginx.conf /etc/nginx/conf.d/default.conf

COPY load.environment.php ./
COPY --from=build /app/scripts scripts
COPY --from=build /app/console console
COPY --from=build /app/vendor vendor
COPY --from=build /app/web web
COPY --from=build /app/composer.json /app/composer.lock ./
COPY ./config config

ENTRYPOINT ["tini", "-g", "--"]

CMD \
  S3_BUCKET="$(echo "$VCAP_SERVICES" | jq -r '.s3[0].credentials.bucket')" \
  && S3_REGION="$(echo "$VCAP_SERVICES" | jq -r '.s3[0].credentials.region')" \
  && sed -i \
    -e s/S3_BUCKET/"$S3_BUCKET"/ \
    -e s/S3_REGION/"$S3_REGION"/ \
    -e s/PORT/"$PORT"/ \
    /etc/nginx/conf.d/default.conf \
  && php-fpm -D \
  && exec nginx -g 'daemon off;'
