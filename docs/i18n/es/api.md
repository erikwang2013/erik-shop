# Plataforma de comercio electrónico transfronterizo — Documentación de la API

> Documentación dinámica: tras iniciar el servicio, visite http://localhost:8787/apidoc/ (generada automáticamente por hg/apidoc)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Convenciones generales

### Formato de las peticiones

| Elemento | Descripción |
|------|------|
| URL base | `http://localhost:8787/api` |
| Control de versiones | header `API-Version: 2026-05-20` (no en la URL) |
| Autenticación | header `Authorization: Bearer <token>` |
| Idioma | header `Accept-Language: zh_CN|zh_HK|en|ja|ko` |
| Plataforma | header `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` |
| Content-Type | `application/json` (POST/PUT) |
| Verificación humano-máquina | header `X-Poster-Token: <token>` (operaciones sensibles) |
| URLs de recursos | Con CDN activado, las imágenes/documentos se emiten por el dominio CDN (`https://{CDN_DOMAIN}{ruta}`, reescritura de URL origin-pull vía `Cdn::url()`) |

### Formato de las respuestas

```json
// Éxito
{"code": 0, "msg": "ok", "data": {}}

// Error
{"code": 1, "msg": "Mensaje de error", "data": null}

// Paginación
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// Códigos de error
// 40001 Ataque XSS  40002 Inyección SQL  40003 Inyección CRLF  40004 Path traversal
// 40005 Cuerpo de petición demasiado grande  40006 Content-Type incorrecto  40008 Fuerza bruta
// 40009 Subida de archivos no permitida  40010 Inyección XXE  40011 Ataque SSRF
// 40012 Método HTTP incorrecto  40013 Header Host incorrecto
// 401 No autenticado  403 Acceso denegado  422 Fallo de validación de parámetros  429 Demasiadas peticiones  503 Servicio no disponible (disyuntor/degradación)
```

### Nota sobre los IDs

Todos los campos de ID en las interfaces son cadenas codificadas con hashids (p. ej. `Ab3xK9pq`), codificadas/decodificadas automáticamente por los middlewares. El frontend no necesita procesarlas manualmente.

---

## 1. Interfaces de autenticación

### 1.1 Registro `POST /api/auth/register`

> Requiere verificación humano-máquina `X-Poster-Token`

**Petición:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**Respuesta:**
```json
{
  "code": 0, "msg": "Registro exitoso",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 Inicio de sesión `POST /api/auth/login`

**Petición:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Respuesta:**
```json
{
  "code": 0, "msg": "Inicio de sesión exitoso",
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

### 1.3 Renovar Token `POST /api/auth/refresh`

**Petición:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**Respuesta:**
```json
{
  "code": 0, "msg": "Token renovado",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 Inicio de sesión social `POST /api/auth/social`

**Petición:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**Respuesta:**
```json
{
  "code": 0, "msg": "Inicio de sesión exitoso",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. Interfaces de productos

### 2.1 Lista de productos `GET /api/products`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| page | int | No | Número de página (por defecto 1) |
| per_page | int | No | Nº de elementos por página (por defecto 20, máximo 100) |
| category_id | string | No | ID de categoría (hashid, incluye subcategorías) |
| keyword | string | No | Palabra clave de búsqueda |
| sort | string | No | Ordenación: default/price_asc/price_desc/sales/newest |
| min_price | number | No | Precio mínimo |
| max_price | number | No | Precio máximo |

**Respuesta:**
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

### 2.2 Detalle de producto `GET /api/products/{id}`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| currency | string | No | Código de moneda (por defecto USD) |
| dest_country | string | No | País de destino ISO2 (por defecto US) |

**Respuesta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (coincidencia multilingüe)",
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
      {"category": "Marcado CE", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 Reseñas de producto `GET /api/reviews/{productId}`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| page | int | No | Número de página |
| per_page | int | No | Por página (por defecto 10) |
| rating | int | No | Filtro por puntuación (1-5) |

**Respuesta:**
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

## 3. Interfaces de categorías

### 3.1 Lista de categorías `GET /api/categories`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| parent_id | int | No | ID de categoría padre (0=raíz) |

### 3.2 Árbol de categorías `GET /api/categories/tree`

Devuelve el árbol de categorías anidadas completo.

**Respuesta:**
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

## 4. Interfaces de carrito `[JWT]`

### 4.1 Lista del carrito `GET /api/cart`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| currency | string | No | Moneda (por defecto USD) |

**Respuesta:**
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

### 4.2 Añadir al carrito `POST /api/cart`

**Petición:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 Actualizar cantidad `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> Si quantity=0 se elimina automáticamente

### 4.4 Eliminar `DELETE /api/cart/{id}`

---

## 5. Interfaces de pedidos `[JWT]`

### 5.1 Lista de pedidos `GET /api/orders`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| status | int | No | Filtro de estado:0 pendiente de pago/1 pagado/2 enviado/3 recibido/4 completado/5 cancelado/6 en reembolso/7 reembolsado/8 pendiente de revisión |
| page | int | No | Número de página (por defecto 1) |
| per_page | int | No | Por página (por defecto 10) |

**Respuesta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "Pagado",
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

### 5.2 Detalle de pedido `GET /api/orders/{id}`

Devuelve la información completa del pedido, incluyendo items/logs/documents.

### 5.3 Crear pedido `POST /api/orders` `[PosterVerify]`

**Petición:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**Respuesta:**
```json
{
  "code": 0, "msg": "Pedido creado con éxito",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 Cancelar pedido `POST /api/orders/{id}/cancel`

> Solo se puede cancelar si el estado=0 (pendiente de pago)

### 5.5 Factura comercial `GET /api/orders/{id}/documents/invoice`

Devuelve el enlace de descarga del PDF.

### 5.6 Lista de embalaje `GET /api/orders/{id}/documents/packing-list`

---

## 6. Interfaces de pago `[JWT]`

### 6.1 Métodos de pago disponibles `GET /api/payment/methods`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| country | string | No | ISO2 (por defecto US) |
| currency | string | No | Moneda (por defecto USD) |

**Respuesta:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "Tarjeta de crédito/débito",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna compra ahora paga después",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 Crear pago `POST /api/payment/create` `[PosterVerify]`

**Petición:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**Respuesta:**
```json
{
  "code": 0, "msg": "Pago iniciado con éxito",
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

### 6.3 Estado del pago `GET /api/payment/status/{id}`

### 6.4 Callback Webhook `POST /webhook/payment/{gateway}`

> No requiere JWT. Lo invoca la pasarela de pago de forma asíncrona. Requiere verificación de firma.

---

## 7. Interfaces de logística

### 7.1 Cálculo de flete `GET /api/shipping/calculate`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| dest_country_id | int | Sí | ID del país de destino (snowflake) |
| weight | int | No | Peso (gramos) (por defecto 500) |

**Respuesta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "Zona Norteamérica",
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

## 8. Interfaces de aranceles

### 8.1 Estimación de aranceles `GET /api/tariff/estimate`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| product_id | string | Sí | ID de producto (hashid) |
| dest_country_id | int | Sí | ID del país de destino |
| declared_value | number | Sí | Valor declarado |

**Respuesta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "Solo como referencia, el valor real lo determina la aduana"
  }
}
```

---

## 9. Interfaces de devolución `[JWT]`

### 9.1 Lista de devoluciones `GET /api/returns`

### 9.2 Solicitar devolución `POST /api/returns`

**Petición:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 Etiqueta de devolución `GET /api/returns/{id}/label`

---

## 10. Interfaces de usuario `[JWT]`

### 10.1 Información personal `GET /api/user/profile`

### 10.2 Actualizar información `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 Lista de direcciones `GET /api/user/addresses`

### 10.4 Añadir dirección `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "Casa"
}
```

### 10.5 Actualizar dirección `PUT /api/user/addresses/{id}`

### 10.6 Eliminar dirección `DELETE /api/user/addresses/{id}`

### 10.7 Idioma y moneda `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. Interfaces de marketing

### 11.1 Banners `GET /api/banners?position=home`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| position | string | No | Posición: home/category/product |

### 11.2 Cupones disponibles `GET /api/coupons` `[JWT]`

### 11.3 Reclamar cupón `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 Lista de ventas flash `GET /api/flash-sales`

### 11.5 Lista de compras grupales `GET /api/group-buys`

### 11.6 Enlaces de afiliado `GET /api/affiliate/links` `[JWT]`

### 11.7 Comisiones de afiliado `GET /api/affiliate/commissions` `[JWT]`

---

## 12. Interfaces de membresía `[JWT]`

### 12.1 Información de membresía `GET /api/membership`

**Respuesta:**
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

### 12.2 Registro de puntos `GET /api/points`

---

## 13. Otras interfaces

### 13.1 Datos de países `GET /api/countries`

Devuelve todos los países/monedas/tipos de cambio/valores por defecto disponibles.

### 13.2 Configuración pública `GET /api/settings?group=general`

### 13.3 Búsqueda ES `GET /api/search?keyword=xxx`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| keyword | string | Sí | Término de búsqueda |
| category_id | string | No | Filtro de categoría |
| page | int | No | Número de página |

### 13.4 Comparación de productos `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE requiere el id del registro de comparación: `DELETE /api/comparisons/{id}` (`{id}` es el ID del registro de comparación, obligatorio)

### 13.5 Recomendaciones personalizadas `GET /api/recommendations` `[JWT]`

### 13.6 Alertas de bajada de precio `GET/POST /api/price-alerts` `[JWT]`

### 13.7 Favoritos `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 Notificaciones `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 Páginas CMS `GET /api/cms/{slug}`

### 13.11 Tabla de tallas `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 Comprobación de cumplimiento `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 Detección GeoIP `GET /api/geoip/detect`

### 13.14 Publicar reseña `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 Saldo de tarjeta de regalo `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 Canjear tarjeta de regalo `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 Solicitud GDPR `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 Exportar pedidos `GET /api/export/orders` `[JWT]`

| Parámetro | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| date_from | string | No | Fecha de inicio (YYYY-MM-DD) |
| date_to | string | No | Fecha de fin |

Devuelve la descarga de un archivo CSV.

### 13.19 Solicitud de cotización B2B `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 Comprobación de salud `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## Apéndice: correspondencia de códigos de estado

### Estados de pedido

| Valor | Descripción |
|----|------|
| 0 | Pendiente de pago |
| 1 | Pagado |
| 2 | Enviado |
| 3 | Recibido |
| 4 | Completado |
| 5 | Cancelado |
| 6 | En reembolso |
| 7 | Reembolsado |
| 8 | Pendiente de revisión (riesgo) |

### Estados de producto

| Valor | Descripción |
|----|------|
| 0 | Borrador |
| 1 | Pendiente de revisión |
| 2 | Publicado |
| 3 | Retirado |

### Estados de pago

| Valor | Descripción |
|----|------|
| 0 | Pendiente de pago |
| 1 | Pagado |
| 2 | Reembolsado |
| 3 | Fallido |

### Modos de visualización de precios por país

| Valor | Descripción |
|----|------|
| tax_inclusive | Precio con impuestos (EU/UK) |
| tax_exclusive | Precio sin impuestos (US/CA) |
| both | Mostrar ambos (JP) |

---

## Apéndice: pipeline de middlewares

```
Petición → Cors → Security(31 tipos) → RateLimit(token bucket) → Platform(8 plataformas)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → Controlador
```

Marcas: `[JWT]` requiere autenticación | `[PosterVerify]` requiere verificación humano-máquina | sin marca = interfaz pública

---

## Apéndice: resumen de estadísticas de endpoints

### A.1 Interfaces públicas (23 endpoints)

| Método | Ruta | Descripción |
|------|------|------|
| POST | /api/auth/register | Registro (PosterVerify) |
| POST | /api/auth/login | Inicio de sesión |
| POST | /api/auth/refresh | Renovar Token |
| POST | /api/auth/social | Inicio de sesión social |
| GET | /api/products | Lista de productos (paginación + filtros + ordenación) |
| GET | /api/products/{id} | Detalle de producto (multilingüe + multimoneda + cumplimiento + HS) |
| GET | /api/categories | Lista de categorías |
| GET | /api/categories/tree | Árbol de categorías |
| GET | /api/banners | Banners (por posición + región) |
| GET | /api/countries | Lista de países/monedas/tipos de cambio |
| GET | /api/search | Búsqueda multilingüe ES |
| GET | /api/reviews/{productId} | Lista de reseñas de producto |
| GET | /api/flash-sales | Ventas flash actuales |
| GET | /api/group-buys | Compras grupales actuales |
| GET | /api/faq | FAQ (por idioma + categoría) |
| GET | /api/cms/{slug} | Páginas CMS |
| GET | /api/settings | Configuración pública |
| GET | /api/size-charts | Tabla de tallas |
| GET | /api/tariff/estimate | Estimación de aranceles |
| GET | /api/shipping/calculate | Cálculo de flete |
| GET | /api/payment/methods | Métodos de pago disponibles |
| GET | /api/geoip/detect | Detección GeoIP |
| GET | /api/compliance/check | Comprobación de cumplimiento |

### A.2 Interfaces autenticadas (47 endpoints)

| Método | Ruta | Descripción |
|------|------|------|
| GET/PUT | /api/user/profile | Información personal |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | CRUD de direcciones |
| PUT | /api/user/locale | Actualizar idioma/moneda |
| GET/POST | /api/wishlist[/{id}] | Favoritos |
| GET/POST | /api/price-alerts | Alertas de bajada de precio |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | Carrito |
| GET/POST | /api/orders | Lista/creación de pedidos (PosterVerify) |
| GET | /api/orders/{id} | Detalle de pedido |
| POST | /api/orders/{id}/cancel | Cancelar pedido |
| GET | /api/orders/{id}/documents/invoice | Factura comercial |
| GET | /api/orders/{id}/documents/packing-list | Lista de embalaje |
| POST | /api/payment/create | Crear pago (PosterVerify) |
| GET | /api/payment/status/{id} | Estado del pago |
| GET/POST | /api/returns[/{id}] | Devoluciones |
| GET | /api/returns/{id}/label | Etiqueta de devolución |
| POST | /api/reviews | Publicar reseña |
| GET/POST | /api/coupons[/{id}/claim] | Cupones |
| GET/PUT | /api/notifications[/{id}/read] | Notificaciones |
| GET/POST/DELETE | /api/comparisons[/{id}] | Comparación de productos |
| GET | /api/recommendations | Recomendaciones personalizadas |
| GET | /api/affiliate/links | Enlaces de afiliado |
| GET | /api/affiliate/commissions | Comisiones de afiliado |
| GET | /api/membership | Niveles de membresía |
| GET | /api/points | Registro de puntos |
| GET/POST | /api/gift-cards | Tarjetas de regalo |
| GET/POST | /api/b2b/quotes | Cotizaciones B2B |
| GET/POST | /api/privacy/request | Solicitudes GDPR |
| GET | /api/export/orders | Exportar pedidos |

### A.3 Webhook (1 endpoint)

| Método | Ruta | Descripción |
|------|------|------|
| POST | /webhook/payment/{gateway} | Notificación asíncrona de pago (verificación de firma) |

### A.4 Admin y comprobación de salud (2 endpoints)

| Método | Ruta | Descripción |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | Ejecución de reembolso desde el panel |
| GET | /health | Comprobación de salud |

---

## Apéndice: especificaciones de diseño de la API

### Control de versiones

La versión se transmite mediante el header `API-Version: 2026-05-20`, no en la URL. Lo mapea el middleware VersionRoute.

### Pipeline de middlewares

```
Cors → Security(31 tipos) → RateLimit(ventana deslizante) → Platform(8 plataformas) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### Estadísticas de endpoints

- Interfaces públicas: 23 (autenticación/productos/categorías/contenido/búsqueda/servicios)
- Interfaces autenticadas: 47 (usuario/carrito/pedidos/pago/devolución/reseñas/marketing)
- Webhook: 1 (callback de pago)
- Admin: 1 (ejecución de reembolso)
- Health: 1 (/health)

### Respuesta unificada

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### Documentación dinámica hg/apidoc

Se genera automáticamente a partir de las anotaciones de los controladores mediante hg/apidoc. Tras el arranque, visite `/apidoc/`.

Ejemplo de anotación:
```php
/**
 * @Apidoc\Title("Inicio de sesión de usuario")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
