FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
        curl \
        unzip \
        git \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

RUN printf '%s\n' \
        'display_errors=Off' \
        'html_errors=Off' \
        'log_errors=On' \
        'error_reporting=E_ALL' \
        > /usr/local/etc/php/conf.d/achou-ufc.ini

RUN printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/achou-ufc.conf \
    && a2enconf achou-ufc

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .

RUN composer dump-autoload --optimize --no-dev

EXPOSE 80