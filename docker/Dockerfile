# PHP 8.4 with Composer: the harness installs this package and every omniplay provider at start.
FROM php:8.4-cli-alpine
RUN apk add --no-cache git unzip && git config --system --add safe.directory '*'
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 COMPOSER_CACHE_DIR=/harness/.cache
WORKDIR /harness
ENTRYPOINT ["/omniplay/core/docker/entrypoint.sh"]
CMD ["test"]
