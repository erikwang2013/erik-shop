# Платформа трансграничной электронной коммерции — дизайн-документ

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Проектирование базы данных

### 1.1 Соглашения об именовании

- Префикс таблиц: `erik_`
- Первичный ключ: `id BIGINT UNSIGNED NOT NULL` (генерируется snowflake, не автоинкремент)
- Временные метки: `created_at`, `updated_at`, `deleted_at` (мягкое удаление)
- Движок: InnoDB, кодировка: utf8mb4_unicode_ci

### 1.2 Разбиение на модули (110 таблиц)

| Модуль | Кол-во таблиц | Ключевые таблицы |
|------|------|--------|
| Пользователи и аккаунты | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Товары и категории | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Транзакции | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Оплата и финансы | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Логистика | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Таможня и налоги | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Маркетинг | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Цепочка поставок | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Риски и соответствие | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Мультиплатформенность | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Контент и опыт | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Подписки и B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Клиентская поддержка | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB-тесты | 3 | ab_tests, ab_test_variants, ab_test_results |
| Управление API | 2 | api_rate_limits, api_docs |
| Базовые данные | 3 | countries, currencies, exchange_rates |

### 1.3 Поля трекинга платформ

| Таблица | Поле | Описание |
|----|------|------|
| orders | platform VARCHAR(16) | Платформа оформления заказа |
| payments | platform VARCHAR(16) | Платёжная платформа |
| operation_logs | platform VARCHAR(16) | Платформа операций |
| users | last_login_platform VARCHAR(16) | Платформа последнего входа |
| search_logs | platform VARCHAR(16) | Платформа поиска |
| chat_messages | platform VARCHAR(16) | Источник сообщения |

---

## 2. Проектирование API

Версионирование API, конвейер промежуточного ПО, статистика конечных точек и стандарты единого ответа — подробнее в [документации API](api.md).

---

## 3. Проектирование безопасности

### 3.1 SecurityMiddleware инкапсулирует 31 детектор security-php

| # | Тип | Код ошибки | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL-инъекция | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Обход пути | 40004 | ✅ | ✅ |
| 5 | Слишком большой Body | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | Загрузка файлов | 40009 | ✅ | ✅ |
| 8 | Безопасные заголовки ответа | — | ✅ | ✅ |
| 9 | Перебор паролей | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP-методы | 40012 | ✅ | ✅ |
| 13 | Заголовок Host | 40013 | ✅ | — |
| 14 | Маскирование чувствительных данных | — | ✅ | ✅ |
| 15 | CORS-белый список | — | ⚠️ | ⚠️ |

### 3.2 Три уровня шифрования

| Уровень | Технология | Пакет |
|------|------|-----|
| Транспортный уровень | AES-256-CBC | erikwang2013/encryption |
| Уровень базы данных | trait Encryptable | erikwang2013/encryptable (Maize) |
| Обфускация ID | Hashids | erikwang2013/hashids |

---

## 4. Проектирование под высокую нагрузку

### 4.1 Ограничение частоты

Токен-бакет со скользящим окном (Redis ZSET, через фасад `support\Redis`): по умолчанию 60s/100 запросов, вход 10 раз/60s, регистрация 5 раз/300s, вход через соцсети 5 раз/300s, оплата 5 раз/60s, оформление заказа 3 раза/10s, поиск 10 раз/1s

### 4.2 Предохранитель и деградация

Предохранитель на Redis (`app\common\CircuitBreaker`): внешние API-вызовы платёжных шлюзов/входа через соцсети проходят единообразно через `CircuitBreaker::call()` — 5 последовательных сбоев размыкают предохранитель на 30s; после истечения TTL следующий запрос автоматически выполняет полуоткрытую проверку, при успехе предохранитель сбрасывается. Бизнес-исключения из белого списка (недействительная карта/недействительный токен) не считаются сбоями, что не даёт атакующим вывести из строя зависимые сервисы недействительными запросами; при недоступности Redis выполняется автоматический пропуск (fail-open). Пока предохранитель разомкнут, интерфейс возвращает 503 «Сервис временно недоступен».

### 4.3 Использование Redis

Redis используется для токен-бакета ограничения частоты (фасад `support\Redis`), счётчиков предохранителя, кодов человеко-машинной проверки и хранения сессий; бизнес-данные не кэшируются на уровне приложения — напрямую читаются из MySQL (разделение чтения/записи + пул соединений).

### 4.4 Пул соединений

MySQL: 50max/10min/2s timeout | разделение чтения/записи: 30max/5min (2 реплики чтения, sticky=true) | Redis: 30max/5min



---

## 5. Интернационализация

- Интерфейс: zh_CN, zh_HK, en, ja, ko
- Контент: erik_product_translations — отдельные строки по locale
- Цены: erik_product_sku_prices — отдельное ценообразование по валютам
- Заголовки: Accept-Language + API-Version

## 6. API-документация

Генерируется автоматически с помощью hg/apidoc на основе аннотаций контроллеров, подробнее в [документации API](api.md). После запуска перейдите по адресу `/apidoc/`.

## 7. Тестирование

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

Подробнее: [функциональный дизайн](features.md) | [полный архитектурный документ](architecture-full.md) | [документация по развёртыванию](deployment.md)
