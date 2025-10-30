FROM public.ecr.aws/docker/library/php:8.2-fpm-alpine AS base

# Bump system packages
RUN apk upgrade

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install base extensions needed for Drupal
RUN set -ex \
  && install-php-extensions \
    gd \
    opcache \
    pdo_mysql \
    zip \
  # Settings taken from the Docker library's Drupal image
  && { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.fast_shutdown=1'; \
  } > /usr/local/etc/php/conf.d/opcache-recommended.ini

WORKDIR /var/www/html

# PHP packages needed by this image
RUN install-php-extensions apcu sockets igbinary memcached \
  # APK packages needed by this image
  && apk add --no-cache \
  # Needed by the memcached extension
  libmemcached \
  # GNU wget to override BusyBox
  wget

ARG PHP_ERROR_REPORTING='E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED'

RUN set -ex \
  # This construct echoes the configuration lines to the console as well as outputting them
  # to the ini file that PHP-FPM reads.
  && { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
    echo "error_reporting=${PHP_ERROR_REPORTING}"; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini \
  # Enable special PHP-FPM status pages:
  && sed -i \
    # 1. The 'ping' endpoint responds with 'pong' (useful for health checks)
    -e 's/^;\(ping\.path =\)/\1/' \
    # 2. The 'status' endpoint response with metrics related to this FPM worker pool
    -e 's/^;\(pm\.status_path =\)/\1/' \
    /usr/local/etc/php-fpm.d/www.conf \
  && { \
    # Switch the PHP-FPM pool to a static group of 10 servers. Restart the
    # servers every 5,000 requests.
    echo 'pm = static'; \
    echo 'pm.max_children = 10'; \
    echo 'pm.max_requests = 5000'; \
  } >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Set the php-fpm log format. This was largely reverse-engineered from fpm_log.c in the
# PHP sources. Notes on the available log options:
# * %C - CPU percentage
#   * %{user}C - user CPU percentage
#   * %{system}C - system CPU percentage (i.e., time spent in system calls)
#   * %{total}C - total CPU (same as %C by itself)
# * %d - request duration (in seconds)
# * %{<format>}d - duration in <format> (seconds, milliseconds [sic], microseconds)
# * %{VAR}e - read $VAR from environment
# * %f - script filename
# * %m - HTTP method
# * %M - request memory (in bytes)
#   * %{<format>}M - memory in <format> (bytes, kilobytes, megabytes)
# * %q - request query string
# * %Q - '?' or '', depending on whether or not query string is present
# * %r - request URI (FastCGI's SCRIPT_NAME/DOCUMENT_NAME)
# * %R - remote IP
# * %t %T - timestamp
# * %u - user (HTTP basic auth user)
#
# cf. https://github.com/php/php-src/blob/php-7.3.20/sapi/fpm/fpm/fpm_log.c
RUN set -ex \
  && { \
    # Breakdown of this log format:
    # (1)                          (2)        (3) (4)        (5)                                (6)                  (7)         (8)
    # [27/Jul/2020:16:58:08 +0000] 172.18.0.7 GET /index.php /admin/dashboard?foo=hello%20world mem(bytes): 31457280 cpu%: 72.78 dur(secs): 0.137
    # 1. Request timestamp
    # 2. Remote IP
    # 3. HTTP method
    # 4. FastCGI script
    # 5. "actual" FastCGI request
    # 6. Memory used (in bytes)
    # 7. CPU percentage
    # 8. Request duration
    echo 'access.format="[%T] %R %m %r %{REQUEST_URI}e mem(bytes): %M cpu%%: %{total}C dur(secs): %d"'; \
  } | tee /usr/local/etc/php-fpm.d/zz-log.conf

# Install Composer dependencies
FROM public.ecr.aws/docker/library/composer:2 AS deps

WORKDIR /app

# Create a stub 'web' folder instead of copying to maximize cache usage
RUN mkdir web

# Copy "base" Composer scripts and patches first - we assume these change less frequently
# than composer.json and friends
COPY ./scripts scripts
COPY ./patches patches

# Copy Composer manifest files
COPY composer.json composer.lock composer.patches.json auth.json ./

# Perform a production-quality Composer build
RUN composer install \
  --ignore-platform-reqs \
  --no-dev \
  --optimize-autoloader

# Remove any Composer authentication file if present.
RUN rm auth.json

# Build Gesso, the WebCMS' theme
FROM forumone/gesso:node-v18-php-8.1 AS gesso

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

# Copy additional certificates for use by Drupal and bundle them into the system
# certificates
COPY tls /usr/share/ca-certificates/extra
RUN \
  for cert in $(cd /usr/share/ca-certificates; echo extra/*); do \
    echo "$cert" >> /etc/ca-certificates.conf; \
  done \
  && update-ca-certificates \
  # Append AWS' RDS certificate bundle to the bundle we verify in settings.php
  && curl -fsSL https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem >> /etc/ssl/cert.pem


# Add build metadata to the image. We expose two optional build args:
# * GIT_TAG, the named Git tag being built.
# * GIT_COMMIT, the Git commit being built.
#
# These args are converted to environment variables that can be read by, e.g.,
# settings.php to display to users the current running code.
ARG GIT_TAG=""
ARG GIT_COMMIT=""

ENV GIT_TAG=${GIT_TAG} GIT_COMMIT=${GIT_COMMIT}

# Build an nginx container that has the same view into the Drupal filesystem
# as well as its configuration file.
FROM public.ecr.aws/docker/library/nginx:mainline-alpine AS nginx

# Bump system packages
RUN apk upgrade

# Copy the Drupal filesystem into this image
COPY --from=drupal /var/www/html /var/www/html

# Copy nginx configuration
COPY status.conf *.map /etc/nginx/conf.d/

# Copy configuration templates
COPY default.conf /etc/nginx/templates/default.conf.template

# Copy nginx startup script
COPY scripts/ecs/nginx-entrypoint.sh /webcms-entrypoint

ENTRYPOINT [ "/webcms-entrypoint" ]
CMD [ "nginx", "-g", "daemon off;" ]

# Build a drush-specific image: this image includes command-line utilities such as mysql
# and ssh that are inappropriate for a server container image.
FROM public.ecr.aws/docker/library/php:8.2-cli-alpine AS drush

# Bump system packages
RUN apk upgrade

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install base PHP extensions for Drupal as well as tools needed for Drush CLI operations
RUN set -ex \
  && install-php-extensions \
    gd \
    opcache \
    pdo_mysql \
    zip \
    igbinary \
    memcached \
  && apk add --no-cache \
    mysql-client \
    openssh \
    rsync \
    bash \
  && { \
    echo 'memory_limit=-1'; \
  } > /usr/local/etc/php/php-cli.ini

ENV PATH=${PATH}:/var/www/html/vendor/bin
WORKDIR /var/www/html

# APK packages needed by this image
RUN install-php-extensions apcu sockets \
  && apk add --no-cache \
  # Needed by the memcached extension
  libmemcached  \
  # See ENTRYPOINT below
  tini

ARG PHP_ERROR_REPORTING='E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED'

RUN set -ex \
  && { \
    # Allow unlimited memory usage when running Drush tasks (ECS will constrain the memory
    # instead of PHP)
    echo 'memory_limit=-1'; \
    # Switch to our custom error_reporting level
    echo "error_reporting=${PHP_ERROR_REPORTING}"; \
  } | tee /usr/local/etc/php/php-cli.ini

# Same as nginx: copy the built Drupal filesystem
COPY --from=drupal /var/www/html /var/www/html

# Copy additional certificates for use by Drupal and bundle them into the system
# certificates
COPY tls /usr/share/ca-certificates/extra
RUN \
  for cert in $(cd /usr/share/ca-certificates; echo extra/*); do \
    echo "$cert" >> /etc/ca-certificates.conf; \
  done \
  && update-ca-certificates \
  && curl -fsSL https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem >> /etc/ssl/cert.pem

# Copy the migration script into /usr/local/bin as a command
COPY scripts/ecs/drush-migrate.sh /usr/local/bin/drush-migrate
RUN chmod +x /usr/local/bin/drush-migrate

# Wrap the entrypoint script with tini to allow graceful signal handling
ENTRYPOINT ["tini", "--", "docker-php-entrypoint"]

# Add build metadata to the image. See the comments for these ARG instructions
# in the Drupal stage for more details.
ARG GIT_TAG=""
ARG GIT_COMMIT=""

ENV GIT_TAG=${GIT_TAG} GIT_COMMIT=${GIT_COMMIT}

# Lower user
USER www-data
