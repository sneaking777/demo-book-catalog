FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
&& docker-php-ext-configure gd --with-jpeg --with-freetype \
&& docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    mbstring \
    intl \
    gd \
    zip \
    opcache \
    bcmath \
&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory '*'

WORKDIR /var/www/html