FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN groupmod -g ${GID} www-data \
 && usermod -u ${UID} -g ${GID} www-data

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

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
