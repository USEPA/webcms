FROM forumone/drupal8:7.3-xdebug AS dev

# Development-only dependencies/config here
# Note that any configuration applied to the prod image must be mirrored here, due to the
# difference in base layers.
RUN set -ex \
  # This construct echoes the configuration lines to the console as well as outputting them
  # to the ini file that PHP-FPM reads.
  && { \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini

FROM forumone/drupal8:7.3 AS base

# This is the base production layer. We use it here in order to make it easier to ensure
# that both the dev and base targets are in sync with each other.

RUN set -ex \
  && { \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini

# Install Composer dependencies
FROM forumone/composer:1.8 AS deps

WORKDIR /app

# Create a stub 'web' folder instead of copying to maximize cache usage
RUN mkdir web

COPY ./scripts scripts
COPY ./console console
COPY ./patches patches
COPY composer.json composer.lock composer.patches.json ./

RUN composer install \
  --ignore-platform-reqs \
  --no-dev \
  --optimize-autoloader

# Build Gesso
FROM forumone/gesso:php7.3-node12 AS gesso

WORKDIR /app

COPY web/themes/epa_theme/package*.json ./
RUN npm ci

COPY web/themes/epa_theme ./
RUN npx gulp build && rm -rf node_modules

FROM base AS drupal

# Copy Composer-installed packages
COPY --from=deps /app/web web
COPY --from=deps /app/vendor vendor
COPY --from=deps /app/scripts scripts

# Copy composer.json files
COPY --from=deps /app/composer.json /app/composer.lock /app/composer.patches.json ./

# Copy Gesso
COPY --from=gesso /app web/themes/epa_theme

COPY load.environment.php ./

# Copies performed here won't overwrite the build artifacts from previous
# stages: Docker merges the directories' contents intelligently.
COPY web web
COPY config config
COPY drush drush

# Build an nginx container that has the same view into the Drupal filesystem
# as well as its configuration file.
FROM nginx:mainline-alpine AS nginx

COPY --from=drupal /var/www/html /var/www/html

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY scripts/ecs/nginx-entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]

# Build a drush-specific image: this image includes command-line utilities such as mysql
# and ssh that are inappropriate for a server container image.
FROM forumone/drupal8-cli:7.3 AS drush

# Allow unlimited memory usage when running Drush tasks
RUN set -ex \
  && { \
    echo 'memory_limit=-1'; \
  } | tee /usr/local/etc/php/php-cli.ini

COPY --from=drupal /var/www/html /var/www/html
