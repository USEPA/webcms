FROM forumone/drupal8:7.3-xdebug AS dev

# Development-only dependencies/config here

# Install gnu copy of wget to override copy provided by BusyBox.
RUN apk add --no-cache wget

# Note that any configuration applied to the prod image must be mirrored here, due to the
# difference in base layers.
RUN set -ex \
  # This construct echoes the configuration lines to the console as well as outputting them
  # to the ini file that PHP-FPM reads.
  && { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini

FROM forumone/drupal8:7.3 AS base

# This is the base production layer. We use it here in order to make it easier to ensure
# that both the dev and base targets are in sync with each other.

# Install gnu copy of wget to override copy provided by BusyBox.
RUN apk add --no-cache wget

RUN set -ex \
  && { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini

# Install Composer dependencies
FROM forumone/composer:1.8 AS deps

WORKDIR /app

# Create a stub 'web' folder instead of copying to maximize cache usage
RUN mkdir web

# Copy "base" Composer scripts and patches first - we assume these change less frequently
# than composer.json and friends
COPY ./scripts scripts
COPY ./console console
COPY ./patches patches

# Copy Composer manifest files
COPY composer.json composer.lock composer.patches.json ./

# Perform a production-quality Composer build
RUN composer install \
  --ignore-platform-reqs \
  --no-dev \
  --optimize-autoloader

# Build Gesso, the WebCMS' theme
FROM forumone/gesso:php7.3-node12 AS gesso

WORKDIR /app

# Install base npm dependencies (npm ci = npm clean-install)
COPY web/themes/epa_theme/package*.json ./
RUN npm ci

# Copy theme source files and perform a build
COPY web/themes/epa_theme ./
RUN set -ex \
  # Build theme
  && npx gulp build \
  # Remove node_modules - these are only needed for the build, so we save some size in the
  # images by removing theme here.
  && rm -rf node_modules

# Create the final Drupal 8 image - build this by specifying "docker build --target drupal"
FROM base AS drupal

# Copy Composer-installed packages
COPY --from=deps /app/web web
COPY --from=deps /app/vendor vendor
COPY --from=deps /app/scripts scripts

# Copy composer.json files
COPY --from=deps /app/composer.json /app/composer.lock /app/composer.patches.json ./

# Copy the built theme
COPY --from=gesso /app web/themes/epa_theme

# Copy the environment loader
COPY load.environment.php ./

# Copies performed here won't overwrite the build artifacts from previous
# stages: Docker merges the directories' contents intelligently.
COPY web web
COPY config config
COPY drush drush

# Build an nginx container that has the same view into the Drupal filesystem
# as well as its configuration file.
FROM nginx:mainline-alpine AS nginx

# Copy the Drupal filesystem into this image
COPY --from=drupal /var/www/html /var/www/html

# Copy the nginx configuration and the startup script
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY scripts/ecs/nginx-entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]

# Build a drush-specific image: this image includes command-line utilities such as mysql
# and ssh that are inappropriate for a server container image.
FROM forumone/drupal8-cli:7.3 AS drush

# Allow unlimited memory usage when running Drush tasks (ECS will constrain the memory
# instead of PHP)
RUN set -ex \
  && { \
    echo 'memory_limit=-1'; \
  } | tee /usr/local/etc/php/php-cli.ini

# Same as nginx: copy the built Drupal filesystem
COPY --from=drupal /var/www/html /var/www/html
