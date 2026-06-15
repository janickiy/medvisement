FROM php:8.3.8-fpm

# System deps
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libmagickwand-dev \
    libmagickcore-dev \
    libonig-dev \
    imagemagick \
    msmtp \
    unzip \
    gettext-base \
    git \
    && rm -rf /var/lib/apt/lists/*

# GD (с JPEG + FreeType)
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

# Core extensions
RUN docker-php-ext-install -j$(nproc) \
    mysqli \
    pdo \
    pdo_mysql \
    gd \
    curl \
    mbstring \
    xml \
    zip \
    intl \
    exif \
    bcmath \
    opcache \
    pcntl

# PECL extensions
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Удаляем стандартный pool
RUN rm /usr/local/etc/php-fpm.d/www.conf
RUN rm /usr/local/etc/php-fpm.d/www.conf.default
RUN rm /usr/local/etc/php-fpm.d/docker.conf
RUN rm /usr/local/etc/php-fpm.d/zz-docker.conf

# Установка WP-CLI
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x /usr/local/bin/wp

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node.js + npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs

# Юзер
ARG APP_UID
ARG APP_GID
ARG APP_USER

RUN groupadd -g ${APP_GID} ${APP_USER} \
 && useradd -u ${APP_UID} -g ${APP_GID} -m ${APP_USER} || true

USER ${APP_UID}:${APP_GID}