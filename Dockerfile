FROM dunglas/frankenphp:php8.4-bookworm

RUN install-php-extensions \
    mysqli \
    pdo_mysql \
    mbstring \
    openssl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --optimize-autoloader --no-scripts --no-interaction

COPY . .

CMD ["/start-container.sh"]
