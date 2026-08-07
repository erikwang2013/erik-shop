FROM php:8.3.22-cli-alpine

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
  && apk update --no-cache \
  && docker-php-source extract

# install extensions
RUN docker-php-ext-install pdo pdo_mysql -j$(nproc) pcntl

# enable opcache and pcntl
RUN docker-php-ext-enable opcache pcntl
RUN docker-php-source delete \
    rm -rf /var/cache/apk/*

RUN mkdir -p /app
WORKDIR /app

COPY . /app

# 生产模式安装依赖
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8788

CMD ["php", "start.php", "start"]