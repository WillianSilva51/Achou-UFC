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

# Configura Apache para servir a pasta public/ e aceitar .htaccess
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/public>|g' \
        /etc/apache2/apache2.conf \
    && sed -i 's|AllowOverride None|AllowOverride All|g' \
        /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copia tudo e instala dependências
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
