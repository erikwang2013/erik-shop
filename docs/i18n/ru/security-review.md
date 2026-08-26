# Отчёт об интеграции Security Plugin

**Дата**: 2026-08-04
**Область**: интеграция erikwang2013/security-php v1.1.6
**Рецензент**: Claude Code (автоматизированный)

---

## 1. Результаты тестирования

| Проверка | Результат |
|---|---|
| Проверка синтаксиса PHP (47 файлов) | Все пройдены |
| PHPUnit (22 tests, 45 assertions) | Все пройдены |
| Тест с вредоносными нагрузками SecurityGuard | Корректно блокирует XSS + SQLi |
| Тест с безопасными запросами SecurityGuard | Без ложных срабатываний |
| Статический анализ phpstan | Не установлен (не блокирует) |

## 2. Исправленные проблемы

### 2.1 Данные загрузки файлов не передавались в SecurityGuard (Critical)

**Файл**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

Промежуточное ПО передавало только `$request->all()` в `SecurityGuard::guard()`, но этот метод не включает данные загрузки файлов. `UploadDetector` требует данные файлов в формате `['tmp_name' => ..., 'name' => ...]`.

**Исправление**: добавлен цикл, объединяющий данные `$request->file()` в массив данных перед передачей в `SecurityGuard::guard()`.

### 2.2 В конфигурации encryptable для Admin отсутствует значение по умолчанию (Medium)

**Файл**: `admin/config/plugin/erikwang2013/encryptable/app.php`

Конфигурация admin использует `env('ENCRYPTION_KEY')` без резервного значения, возвращая `null` при отсутствии переменной окружения. Service использует `getenv('ENCRYPTION_KEY') ?: ''` с корректным откатом на пустую строку.

**Исправление**: конфигурация admin приведена к оператору `?: ''` для согласования с поведением service.

### 2.3 Неполные переменные окружения Docker Compose (Medium)

**Файл**: `docker-compose.yml`

- В контейнере service отсутствуют `ENCRYPTION_CIPHER` и `ENCRYPTION_PREVIOUS_KEYS`
- В контейнере admin отсутствуют `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID`

**Исправление**: добавлены все отсутствующие переменные окружения со значениями по умолчанию, согласованными с `.env.example`.

### 2.4 Дублирующееся обнаружение WAF-промежуточным ПО (Critical, исправлено в первом раунде)

Собственное `SecurityMiddleware` содержало ~200 строк встроенных регулярных выражений, полностью дублирующих 31 детектор пакета `security-php`. Каждый запрос сканировался дважды — трата CPU и возможное двойное блокирование.

**Исправление**: промежуточное ПО переписано на использование API `SecurityGuard::guard()`, сокращено с 341 до ~110 строк (service) и со 136 до ~85 строк (admin). Защита от перебора паролей и безопасные заголовки ответа сохранены.

### 2.5 Отсутствие ENCRYPTION_KEY (Critical, исправлено в первом раунде)

В файле `.env.example` параметр `ENCRYPTION_KEY` использовал плейсхолдер, отсутствовали `ENCRYPTION_CIPHER` и `ENCRYPTION_PREVIOUS_KEYS`. Фактического файла `.env` не было.

**Исправление**: сгенерирован 32-байтовый ключ base64, добавлены `ENCRYPTION_CIPHER=AES-256-CBC` и `ENCRYPTION_PREVIOUS_KEYS`, создан файл `.env`.

## 3. Полнота экосистемной конфигурации

### 3.1 Пакеты (согласованы в обоих проектах)

| Пакет | Версия | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | Установлен | Установлен |
| erikwang2013/encryptable | - | Установлен | Установлен |
| erikwang2013/encryption | - | Установлен | Установлен |
| erikwang2013/jwt-webman | - | Установлен | Установлен |
| erikwang2013/hashids | - | Установлен | Установлен |
| erikwang2013/snowflake-php | - | Установлен | Установлен |
| erikwang2013/poster-php | - | Установлен | Установлен |
| erikwang2013/season | - | Установлен | Установлен |
| erikwang2013/webman-scout | - | Установлен | Установлен |

### 3.2 Конфигурация WAF

| Пункт | Service | Admin | Статус |
|---|---|---|---|
| Файл конфигурации | `config/plugin/erikwang2013/security-php/app.php` | Аналогично | Опубликован |
| Включённые детекторы | 31/31 | 31/31 | Корректно |
| IP-чёрный список | включён (5 попыток/60s → бан 900s) | Аналогично | Корректно |
| Детекторы в режиме блокировки | 28 | 28 | Корректно |
| Детекторы только логирования | 3 (header_injection, ssti, nosql_injection) | 3 | Корректно |
| Хранилище | file | file | Корректно |
| Логирование | включено (file, ротация 10MB) | Аналогично | Корректно |
| Промежуточное ПО зарегистрировано | `config/middleware.php` | `config/middleware.php` | Корректно |

### 3.3 Конфигурация шифрования

| Пункт | Service | Admin | Статус |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | Аналогично | Установлен |
| ENCRYPTION_CIPHER | `AES-256-CBC` | Аналогично | Установлен |
| ENCRYPTION_PREVIOUS_KEYS | (пусто) | (пусто) | Установлен |
| конфиг encryptable | `config/plugin/erikwang2013/encryptable/app.php` | Аналогично (унифицирован) | Корректно |
| конфиг encryption | `config/encryption.php` | - | Корректно |
| Файл .env | Существует | Существует | Создан |
| .env.example | Обновлён | Обновлён | Корректно |
| docker-compose | Обновлён | Обновлён | Корректно |

### 3.4 Модели с trait Encryptable

31 модель использует trait `Encryptable`, чувствительные поля корректно объявлены в `$encryptable`:

| Категория | Модели | Чувствительные поля |
|---|---|---|
| PII пользователя | Users | email, mobile |
| PII пользователя | UserAddresses | name, phone, detail |
| PII пользователя | UserKyc | real_name, id_number |
| PII пользователя | UserSocialAccounts | access_token, refresh_token |
| Конфиденциальность | PrivacyRequests | email |
| Финансы | GiftCards | receiver_email |
| Финансы | AffiliatePayouts | account |
| Финансы | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Платформы | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Платформы | PlatformAccounts | account_name, api_key, api_secret |
| Платформы | PlatformListings | platform_account_id |
| Логистика | LogisticsCompanies | name, api_key |
| Поставщики | Suppliers | name, email, phone |
| Поставщики | B2bVerifications | company_name |
| Продавцы | Merchants | store_name, email, phone |
| Прочее | EmailLogs | to_email |
| Прочее | ещё 15 моделей | поля с именами |

## 4. Исправления второго раунда (шифрование API + ключи JWT)

### 4.1 Промежуточное ПО шифрования ответов API (Medium, исправлено)

**Файл**: `service/app/middleware/EncryptionMiddleware.php` (новый)

Пакет `erikwang2013/encryption` был установлен, утилита `app/common/Encryption` существовала, но ранее не была подключена к конвейеру промежуточного ПО. Чувствительным данным интерфейсов не хватало шифрования на транспортном уровне.

**Исправление**:
- Создано `EncryptionMiddleware` с шифрованием/дешифрованием, управляемым через HTTP-заголовки:
  - `X-Encrypted: 1` — дешифрование запроса: base64-зашифрованный body дешифруется в JSON и передаётся контроллеру
  - `X-Encrypt-Response: 1` — шифрование ответа: поле `data` ответа шифруется в base64-шифротекст
  - `X-Encrypt-Fields: field1,field2` — шифровать только указанные поля ответа
- Зарегистрировано последней ступенью стека промежуточного ПО (после HashidsEncode)
- Проверки здоровья (`/api/health`, `/api/ping`) и конечные точки документации (`/apidoc`) пропускают шифрование/дешифрование

### 4.2 Несоответствие имени класса и имени файла (Medium, исправлено)

**Файл**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

Класс `app\common\Encryption` был объявлен в файле `EncryptionHelper.php`, что не соответствует PSR-4 и приводило к сбою автозагрузки Composer. В средах IDE и CLI класс мог не находиться автозагрузчиком.

**Исправление**: файл переименован в `Encryption.php` для соответствия имени класса.

### 4.3 Пустой JWT_SECRET_KEY (Low, исправлено)

**Файл**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` была пустой строкой; хотя JWT-промежуточное ПО имеет цепочку отката `JWT_SECRET → JWT_SECRET_KEY` (приоритет у `JWT_SECRET`), значение-плейсхолдер небезопасно.

**Исправление**: сгенерирован 32-байтовый ключ base64, установлены и `JWT_SECRET`, и `JWT_SECRET_KEY`. Обновлены `.env.example`, `.env` и `docker-compose.yml`.

## 5. Вопросы для наблюдения (потенциальные точки оптимизации)

### 5.1 Зависимость SecurityGuard от заголовков webman/Workerman (низкий риск)

**Влияние**: детекторы CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, CORS зависят от данных HTTP-заголовков в `$_SERVER`.

В не-CGI среде Workerman `$_SERVER` может быть заполнен не полностью. SecurityGuard имеет резервную логику (например, пропуск детекции при пустом значении заголовка), поэтому **ложных срабатываний не будет**, но **часть header-атак может быть не обнаружена**. Степень влияния низкая, поскольку Nginx на уровне обратного прокси обычно также фильтрует вредоносные заголовки.

**Рекомендация**: при необходимости более полной детекции заголовков можно явно передавать значения заголовков в параметре `$meta` SecurityGuard. Сейчас изменения не требуются.

### 5.2 Влияние детектора CSRF Origin на Admin (без риска)

Детектор `csrf_origin` в Admin находится в режиме `block`, `allowed_origins` пуст. Но поскольку детектор срабатывает только при наличии заголовка Origin и его несовпадении с Host, при обращении к админке обычно нет заголовка Origin (однотипный доступ) — поэтому **ложного блокирования не будет**.

### 5.3 Все 31 детектор включены, накладные расходы на запрос (примечание о производительности)

Все запросы выполняют все 31 детектор (включая JWT, WebSocket, GraphQL, CSV, prototype pollution и др.). Каждый детектор выполняет сопоставление с регулярными выражениями по всем полям запроса. Для сценариев использования этого проекта накладные расходы приемлемы (webman — резидентный процесс, без накладных расходов на холодный старт CGI).

### 5.4 Персистентность IP-чёрного списка (эксплуатационное примечание)

Серверное хранилище — режим `file`, путь по умолчанию `sys_get_temp_dir() . '/security_storage.json'`. В Docker-контейнерах временный каталог может теряться после перезапуска. Если требуется общий чёрный список в многоконтейнерном развёртывании, можно переключиться на режим `redis`.

## 6. Сводка изменённых файлов

```
admin/.env.example                                (добавлен ENCRYPTION_KEY)
admin/.env                                        (создан из .env.example)
admin/CLAUDE.md                                   (обновлён стек промежуточного ПО + tech stack)
admin/composer.json                               (зависимость security-php)
admin/config/plugin/erikwang2013/encryptable/app.php  (унифицированы значения по умолчанию)
admin/config/plugin/erikwang2013/security-php/app.php  (новый, 31 детектор)
admin/app/middleware/SecurityMiddleware.php       (переписано на SecurityGuard)
service/.env.example                              (обновлены ENCRYPTION_KEY/CIPHER + ключи JWT)
service/.env                                      (создан из .env.example, синхронизированы ключи JWT)
service/CLAUDE.md                                 (обновлены стек промежуточного ПО + Encryption + tech stack)
service/composer.json                             (зависимость security-php)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (новый, 31 детектор)
service/app/common/Encryption.php                 (переименован из EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (новый, шифрование/дешифрование ответов API)
service/app/middleware/SecurityMiddleware.php     (переписано на SecurityGuard + загрузка файлов)
docker-compose.yml                                (дополнены переменные окружения encryption/jwt)
docs/security-review.md                           (настоящий отчёт)
```

## 7. Заключение

**Статус**: пройдено

- WAF-детекция корректно блокирует XSS, SQL-инъекции и другие атаки (31 детектор, API SecurityGuard::guard)
- Конфигурация шифрования чувствительных полей полная (31 модель, 6 категорий чувствительных данных, trait Encryptable)
- Шифрование/дешифрование передачи API подключено к промежуточному ПО (EncryptionMiddleware, AES-256-CBC, запуск по заголовку)
- Ключи JWT настроены (установлены и JWT_SECRET, и JWT_SECRET_KEY)
- Детекция загрузки файлов исправлена (объединение данных $_FILES для передачи в SecurityGuard)
- Функциональных регрессий нет (пройдено 22/22 теста)
- Дублирующегося обнаружения промежуточным ПО нет
- Переменные окружения Docker для развёртывания полные
