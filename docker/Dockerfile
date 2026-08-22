FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
       libicu-dev libxml2-dev libonig-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli zip gd mbstring intl xml \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . /var/www/html/

RUN if [ -f composer.json ]; then composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader; fi \
    && mkdir -p storage/backups storage/update_uploads storage/update_staging storage/uploads \
    && chown -R www-data:www-data /var/www/html

# The application has no root index.php; login.php is the actual entry point.
RUN printf '%s\n' \
    'ServerName localhost' \
    '<Directory /var/www/html>' \
    '    Options -Indexes +FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    'DirectoryIndex login.php index.php index.html' \
    > /etc/apache2/conf-available/webtestonline.conf \
    && a2enconf webtestonline

COPY docker/startup.sh /usr/local/bin/webtestonline-startup
RUN chmod +x /usr/local/bin/webtestonline-startup

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/webtestonline-startup"]
CMD ["apache2-foreground"]
