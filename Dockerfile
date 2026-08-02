FROM php:8.2-apache

# Install PHP extensions yang dibutuhkan OpenCart 4
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        intl \
        opcache \
        curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# PHP config untuk OpenCart
RUN echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/opencart.ini \
    && echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/opencart.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/opencart.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/opencart.ini

# Apache config
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/opencart.conf \
    && a2enconf opencart

# Download dan install OpenCart 4
WORKDIR /var/www/html
ARG OPENCART_VERSION=4.0.2.3
RUN curl -fL -o /tmp/opencart.zip "https://github.com/opencart/opencart/releases/download/${OPENCART_VERSION}/opencart-${OPENCART_VERSION}.zip" \
    && unzip /tmp/opencart.zip -d /tmp/opencart \
    && cp -a /tmp/opencart/opencart-${OPENCART_VERSION}/upload/. /var/www/html/ \
    && rm -rf /tmp/opencart /tmp/opencart.zip

# Prepare OpenCart config files + seed storage (untuk volume mount)
RUN cp config-dist.php config.php \
    && cp admin/config-dist.php admin/config.php \
    && chmod 0777 config.php admin/config.php \
    && chmod -R 0777 system/storage/ image/ \
    && mkdir -p extension \
    && cp -a system/storage /var/www/html/storage-seed

# Copy install script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
