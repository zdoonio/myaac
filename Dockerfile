FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        mysqli \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# mod_rewrite jest wymagany: .htaccess w korzeniu MyAAC używa RewriteEngine,
# a obraz php:apache ustawia AllowOverride All (docker-php.conf), więc bez
# tego modułu Apache odrzuca CAŁY katalog kodem 500. Włączenie przez
# `docker exec a2enmod` jest ulotne — znika przy odtworzeniu kontenera.

