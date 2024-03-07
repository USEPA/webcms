FROM debian:stable-slim

RUN set -ex \
  # 1. Install packages needed for this container
  && apt-get update \
  && apt-get install --yes --no-install-recommends \
    ca-certificates \
    curl \
    jq \
    unzip \
  # 2. Download and install the latest AWS CLI
  && cd /tmp \
  && curl -fsSL https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip -o awscliv2.zip \
  && unzip awscliv2.zip \
  && ./aws/install \
  # 3. Clean up the CLI installer files
  && rm -rf ./aws awscliv2.zip \
  # 4. Remove unneeded packages by marking them as automatic and letting the
  #    'autoremove' command do its thing
  && apt-mark auto unzip \
  && apt-get autoremove --purge --yes \
  && rm -rf /var/apt/lists/*

COPY transform.jq /etc/transform.jq
COPY entrypoint.sh /bin/entrypoint.sh

ENTRYPOINT [ "/bin/sh", "/bin/entrypoint.sh" ]
