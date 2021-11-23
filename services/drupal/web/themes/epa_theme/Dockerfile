FROM forumone/gesso:3-node-v12-php-7.4

COPY package*.json ./
RUN if test -e package-lock.json; then npm ci; else npm i; fi

COPY . .
