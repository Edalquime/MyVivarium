FROM dunglas/frankenphp:php8.4-bookworm

RUN install-php-extensions \
    mysqli \
    pdo_mysql \
    mbstring \
    openssl

COPY . /app

WORKDIR /app

RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

RUN composer install --optimize-autoloader --no-scripts --no-interaction

CMD ["/start-container.sh"]
