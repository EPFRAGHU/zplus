# ZPlus Manpower: PHP 8.3 + Apache serving public/. Database runs in its own container (see docker-compose.yml).
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd intl bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && printf "upload_max_filesize=12M\npost_max_size=14M\nmemory_limit=256M\nexpose_php=Off\n" > /usr/local/etc/php/conf.d/zplus.ini \
    && printf "ServerName localhost\nServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-enabled/zplus.conf

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/docker-entrypoint.sh /usr/local/bin/zplus-entrypoint
RUN chmod +x /usr/local/bin/zplus-entrypoint
ENTRYPOINT ["zplus-entrypoint"]
CMD ["apache2-foreground"]
