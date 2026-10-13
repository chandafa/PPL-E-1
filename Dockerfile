FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql bcmath zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

# batas unggah PHP harus di atas batas aplikasi (5 MB)
CMD ["sh", "-c", "export APP_KEY=${APP_KEY:-base64:$(php -r 'echo base64_encode(random_bytes(32));')} && php artisan migrate --force && php -d upload_max_filesize=6M -d post_max_size=16M -S 0.0.0.0:8080 -t public public/index.php"]
