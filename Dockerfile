FROM dunglas/frankenphp:php8.4-bookworm

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
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

RUN echo '#!/bin/sh' > /start.sh && \
    echo 'cat > /app/.env << EOF' >> /start.sh && \
    echo 'DB_HOST=${DB_HOST}' >> /start.sh && \
    echo 'DB_USERNAME=${DB_USERNAME}' >> /start.sh && \
    echo 'DB_PASSWORD=${DB_PASSWORD}' >> /start.sh && \
    echo 'DB_DATABASE=${DB_DATABASE}' >> /start.sh && \
    echo 'SMTP_HOST=${SMTP_HOST}' >> /start.sh && \
    echo 'SMTP_PORT=${SMTP_PORT}' >> /start.sh && \
    echo 'SMTP_USERNAME=${SMTP_USERNAME}' >> /start.sh && \
    echo 'SMTP_PASSWORD=${SMTP_PASSWORD}' >> /start.sh && \
    echo 'SMTP_ENCRYPTION=${SMTP_ENCRYPTION}' >> /start.sh && \
    echo 'SENDER_EMAIL=${SENDER_EMAIL}' >> /start.sh && \
    echo 'SENDER_NAME=${SENDER_NAME}' >> /start.sh && \
    echo 'DEMO=${DEMO}' >> /start.sh && \
    echo 'EOF' >> /start.sh && \
    echo 'frankenphp run --config /etc/caddy/Caddyfile' >> /start.sh && \
    chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
