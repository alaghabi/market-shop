FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

RUN apk add --no-cache dcron git icu-dev libzip-dev nodejs npm postgresql-dev supervisor \
    && install-php-extensions intl opcache pcov pdo_pgsql redis zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY etc/docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY etc/cron/market-shop /etc/crontabs/root
COPY etc/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY etc/docker/frankenphp/docker-entrypoint.sh /usr/local/bin/market-shop-entrypoint

RUN chmod 0644 /etc/crontabs/root \
    && chmod +x /usr/local/bin/market-shop-entrypoint \
    && command -v node \
    && command -v npm \
    && command -v crond \
    && command -v supervisord

ENTRYPOINT ["/usr/local/bin/market-shop-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
