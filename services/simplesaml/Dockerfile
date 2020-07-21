FROM php:7.4-apache

# Install dependencies - we need these for Composer to work

RUN set -ex \
  && apt update -y \
  && apt install -y unzip git

# Install composer as /usr/local/bin/composer

ENV COMPOSER_VERSION 1.10.9
ENV COMPOSER_CHECKSUM 70d6b9c3e0774b398a372dcb7f89dfe22fc25884e6e09ebf277286dd64cfaf35

RUN set -ex \
  && cd /tmp \
  && curl -fsLO https://getcomposer.org/download/1.10.9/composer.phar \
  && echo "${COMPOSER_CHECKSUM}  composer.phar" | sha256sum -c - \
  && install -D composer.phar /usr/local/bin/composer

# Install SimpleSAMLphp

ENV SIMPLESAML_VERSION 1.18.7
ENV SIMPLESAML_ARCHIVE simplesamlphp-${SIMPLESAML_VERSION}.tar.gz
ENV SIMPLESAML_CHECKSUM 0ca1c54eb0d46e143f52fe6f42a4f659af4b368122cd522d0e505e1d0e7da7d7

RUN set -ex \
  && mkdir -p /var/simplesamlphp \
  && cd /tmp \
  && curl -fsLO https://github.com/simplesamlphp/simplesamlphp/releases/download/v${SIMPLESAML_VERSION}/${SIMPLESAML_ARCHIVE} \
  && echo "${SIMPLESAML_CHECKSUM}  ${SIMPLESAML_ARCHIVE}" | sha256sum -c - \
  && tar xf ${SIMPLESAML_ARCHIVE} --strip-components=1 -C /var/simplesamlphp \
  && rm simplesamlphp-${SIMPLESAML_VERSION}.tar.gz

# Copy the SimpleSAMLphp Apache configuration and enable the vhost

COPY simplesaml.conf /etc/apache2/sites-available/simplesaml.conf
RUN set -ex \
  && a2dissite 000-default \
  && a2ensite simplesaml

# Copy the SimpleSAMLphp files

COPY config.php authsources.php /var/simplesamlphp/config/
COPY saml-private.pem saml-public.crt /var/simplesamlphp/cert/
COPY metadata.php /var/simplesamlphp/metadata/saml20-idp-hosted.php
