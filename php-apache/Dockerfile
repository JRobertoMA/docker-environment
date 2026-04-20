# Usar la imagen base de PHP con Apache
ARG PHP_VERSION
FROM php:${PHP_VERSION}-apache

# Recibir argumentos de construcción
ARG UID=1000
ARG GID=1000

# Sincronizar IDs con el host
RUN groupmod -g ${GID} www-data && \
    usermod -u ${UID} -g www-data www-data

# Habilitar mod_rewrite y configurar ServerName
RUN a2enmod rewrite headers && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copiar configuración personalizada de Apache
COPY config/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Instalar dependencias del sistema y extensiones PHP en una sola capa
RUN apt-get update && apt-get install -y \
    acl \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    git \
    libmagickwand-dev \
    libmemcached-dev \
    zlib1g-dev \
    --no-install-recommends \
    && pecl install imagick memcached \
    && docker-php-ext-enable imagick memcached \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        soap \
        curl \
        mbstring \
        fileinfo \
        xml \
    && echo "zend_extension=$(php-config --extension-dir)/opcache.so" > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
