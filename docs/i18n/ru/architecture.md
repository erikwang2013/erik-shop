# Платформа трансграничной электронной коммерции — обзор архитектуры

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Технологический стек

| Уровень | Технология | Версия |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| Клиент | Flutter (5 платформ) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| База данных | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| Оплата | Stripe / PayPal / Klarna / Adyen | — |

## 2. Структура каталогов

```
shop-php/
  service/            Бизнес-API (251 PHP-файл)
    config/            35 конфигураций (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39 контроллеров (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 моделей (BaseModel + 110 бизнес-моделей)
    app/middleware/     14 промежуточных ПО (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 утилит (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (заменён корневым install.sql) + seeders
    tests/              4 тестовых класса (22 tests, 45 assertions)
  admin/              Админ-панель (239 PHP-файлов)
    plugin/admin/app/controller/shop/ 82 контроллера
    plugin/admin/app/model/shop/      76 моделей
    plugin/admin/app/view/shop/       ECharts-дашборд
    app/middleware/    5 промежуточных ПО (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/               Клиенты
    flutter/lib/      25 Dart (11 страниц + базовый слой + маршрутизация)
    harmonyos/        14 ArkTS (9 страниц + API-клиент + глобальное состояние)
  docs/               5 дизайн-документов
  .claude/skills/     38 навыков стандартов разработки
```

## 3. Конвейер промежуточного ПО

```
Service: Cors → Security(31 тип атак) → RateLimit(токен-бакет) → Platform(8 платформ)
        → GeoIp(регион) → Locale(язык) → HashidsDecode → VersionRoute
        → (PosterVerify человек-машина) → (JwtAuth Token) → HashidsEncode → Encryption(шифрование интерфейса)

Admin:  Security → Platform → HashidsDecode → AccessControl(встроенный RBAC) → HashidsEncode
```

## 4. Безопасность

- **31 тип обнаружения атак**: XSS/SQL-инъекции/инъекции команд/CRLF/обход пути/Body/ContentType/загрузка файлов/перебор паролей/XXE/SSRF/десериализация/LDAP/заголовки почты/SSTI/NoSQL/открытое перенаправление/атаки на JWT/Host/смоуглинг запросов/GraphQL/XPATH/Log4Shell/SSI/CSV-формулы/утечка данных/загрязнение прототипов/WebSocket/CORS/DNS-ребinding/HTTP-методы/CSRF Origin
- **Три уровня шифрования**: уровень интерфейса (AES-256-CBC) + уровень БД (trait Encryptable) + обфускация ID (Hashids)
- **Трекинг платформ**: 8 платформ (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + заголовок X-Platform + запись в 6 таблиц

## 5. Высокая нагрузка

- **Ограничение частоты**: токен-бакет со скользящим окном (Redis ZSET), правила для 6 конечных точек
- **Предохранитель/деградация**: предохранитель на Redis — внешние API-вызовы платёжных шлюзов/входа через соцсети; 5 последовательных сбоев → размыкание на 30s, полуоткрытая проверка с автоматическим восстановлением; бизнес-исключения не считаются сбоями; при отказе Redis — автоматический пропуск (503)
- **БД**: разделение чтения/записи (2 реплики чтения + sticky) + пул соединений (50/10)
- **Медленные операции**: обрабатываются отдельными процессами Cron (синхронизация Feed/расчёт рекомендаций/сверка платежей/распределение расчётов и др.)

## 6. Тестирование

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+утечка данных
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. Развёртывание

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# Вручную
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **Многоязычность (i18n)**: 5 файлов переводов + LocaleMiddleware + Flutter AppLocalizations
- **API-документация**: автоматическая генерация hg/apidoc (6 групп, на основе аннотаций контроллеров)
- **Трекинг платформ**: заголовок X-Platform для 8 платформ + запись в БД

Подробнее: [документация по развёртыванию](deployment.md) | [полный архитектурный документ](architecture-full.md) | [функциональный дизайн](features.md)
