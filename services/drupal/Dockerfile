FROM forumone/drupal8:7.3 AS memcached-ext

# This build stage builds and installs the AWS Elasticache client for Memcached. It's a
# manual clone & build process for two reasons:
# 1. The client cannot be found on PECL, and
# 2. We cannot use precompiled artifacts due to differences in libc implementations (glibc vs. musl).

# cf. https://github.com/awslabs/aws-elasticache-cluster-client-libmemcached
# cf. https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php/tree/php7

# Change the working directory to / from /var/www/html
WORKDIR /

# First, install the tools we need to build and link the two libraries.
RUN apk add --no-cache $PHPIZE_DEPS git zlib-dev libevent-dev g++

# Second, clone and build AWS' libmemcached fork. Aside from the `sed' invocation (see
# comment below), this hews to that repo's instructions.
RUN set -ex \
  && git clone --depth 1 https://github.com/awslabs/aws-elasticache-cluster-client-libmemcached.git \
  && cd aws-elasticache-cluster-client-libmemcached \
  # Patch cmdline.cc per https://github.com/awslabs/aws-elasticache-cluster-client-libmemcached/pull/4
  # The '65' here refers to the line where the declaration 'static char **environ' can be
  # found - we need to remove the 'static' declaration in order to match libmusl's
  # declaration.
  && sed -ie '65s/static //' libtest/cmdline.cc \
  && touch configure.ac aclocal.m4 configure Makefile.am Makefile.in \
  && mkdir BUILD \
  && cd BUILD \
  && ../configure --prefix=/usr/local/libmemcached --with-pic --disable-sasl \
  && make -j$(nproc) \
  && make install

# Next, clone and build the cluster client.
RUN set -ex \
  && git clone --branch php7 --depth 1 https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php.git \
  && cd aws-elasticache-cluster-client-memcached-for-php \
  && phpize \
  && ./configure --with-libmemcached-dir=/usr/local/libmemcached --disable-memcached-sasl \
  # Replace link to libmemcached with static library; lets us fully remove the AWS libmemcached fork (see README)
  && sed -i "s#-lmemcached#/usr/local/libmemcached/lib/libmemcached.a -lcrypt -lpthread -lm -lstdc++#" Makefile \
  && make -j$(nproc) \
  && make install

# At this point, we now have a fully-realized "memcached.so"
# available in the PHP extensions directory. It will be copied into the dev, base, and
# drush build stages.

FROM forumone/drupal8:7.3-xdebug AS dev

# Development-only dependencies/config here

# Copy and enable the AWS Elasticache memcached extension
COPY --from=memcached-ext \
  /usr/local/lib/php/extensions/no-debug-non-zts-20180731/memcached.so \
  /usr/local/lib/php/extensions/no-debug-non-zts-20180731/
RUN docker-php-ext-enable memcached

# APK packages needed by this image
RUN f1-ext-install pecl:apcu builtin:sockets \
  && apk add --no-cache \
  # Needed by the Elasticache memcached client
  libstdc++ \
  # GNU wget to override BusyBox
  wget

# Note that any configuration applied to the prod image must be mirrored here, due to the
# difference in base layers.
RUN set -ex \
  # This construct echoes the configuration lines to the console as well as outputting them
  # to the ini file that PHP-FPM reads.
  && { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini \
  # Enable special PHP-FPM status pages:
  && sed -i \
    # 1. The 'ping' endpoint responds with 'pong' (useful for health checks)
    -e 's/^;\(ping\.path =\)/\1/' \
    # 2. The 'status' endpoint response with metrics related to this FPM worker pool
    -e 's/^;\(pm\.status_path =\)/\1/' \
    /usr/local/etc/php-fpm.d/www.conf

# Set the php-fpm log format. This was largely reverse-engineered from fpm_log.c in the
# PHP sources. Notes on the available log options:
# * %C - CPU percentage
#   * %{user}C - user CPU percentage
#   * %{system}C - system CPU percentage (i.e., time spent in system calls)
#   * %{total}C - total CPU (same as %C by itself)
# * %d - request duration (in seconds)
#   * %{<format>}d - duration in <format> (seconds, miliseconds [sic], microseconds)
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

FROM forumone/drupal8:7.3 AS base

# This is the base production layer. We use it here in order to make it easier to ensure
# that both the dev and base targets are in sync with each other.

# Copy and enable the AWS Elasticache memcached extension
COPY --from=memcached-ext \
  /usr/local/lib/php/extensions/no-debug-non-zts-20180731/memcached.so \
  /usr/local/lib/php/extensions/no-debug-non-zts-20180731/
RUN docker-php-ext-enable memcached

# APK packages needed by this image
RUN f1-ext-install pecl:apcu builtin:sockets \
  && apk add --no-cache \
  # Needed by the Elasticache memcached client
  libstdc++ \
  # GNU wget to override BusyBox
  wget

RUN set -ex \
  && { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=1G'; \
    echo 'post_max_size=1G'; \
  } | tee /usr/local/etc/php/php-fpm-fcgi.ini \
  && sed -i \
    -e 's/^;\(ping\.path =\)/\1/' \
    -e 's/^;\(pm\.status_path =\)/\1/' \
    /usr/local/etc/php-fpm.d/www.conf

# See notes above
RUN set -ex \
  && { \
    echo 'access.format="[%T] %R %m %r %{REQUEST_URI}e mem(bytes): %M cpu%%: %{total}C dur(secs): %d"'; \
  } | tee /usr/local/etc/php-fpm.d/zz-log.conf

# Install Composer dependencies
FROM composer:2 AS deps

WORKDIR /app

# Create a stub 'web' folder instead of copying to maximize cache usage
RUN mkdir web

# Copy "base" Composer scripts and patches first - we assume these change less frequently
# than composer.json and friends
COPY ./scripts scripts
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
COPY default.conf status.conf *.map /etc/nginx/conf.d/
COPY scripts/ecs/nginx-entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/bin/sh", "/entrypoint.sh"]

# Build a drush-specific image: this image includes command-line utilities such as mysql
# and ssh that are inappropriate for a server container image.
FROM forumone/drupal8-cli:7.3 AS drush

# Copy and enable the AWS Elasticache memcached extension
COPY --from=memcached-ext \
  /usr/local/lib/php/extensions/no-debug-non-zts-20180731/memcached.so \
  /usr/local/lib/php/extensions/no-debug-non-zts-20180731/
RUN docker-php-ext-enable memcached

# APK packages needed by this image
RUN f1-ext-install pecl:apcu builtin:sockets \
  && apk add --no-cache \
  # Needed by the Elasticache memcached client
  libstdc++

# Allow unlimited memory usage when running Drush tasks (ECS will constrain the memory
# instead of PHP)
RUN set -ex \
  && { \
    echo 'memory_limit=-1'; \
  } | tee /usr/local/etc/php/php-cli.ini

# Same as nginx: copy the built Drupal filesystem
COPY --from=drupal /var/www/html /var/www/html

# Copy the migration script into /usr/local/bin as a command
COPY scripts/ecs/drush-migrate.sh /usr/local/bin/drush-migrate
RUN chmod +x /usr/local/bin/drush-migrate
