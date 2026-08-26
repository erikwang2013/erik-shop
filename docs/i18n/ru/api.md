# Платформа трансграничной электронной коммерции — документация API

> Динамическая документация: после запуска Service перейдите по адресу http://localhost:8787/apidoc/ (автоматически генерируется hg/apidoc)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Общие правила

### Формат запросов

| Пункт | Описание |
|------|------|
| Base URL | `http://localhost:8787/api` |
| Версионирование | заголовок `API-Version: 2026-05-20` (не в URL) |
| Аутентификация | заголовок `Authorization: Bearer <token>` |
| Язык | заголовок `Accept-Language: zh_CN|zh_HK|en|ja|ko` |
| Платформа | заголовок `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` |
| Content-Type | `application/json` (POST/PUT) |
| Человеко-машинная проверка | заголовок `X-Poster-Token: <token>` (чувствительные операции) |

### Формат ответов

```json
// Успех
{"code": 0, "msg": "ok", "data": {}}

// Ошибка
{"code": 1, "msg": "сообщение об ошибке", "data": null}

// Пагинация
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// Коды ошибок
// 40001 XSS-атака  40002 SQL-инъекция  40003 CRLF-инъекция  40004 Обход пути
// 40005 Слишком большой запрос  40006 Неверный Content-Type  40008 Перебор паролей
// 40009 Нарушение при загрузке файлов  40010 XXE-инъекция  40011 SSRF-атака
// 40012 Неверный HTTP-метод  40013 Ошибка заголовка Host
// 401 Не авторизован  403 Доступ запрещён  422 Ошибка валидации параметров  429 Слишком много запросов
```

### Описание ID

Все поля ID в интерфейсах представляют собой строки, закодированные hashids (например, `Ab3xK9pq`), автоматически кодируемые/декодируемые промежуточным ПО. Фронтенду не требуется обрабатывать их вручную.

---

## 1. Интерфейсы аутентификации

### 1.1 Регистрация `POST /api/auth/register`

> Требуется человеко-машинная проверка `X-Poster-Token`

**Запрос:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**Ответ:**
```json
{
  "code": 0, "msg": "регистрация успешна",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 Вход `POST /api/auth/login`

**Запрос:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Ответ:**
```json
{
  "code": 0, "msg": "вход выполнен",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "level": 1,
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.3 Обновление токена `POST /api/auth/refresh`

**Запрос:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**Ответ:**
```json
{
  "code": 0, "msg": "токен обновлён",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 Вход через соцсети `POST /api/auth/social`

**Запрос:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**Ответ:**
```json
{
  "code": 0, "msg": "вход выполнен",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. Интерфейсы товаров

### 2.1 Список товаров `GET /api/products`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| page | int | нет | Номер страницы (по умолчанию 1) |
| per_page | int | нет | Количество на странице (по умолчанию 20, макс. 100) |
| category_id | string | нет | ID категории (hashid, включает подкатегории) |
| keyword | string | нет | Ключевое слово поиска |
| sort | string | нет | Сортировка: default/price_asc/price_desc/sales/newest |
| min_price | number | нет | Минимальная цена |
| max_price | number | нет | Максимальная цена |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Ab3xK9pq",
        "title": "Product Title",
        "subtitle": "Subtitle",
        "main_image": "https://img.example.com/p1.jpg",
        "brand": "BrandName",
        "min_price": 29.99,
        "max_price": 49.99,
        "status": 2,
        "is_hot": true,
        "is_new": false,
        "sales_count": 1000
      }
    ],
    "total": 100, "page": 1, "per_page": 20
  }
}
```

### 2.2 Детали товара `GET /api/products/{id}`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| currency | string | нет | Код валюты (по умолчанию USD) |
| dest_country | string | нет | ISO2 страны назначения (по умолчанию US) |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (подбор по языку)",
    "subtitle": "Subtitle",
    "description": "Full description...",
    "brand": "BrandName",
    "main_image": "https://img.example.com/p1.jpg",
    "min_price": 29.99,
    "max_price": 49.99,
    "weight": 500,
    "unit": "piece",
    "status": 2,
    "is_hot": true,
    "is_new": false,
    "sales_count": 1000,
    "view_count": 5000,
    "skus": [
      {
        "id": "Cd4yL8rq",
        "sku_code": "SKU-RED-M",
        "attrs": {"color": "Red", "size": "M"},
        "default_price": 29.99,
        "stock": 100,
        "image": "https://img.example.com/sku1.jpg",
        "display_price": {
          "tax_exclusive": 29.99,
          "tax_inclusive": 35.99,
          "vat_amount": 6.00,
          "vat_rate": 20,
          "currency": "USD",
          "display_mode": "tax_exclusive"
        }
      }
    ],
    "images": [
      {"id": "Ef5zM9ns", "url": "https://img.example.com/p1.jpg", "is_main": true}
    ],
    "compliance_info": [
      {"category": "Знак CE", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 Отзывы о товаре `GET /api/reviews/{productId}`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| page | int | нет | Номер страницы |
| per_page | int | нет | На странице (по умолчанию 10) |
| rating | int | нет | Фильтр по оценке (1-5) |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Re1v2W3x",
        "user_id": "Ab3xK9pq",
        "product_id": "Ab3xK9pq",
        "rating": 5,
        "content": "Great product!",
        "images": ["https://img.example.com/review1.jpg"],
        "is_anonymous": false,
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 50, "page": 1, "per_page": 10
  }
}
```

---

## 3. Интерфейсы категорий

### 3.1 Список категорий `GET /api/categories`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| parent_id | int | нет | ID родительской категории (0 = верхний уровень) |

### 3.2 Дерево категорий `GET /api/categories/tree`

Возвращает полное вложенное дерево категорий.

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ct1g2H3i",
      "parent_id": 0,
      "name": "Clothing",
      "slug": "clothing",
      "icon": "icon-url",
      "level": 1,
      "is_hot": true,
      "children": [
        {
          "id": "Ct4j5K6l",
          "parent_id": "Ct1g2H3i",
          "name": "Dresses", "slug": "dresses",
          "level": 2, "is_hot": false,
          "children": []
        }
      ]
    }
  ]
}
```

---

## 4. Интерфейсы корзины `[JWT]`

### 4.1 Список корзины `GET /api/cart`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| currency | string | нет | Валюта (по умолчанию USD) |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ca1r2T3s",
      "sku_id": "Cd4yL8rq",
      "product_id": "Ab3xK9pq",
      "title": "Product Title",
      "image": "https://img.example.com/sku1.jpg",
      "attrs": {"color":"Red","size":"M"},
      "price": 29.99,
      "currency": "USD",
      "quantity": 2,
      "selected": true,
      "stock": 100
    }
  ]
}
```

### 4.2 Добавление в корзину `POST /api/cart`

**Запрос:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 Обновление количества `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> При quantity=0 позиция автоматически удаляется

### 4.4 Удаление `DELETE /api/cart/{id}`

---

## 5. Интерфейсы заказов `[JWT]`

### 5.1 Список заказов `GET /api/orders`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| status | int | нет | Фильтр статуса: 0 ожидает оплаты / 1 оплачен / 2 отправлен / 3 получен / 4 завершён / 5 отменён / 6 возврат средств / 7 средства возвращены / 8 ожидает модерации |
| page | int | нет | Номер страницы (по умолчанию 1) |
| per_page | int | нет | На странице (по умолчанию 10) |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "оплачен",
        "total_amount": 59.98, "pay_amount": 59.98,
        "currency_code": "USD",
        "created_at": "2026-05-21 10:30:00",
        "paid_at": "2026-05-21 10:31:00"
      }
    ],
    "total": 10, "page": 1, "per_page": 10
  }
}
```

### 5.2 Детали заказа `GET /api/orders/{id}`

Возвращает полную информацию о заказе, включая items/logs/documents.

### 5.3 Создание заказа `POST /api/orders` `[PosterVerify]`

**Запрос:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**Ответ:**
```json
{
  "code": 0, "msg": "заказ успешно создан",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 Отмена заказа `POST /api/orders/{id}/cancel`

> Отменить можно только при статусе=0 (ожидает оплаты)

### 5.5 Коммерческий инвойс `GET /api/orders/{id}/documents/invoice`

Возвращает ссылку на скачивание PDF-файла.

### 5.6 Упаковочный лист `GET /api/orders/{id}/documents/packing-list`

---

## 6. Интерфейсы оплаты `[JWT]`

### 6.1 Доступные способы оплаты `GET /api/payment/methods`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| country | string | нет | ISO2 (по умолчанию US) |
| currency | string | нет | Валюта (по умолчанию USD) |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "кредитная/дебетовая карта",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna — плати позже",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 Создание платежа `POST /api/payment/create` `[PosterVerify]`

**Запрос:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**Ответ:**
```json
{
  "code": 0, "msg": "платёж успешно создан",
  "data": {
    "payment_id": "Pa1y2M3t",
    "order_no": "ORD20260521A1B2C3D4",
    "amount": 59.98,
    "currency": "USD",
    "gateway": "stripe",
    "method": "card",
    "client_secret": "pi_3Nxxxx_secret_xxxx",
    "txn_id": "pi_3Nxxxxxxxxxxxx"
  }
}
```

### 6.3 Статус платежа `GET /api/payment/status/{id}`

### 6.4 Webhook-уведомление `POST /webhook/payment/{gateway}`

> JWT не требуется. Вызывается платёжным шлюзом асинхронно. Требуется проверка подписи.

---

## 7. Интерфейсы логистики

### 7.1 Расчёт доставки `GET /api/shipping/calculate`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| dest_country_id | int | да | ID страны назначения (snowflake) |
| weight | int | нет | Вес (граммы) (по умолчанию 500) |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "Северная Америка",
    "weight_kg": 0.5,
    "dest_country": "US",
    "options": [
      {
        "logistics_name": "DHL Express",
        "logistics_code": "DHL",
        "fee": 25.50,
        "estimated_days": "3-5",
        "tracking_url": "https://www.dhl.com/track?num="
      }
    ]
  }
}
```

---

## 8. Интерфейсы таможенных пошлин

### 8.1 Оценка пошлины `GET /api/tariff/estimate`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| product_id | string | да | ID товара (hashid) |
| dest_country_id | int | да | ID страны назначения |
| declared_value | number | да | Декларируемая стоимость |

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "Только для справки; фактическая сумма определяется таможней"
  }
}
```

---

## 9. Интерфейсы возвратов `[JWT]`

### 9.1 Список возвратов `GET /api/returns`

### 9.2 Заявка на возврат `POST /api/returns`

**Запрос:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 Ярлык возврата `GET /api/returns/{id}/label`

---

## 10. Интерфейсы пользователя `[JWT]`

### 10.1 Личные данные `GET /api/user/profile`

### 10.2 Обновление данных `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 Список адресов `GET /api/user/addresses`

### 10.4 Добавление адреса `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "дом"
}
```

### 10.5 Обновление адреса `PUT /api/user/addresses/{id}`

### 10.6 Удаление адреса `DELETE /api/user/addresses/{id}`

### 10.7 Язык и валюта `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. Маркетинговые интерфейсы

### 11.1 Баннеры `GET /api/banners?position=home`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| position | string | нет | Позиция: home/category/product |

### 11.2 Доступные купоны `GET /api/coupons` `[JWT]`

### 11.3 Получение купона `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 Список флеш-распродаж `GET /api/flash-sales`

### 11.5 Список групповых покупок `GET /api/group-buys`

### 11.6 Партнёрские ссылки `GET /api/affiliate/links` `[JWT]`

### 11.7 Партнёрские комиссии `GET /api/affiliate/commissions` `[JWT]`

---

## 12. Интерфейсы членства `[JWT]`

### 12.1 Информация о членстве `GET /api/membership`

**Ответ:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "current_level": {"id": "Lv1", "name": "Gold", "level": 2},
    "current_benefits": [{"benefit_type": "discount", "benefit_value": "5%"}],
    "all_levels": [],
    "current_score": 1500
  }
}
```

### 12.2 История баллов `GET /api/points`

---

## 13. Прочие интерфейсы

### 13.1 Данные о странах `GET /api/countries`

Возвращает все доступные страны/валюты/курсы/значения по умолчанию.

### 13.2 Публичные настройки `GET /api/settings?group=general`

### 13.3 Поиск ES `GET /api/search?keyword=xxx`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| keyword | string | да | Поисковый запрос |
| category_id | string | нет | Фильтр по категории |
| page | int | нет | Номер страницы |

### 13.4 Сравнение товаров `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE требует ID записи сравнения: `DELETE /api/comparisons/{id}` (`{id}` — ID записи сравнения, обязателен)

### 13.5 Персональные рекомендации `GET /api/recommendations` `[JWT]`

### 13.6 Уведомления о снижении цены `GET/POST /api/price-alerts` `[JWT]`

### 13.7 Избранное `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 Уведомления `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMS-страницы `GET /api/cms/{slug}`

### 13.11 Таблица размеров `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 Проверка соответствия `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP-определение `GET /api/geoip/detect`

### 13.14 Публикация отзыва `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 Баланс подарочной карты `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 Погашение подарочной карты `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPR-запрос `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 Экспорт заказов `GET /api/export/orders` `[JWT]`

| Параметр | Тип | Обязателен | Описание |
|------|------|------|------|
| date_from | string | нет | Дата начала (YYYY-MM-DD) |
| date_to | string | нет | Дата окончания |

Возвращает CSV-файл для скачивания.

### 13.19 B2B-запрос котировки `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 Проверка здоровья `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## Приложение: таблица статусных кодов

### Статусы заказа

| Значение | Описание |
|----|------|
| 0 | Ожидает оплаты |
| 1 | Оплачен |
| 2 | Отправлен |
| 3 | Получен |
| 4 | Завершён |
| 5 | Отменён |
| 6 | Возврат средств |
| 7 | Средства возвращены |
| 8 | Ожидает модерации (фрод-контроль) |

### Статусы товара

| Значение | Описание |
|----|------|
| 0 | Черновик |
| 1 | Ожидает модерации |
| 2 | Опубликован |
| 3 | Снят с продажи |

### Статусы платежа

| Значение | Описание |
|----|------|
| 0 | Ожидает оплаты |
| 1 | Оплачен |
| 2 | Возвращён |
| 3 | Ошибка |

### Режимы отображения цены по странам

| Значение | Описание |
|----|------|
| tax_inclusive | Цена с налогом (EU/UK) |
| tax_exclusive | Цена без налога (US/CA) |
| both | Параллельное отображение (JP) |

---

## Приложение: конвейер промежуточного ПО

```
Запрос → Cors → Security(31 тип) → RateLimit(токен-бакет) → Platform(8 платформ)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → Контроллер
```

Обозначения: `[JWT]` требуется аутентификация | `[PosterVerify]` требуется человеко-машинная проверка | без метки = публичный интерфейс

---

## Приложение: сводная статистика конечных точек

### A.1 Публичные интерфейсы (23 конечные точки)

| Метод | Путь | Описание |
|------|------|------|
| POST | /api/auth/register | Регистрация (PosterVerify) |
| POST | /api/auth/login | Вход |
| POST | /api/auth/refresh | Обновление токена |
| POST | /api/auth/social | Вход через соцсети |
| GET | /api/products | Список товаров (пагинация + фильтры + сортировка) |
| GET | /api/products/{id} | Детали товара (мультиязычность + мультивалютность + соответствие + HS) |
| GET | /api/categories | Список категорий |
| GET | /api/categories/tree | Дерево категорий |
| GET | /api/banners | Баннеры (по позиции + региону) |
| GET | /api/countries | Список стран/валют/курсов |
| GET | /api/search | Многоязычный поиск ES |
| GET | /api/reviews/{productId} | Список отзывов о товаре |
| GET | /api/flash-sales | Текущие флеш-распродажи |
| GET | /api/group-buys | Текущие групповые покупки |
| GET | /api/faq | FAQ (по языку + категории) |
| GET | /api/cms/{slug} | CMS-страница |
| GET | /api/settings | Публичные настройки |
| GET | /api/size-charts | Таблица размеров |
| GET | /api/tariff/estimate | Оценка пошлины |
| GET | /api/shipping/calculate | Расчёт доставки |
| GET | /api/payment/methods | Доступные способы оплаты |
| GET | /api/geoip/detect | GeoIP-определение |
| GET | /api/compliance/check | Проверка соответствия |

### A.2 Интерфейсы с аутентификацией (47 конечных точек)

| Метод | Путь | Описание |
|------|------|------|
| GET/PUT | /api/user/profile | Личные данные |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | CRUD адресов |
| PUT | /api/user/locale | Обновление языка/валюты |
| GET/POST | /api/wishlist[/{id}] | Избранное |
| GET/POST | /api/price-alerts | Уведомления о снижении цены |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | Корзина |
| GET/POST | /api/orders | Список/создание заказов (PosterVerify) |
| GET | /api/orders/{id} | Детали заказа |
| POST | /api/orders/{id}/cancel | Отмена заказа |
| GET | /api/orders/{id}/documents/invoice | Коммерческий инвойс |
| GET | /api/orders/{id}/documents/packing-list | Упаковочный лист |
| POST | /api/payment/create | Создание платежа (PosterVerify) |
| GET | /api/payment/status/{id} | Статус платежа |
| GET/POST | /api/returns[/{id}] | Возвраты |
| GET | /api/returns/{id}/label | Ярлык возврата |
| POST | /api/reviews | Публикация отзыва |
| GET/POST | /api/coupons[/{id}/claim] | Купоны |
| GET/PUT | /api/notifications[/{id}/read] | Уведомления |
| GET/POST/DELETE | /api/comparisons[/{id}] | Сравнение товаров |
| GET | /api/recommendations | Персональные рекомендации |
| GET | /api/affiliate/links | Партнёрские ссылки |
| GET | /api/affiliate/commissions | Партнёрские комиссии |
| GET | /api/membership | Уровень членства |
| GET | /api/points | История баллов |
| GET/POST | /api/gift-cards | Подарочные карты |
| GET/POST | /api/b2b/quotes | B2B-запрос котировки |
| GET/POST | /api/privacy/request | GDPR-запрос |
| GET | /api/export/orders | Экспорт заказов |

### A.3 Webhook (1 конечная точка)

| Метод | Путь | Описание |
|------|------|------|
| POST | /webhook/payment/{gateway} | Асинхронное уведомление об оплате (проверка подписи) |

### A.4 Admin и проверка здоровья (2 конечные точки)

| Метод | Путь | Описание |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | Выполнение возврата из админки |
| GET | /health | Проверка здоровья |

---

## Приложение: стандарты проектирования API

### Версионирование

Версия передаётся через заголовок `API-Version: 2026-05-20`, не в URL. Отображается промежуточным ПО VersionRoute.

### Конвейер промежуточного ПО

```
Cors → Security(31 тип) → RateLimit(скользящее окно) → Platform(8 платформ) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### Статистика конечных точек

- Публичные интерфейсы: 23 (аутентификация/товары/категории/контент/поиск/сервисы)
- Интерфейсы с аутентификацией: 47 (пользователь/корзина/заказы/оплата/возвраты/отзывы/маркетинг)
- Webhook: 1 (уведомление об оплате)
- Admin: 1 (выполнение возврата)
- Health: 1 (/health проверка здоровья)

### Единый формат ответа

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### Динамическая документация hg/apidoc

Генерируется автоматически с помощью hg/apidoc на основе аннотаций контроллеров. После запуска перейдите по адресу `/apidoc/`.

Пример аннотации:
```php
/**
 * @Apidoc\Title("Вход пользователя")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
