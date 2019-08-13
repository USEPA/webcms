FROM php:7.3-cli-stretch

RUN set -ex \
  && savedAptMark="$(apt-mark showmanual)" \
  && apt-get update \
  && apt-get install -y $PHPIZE_DEPS \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libpq-dev \
    libzip-dev \
  && docker-php-ext-configure gd \
    --with-freetype-dir=/usr \
    --with-jpeg-dir=/usr \
    --with-png-dir=/usr \
  && docker-php-ext-install -j $(nproc) gd opcache pdo_mysql pdo_pgsql zip \
  && apt-mark auto '.*' > /dev/null \
  && apt-mark manual $savedAptMark \
  && ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
    | awk '/=>/ { print $3 }' \
    | sort -u \
    | xargs -r dpkg-query -S \
    | cut -d: -f1 \
    | sort -u \
    | xargs -rt apt-mark manual \
  && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
  && apt-get install -y --no-install-recommends default-mysql-client openssh-client rsync \
  && rm -rf /var/lib/apt/lists/*
