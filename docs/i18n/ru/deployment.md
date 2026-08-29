# Платформа трансграничной электронной коммерции — документация по развёртыванию

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Развёртывание через Docker (рекомендуется)

### 1.1 Требования к окружению

- Docker 24.0+ / Docker Compose v2
- Хост: Linux (рекомендуется Ubuntu 22.04+)
- Память: минимум 4GB, рекомендуется 8GB+

### 1.2 Шаги развёртывания

```bash
# 1. Клонировать проект
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Настроить переменные окружения
cp .env.example .env
# Отредактировать .env и изменить все пароли и ключи:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET и т.д.

# 3. Запустить все сервисы
docker compose up -d

# 4. Просмотр логов
docker compose logs -f service
docker compose logs -f admin

# 5. Доступ
# API: http://localhost/api
# Админ-панель: http://admin.localhost
```

### 1.3 Список сервисов

| Сервис | Порт | Описание |
|------|------|------|
| nginx | 80, 443 | Обратный прокси |
| service | 8787 (внутренний) | PHP-бизнес API |
| admin | 8788 (внутренний) | Админ-панель |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Контрольный список для продакшена

- [ ] Все ключи в `.env` заменены на случайные значения
- [ ] `STRIPE_MODE=live` (продакшен)
- [ ] `APP_ENV=production`
- [ ] В `config/app.php` параметр `debug` установлен в `false`
- [ ] Настроен SSL-сертификат (nginx + Let's Encrypt)
- [ ] Импортирована БД из корневого `install.sql` (117 таблиц, автоматически импортирует Web-мастер установки)
- [ ] Созданы индексы ES: `php start.php scout:import "app\model\Products"`
- [ ] Настроено резервное копирование томов данных MySQL/Redis/ES
- [ ] Настроен CDN (если используется): `CDN_DOMAIN` с CNAME на домен admin + учётные данные провайдера (Cloudflare/CloudFront/Aliyun/Tencent) в `config/cdn.php` / админ-панели; тома `admin_uploads` и `service_public` персистентны

## 2. Ручное развёртывание

### 2.1 Зависимости окружения

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (опционально, требуется для поиска)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# Отредактировать .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# Прослушивание: http://0.0.0.0:8787
```

### 2.3 Admin — админ-панель

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Прослушивание: http://0.0.0.0:8787 (другой порт — разделение через Nginx reverse proxy)
```

### 2.4 Nginx — обратный прокси

```nginx
# См. docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
# CDN origin-pull: граничный кэш загрузок (иммутабельный, 7 дней)
# location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }
```

## 3. Инициализация базы данных

```bash
# Создание базы данных
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Импорт структуры таблиц
mysql -u root -p erik_shop < install.sql

# Импорт данных-заполнителей (опционально)
php -r "
require 'vendor/autoload.php';
// Импорт стран/валют/HS Code/логистических зон и других данных-заполнителей
"
```

## 4. Справочник по переменным окружения

| Переменная | Значение по умолчанию | Описание |
|------|--------|------|
| APP_ENV | production | Окружение приложения |
| DB_HOST | 127.0.0.1 | Адрес базы данных |
| DB_PORT | 3306 | Порт базы данных |
| DB_NAME | erik_shop | Имя базы данных |
| DB_USER | erik | Пользователь базы данных |
| DB_PASS | (обязательно) | Пароль базы данных |
| REDIS_HOST | 127.0.0.1 | Адрес Redis |
| JWT_SECRET | (обязательно) | Ключ подписи JWT (256bit) |
| HASHIDS_SALT | (обязательно) | Соль Hashids |
| ENCRYPTION_KEY | (обязательно) | Ключ AES-шифрования |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Ключ Stripe |
| STRIPE_WEBHOOK_SECRET | - | Проверка подписи Stripe Webhook |
| CDN_ENABLED | false | Глобальный вкл/выкл CDN (0/1) |
| CDN_DEFAULT_PROVIDER | cloudflare | Провайдер по умолчанию (cloudflare/cloudfront/aliyun/tencent) |
| CDN_DOMAIN | - | Домен CDN (напр. cdn.erik.xyz, CNAME на домен admin) |
| CF_API_TOKEN | - | API-токен Cloudflare |
| CF_ZONE_ID | - | Zone ID Cloudflare |
| AWS_ACCESS_KEY_ID | - | Access Key ID AWS (CloudFront) |
| AWS_SECRET_ACCESS_KEY | - | Secret Access Key AWS (CloudFront) |
| AWS_REGION | us-east-1 | Регион AWS |
| CLOUDFRONT_DISTRIBUTION_ID | - | Distribution ID CloudFront |
| ALIYUN_ACCESS_KEY_ID | - | AccessKey ID Aliyun CDN |
| ALIYUN_ACCESS_KEY_SECRET | - | AccessKey Secret Aliyun CDN |
| TENCENT_SECRET_ID | - | SecretId Tencent Cloud CDN |
| TENCENT_SECRET_KEY | - | SecretKey Tencent Cloud CDN |

## 5. Команды эксплуатации

```bash
# Service API
cd service
php start.php status        # Просмотр статуса
php start.php reload        # Плавный перезапуск
php start.php stop          # Остановка

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # Просмотр статуса контейнеров
docker compose logs -f      # Просмотр логов
docker compose restart      # Перезапуск всех
docker compose down         # Остановка
```
