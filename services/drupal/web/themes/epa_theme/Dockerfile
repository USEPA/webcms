# Node 24 LTS + PHP 8.3 to match theme build toolchain (was 3-node-v12-php-7.4)
FROM forumone/gesso:node-v24-php-8.3

COPY package*.json ./
RUN if test -e package-lock.json; then npm ci; else npm i; fi

COPY . .
