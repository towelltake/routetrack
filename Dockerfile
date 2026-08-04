FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    default-mysql-client \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        gd \
        mbstring \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        xml \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    public/visualimages \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        public/visualimages \
    && chmod -R 775 \
        storage \
        bootstrap/cache \
        public/visualimages

EXPOSE 9000

CMD ["php-fpm"]
