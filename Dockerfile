FROM php:8.2-apache

# Extensões necessárias: PDO + PostgreSQL + curl (reCAPTCHA) + mbstring + openssl
RUN apt-get update && apt-get install -y \
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

# Instala Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Habilita mod_rewrite para o .htaccess funcionar
RUN a2enmod rewrite

# APIs devem registrar warnings no log, nunca imprimir HTML antes do JSON.
RUN printf '%s\n' \
        'display_errors=Off' \
        'html_errors=Off' \
        'log_errors=On' \
        'error_reporting=E_ALL' \
        > /usr/local/etc/php/conf.d/achou-ufc.ini

# Configura Apache para servir o frontend na raiz e encaminhar /api pelo .htaccess.
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/achou-ufc.conf \
    && a2enconf achou-ufc

WORKDIR /var/www/html

# Copia tudo e instala dependências
COPY . .
RUN git config --global --add safe.directory /var/www/html
RUN composer install --no-dev --optimize-autoloader

# Permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
