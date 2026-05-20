# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
FROM php:8.3-cli-alpine

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 使用阿里云镜像加速（国内部署）
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
  && apk update --no-cache

# 安装PHP扩展
RUN apk add --no-cache libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev icu-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) pdo pdo_mysql pcntl bcmath opcache gd zip intl sockets \
  && pecl install redis && docker-php-ext-enable redis opcache pcntl \
  && apk del libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev icu-dev \
  && rm -rf /var/cache/apk/*

# OPCache 生产配置
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN mkdir -p /app
WORKDIR /app
COPY . /app

# 生产模式安装依赖
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8787

CMD ["php", "start.php", "start"]
