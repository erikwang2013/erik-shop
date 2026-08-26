# Платформа трансграничной электронной коммерции — Руководство по установке

> Cross-border E-Commerce Platform Installation Guide
>
> [Китайский README](../../../README.md) | [English README](../../README-EN.md) | [Отчёт об аудите](../../AUDIT-REPORT.md)

---

## Требования к окружению / Requirements

| Компонент | Минимальная версия | Рекомендуемая версия |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (необязательно/optional) |

### Расширения PHP

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Способы установки / Installation Methods

### Способ 1 (рекомендуется): мастер установки в один клик через Web

Откройте страницу установки в браузере, укажите данные базы данных и учётную запись администратора — **создание таблиц, конфигурация и создание администратора выполняются полностью автоматически**.

```bash
# 1. Установка зависимостей
cd admin/
composer install

# 2. Запуск админ-панели
php start.php start

# 3. Доступ через браузер (при первом запуске авто-переход на страницу установки)
# http://127.0.0.1:8788/app/admin/install/step1
```

Установочный мастер **автоматически выполнит**:
- создание базы данных MySQL (если не существует)
- импорт всех 117 таблиц из `install.sql` (7 таблиц `wa_` + 110 таблиц `erik_`)
- импорт меню админ-панели
- генерацию `plugin/admin/config/database.php` и `thinkorm.php`
- генерацию `service/.env` (со случайными ключами JWT/Hashids/шифрования)
- создание учётной записи супер-администратора
- отправку сигнала SIGUSR1 для перезагрузки служб

> После установки также необходимо запустить API-службу service/ (см. шаг 5 ниже).

---

### Способ 2: ручная установка / Manual Installation

<details>
<summary>Подходит для развёртывания через командную строку или при наличии существующей БД</summary>

### 1. Создание базы данных

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Импорт базы данных

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` содержит **117 таблиц** и данные-заполнители по умолчанию.

### 3. Настройка service/.env

```bash
cd service/
cp .env.example .env
# Отредактируйте .env: укажите реальные параметры БД/Redis/JWT и т.д.
```

**Ключевые параметры конфигурации:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<случайный 32-байтовый ключ>
HASHIDS_SALT=<случайная соль>
ENCRYPTION_KEY=<случайный 32-байтовый ключ>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. Настройка admin/

```bash
cd admin/
cp .env.example .env
# Отредактируйте .env: укажите те же данные БД, что и в service
```

### 5. Создание учётной записи администратора

```sql
-- Пароль должен быть сгенерирован через bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Супер-администратор', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Способ 3: развёртывание через Docker / Docker Deployment

```bash
# 1. Настройка переменных окружения
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Запуск всех сервисов
docker-compose up -d

# 3. Запуск Web-мастера установки
# http://localhost/app/admin/install/step1
```

Службы Docker: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Запуск служб / Start Services

```bash
# Установка зависимостей (нужна для обоих проектов)
cd service/ && composer install
cd admin/ && composer install

# Запуск API-сервиса
cd service/
php start.php start -d

# Запуск админ-панели
cd admin/
php start.php start -d
```

| Служба | Порт по умолчанию | Способ проверки |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Админ-панель | 8788 | браузер: `http://127.0.0.1:8788/app/admin` |

### Импорт данных-заполнителей (необязательно) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # страны/регионы
php start.php seed:currencies    # валюты
php start.php seed:hs_codes      # коды HS Code
php start.php seed:compliance    # категории соответствия
```

---

## Структура каталогов / Directory Structure

```
shop-php/
├── install.sql              # итоговый полный установочный SQL
├── admin/                   # админ-панель (webman-admin + LayUI)
│   ├── config/database.php  # конфигурация БД
│   ├── plugin/admin/        # плагин webman-admin
│   └── start.php
├── service/                 # API-сервис (webman RESTful)
│   ├── config/              # файлы конфигурации
│   ├── database/schema.sql  # исходный SQL бизнес-таблиц (заменён install.sql)
│   ├── database/seeders/    # данные-заполнители
│   └── start.php
```

---

## Обзор структуры базы данных / Database Schema Overview

| Модуль | Префикс таблиц | Кол-во таблиц | Описание |
|------|--------|--------|------|
| Система админ-панели | `wa_` | 7 | Администраторы/роли/права/конфигурация/вложения |
| Пользователи и аккаунты | `erik_users_*` | 7 | Пользователи/адреса/соцсети/KYC/избранное/членство |
| Товары и категории | `erik_product_*` | 16 | Товары/SKU/многоязычность/мультивалютность/отзывы/соответствие/HS |
| Корзина и заказы | `erik_order_*` | 9 | Корзина/заказы/оплата/возвраты/обмены/растаможка |
| Страны/валюты/логистика | `erik_shipping_*` | 11 | Страны/валюты/курсы/логистика/зоны/склады/складские остатки |
| Таможня и налоги | `erik_hs_*` | 5 | HS-коды/пошлины/VAT/ограничения соответствия |
| Оплата и финансы | `erik_payment_*` | 6 | Платёжные шлюзы/расчёты платформы/расчёты поставщиков/курсовые разницы |
| Маркетинг | `erik_coupon_*` | 9 | Купоны/флеш-распродажи/групповые покупки/партнёрская программа |
| Цепочка поставок | `erik_supplier_*` | 7 | Поставщики/закупки/контроль качества |
| Риски и соответствие | `erik_risk_*` | 6 | Правила рисков/GDPR/Cookie/конфиденциальность |
| Мультиплатформенность | `erik_platform_*` | 8 | Мультимагазины/аккаунты платформ/линковка/продавцы |
| Контент и опыт | `erik_*` | 12 | CMS/Feed/размеры/уведомления/письма/поиск/журналы операций |
| Подписки/баллы и др. | `erik_*` | 7 | Подписки/баллы/подарочные карты/B2B |
| AB-тесты/API/настройки | `erik_*` | 7 | AB-тесты/лимиты/API-документация/системные настройки |

---

## Частые проблемы / Troubleshooting

### Ошибка MySQL "Specified key was too long"

```sql
-- Убедитесь, что используется utf8mb4 + InnoDB и включён innodb_large_prefix
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Конфликт портов / Port Conflict

Измените `APP_PORT` в `admin/.env` или `service/.env`.

### Ошибка подключения к Redis

Проверьте, что расширение Redis установлено и служба Redis запущена:
```bash
redis-cli ping  # должен вернуть PONG
```

### Конфликт ID Snowflake

Если несколько серверов создают ID одновременно, убедитесь, что `SNOWFLAKE_WORKER_ID` (0-31) на каждом сервере отличается.

---

## Шпаргалка по командам разработки / Development Commands

```bash
# service/ (API)
php start.php start          # запуск
php start.php start -d       # демон-процесс
php start.php reload         # горячая перезагрузка
php start.php stop           # остановка
php start.php status         # статус

# admin/ (админ-панель)
php start.php start
php start.php start -d
php start.php reload
```
