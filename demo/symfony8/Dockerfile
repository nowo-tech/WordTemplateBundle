# FrankenPHP (Dunglas) - PHP 8.2 Alpine
# https://frankenphp.dev/docs/docker/
FROM dunglas/frankenphp:1-php8.2-alpine

RUN apk add --no-cache git unzip bash nodejs npm
# gd: required by phpoffice/phpword ^1.4
RUN install-php-extensions intl zip gd
RUN npm install -g pnpm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN git config --global --add safe.directory '*'

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile.dev

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
