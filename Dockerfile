FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
      intl \
      pdo_mysql \
      pdo_pgsql \
      zip \
      opcache \
      mbstring \
      xml \
      gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GID} appuser \
    && useradd -u ${UID} -g ${GID} -m appuser

WORKDIR /var/www/html

RUN mkdir -p var/cache var/log \
    && chown -R appuser:appuser var

USER appuser

CMD ["php-fpm"]
