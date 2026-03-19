FROM dunglas/frankenphp:php8.2-bookworm

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    mysqli \
    pdo_mysql \
    mbstring \
    openssl \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --prefer-dist --optimize-autoloader --no-scripts --no-interaction

COPY . .

COPY Caddyfile /etc/caddy/Caddyfile

RUN printf '#!/bin/sh\n\
cat > /app/.env << ENVEOF\n\
DB_HOST=${DB_HOST}\n\
DB_USERNAME=${DB_USERNAME}\n\
DB_PASSWORD=${DB_PASSWORD}\n\
DB_DATABASE=${DB_DATABASE}\n\
SMTP_HOST=${SMTP_HOST}\n\
SMTP_PORT=${SMTP_PORT}\n\
SMTP_USERNAME=${SMTP_USERNAME}\n\
SMTP_PASSWORD=${SMTP_PASSWORD}\n\
SMTP_ENCRYPTION=${SMTP_ENCRYPTION}\n\
SENDER_EMAIL=${SENDER_EMAIL}\n\
SENDER_NAME=${SENDER_NAME}\n\
DEMO=no\n\
ENVEOF\n\
frankenphp run --config /etc/caddy/Caddyfile\n' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
