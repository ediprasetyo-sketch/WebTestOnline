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
       libonig-dev \
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
    && mkdir -p /var/www/html/storage/backups \
       /var/www/html/storage/update_uploads \
       /var/www/html/storage/update_staging \
       /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html

RUN printf '%s\n' \
    'ServerName localhost' \
    '<Directory /var/www/html>' \
    '    Options FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    'DirectoryIndex index.php index.html' \
    > /etc/apache2/conf-available/ujian-online.conf \
    && a2enconf ujian-online

COPY docker/startup.sh /usr/local/bin/ujian-online-startup
RUN chmod +x /usr/local/bin/ujian-online-startup

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/ujian-online-startup"]
CMD ["apache2-foreground"]
