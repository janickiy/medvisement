FROM php:8.3.8-cli

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

# Юзер
ARG APP_UID
ARG APP_GID
ARG APP_USER

RUN groupadd -g ${APP_GID} ${APP_USER} \
 && useradd -u ${APP_UID} -g ${APP_GID} -m ${APP_USER} || true

USER ${APP_UID}:${APP_GID}