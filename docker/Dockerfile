FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       libpng-dev \
       libjpeg62-turbo-dev \
       libfreetype6-dev \
       libicu-dev \
       libxml2-dev \
       unzip \
       git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       pdo_mysql \
       mysqli \
       zip \
       gd \
       mbstring \
       intl \
       xml \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html/

RUN if [ -f composer.json ]; then composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader; fi \
    && mkdir -p storage/backups storage/update_uploads storage/update_staging storage/uploads \
    && chown -R www-data:www-data /var/www/html/storage

RUN printf '%s\n' \
    'ServerName localhost' \
    'DirectoryIndex index.php index.html' \
    > /etc/apache2/conf-available/ujian-online.conf \
    && a2enconf ujian-online

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r "exit((int)!@fsockopen('127.0.0.1',80));"
