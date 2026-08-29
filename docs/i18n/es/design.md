# Plataforma de comercio electrónico transfronterizo — Documento de diseño

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Diseño de la base de datos

### 1.1 Convenciones de nomenclatura

- Prefijo de tabla: `erik_`
- Clave primaria: `id BIGINT UNSIGNED NOT NULL` (generada por snowflake, no autoincremental)
- Marcas de tiempo: `created_at`, `updated_at`, `deleted_at` (borrado suave)
- Motor: InnoDB, juego de caracteres: utf8mb4_unicode_ci

### 1.2 División de módulos (110 tablas)

| Módulo | Nº de tablas | Tablas principales |
|------|------|--------|
| Usuarios y cuentas | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Productos y categorías | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Transacciones | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Pagos y fondos | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Logística | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Aduanas e impuestos | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Marketing | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Cadena de suministro | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Riesgo y cumplimiento | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Multiplataforma | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Contenido y experiencia | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Suscripciones y B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Atención al cliente | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| Pruebas AB | 3 | ab_tests, ab_test_variants, ab_test_results |
| Gobernanza de API | 2 | api_rate_limits, api_docs |
| Datos base | 3 | countries, currencies, exchange_rates |

### 1.3 Campos de seguimiento de plataforma

| Tabla | Campo | Descripción |
|----|------|------|
| orders | platform VARCHAR(16) | Plataforma de pedido |
| payments | platform VARCHAR(16) | Plataforma de pago |
| operation_logs | platform VARCHAR(16) | Plataforma de operación |
| users | last_login_platform VARCHAR(16) | Plataforma del último inicio de sesión |
| search_logs | platform VARCHAR(16) | Plataforma de búsqueda |
| chat_messages | platform VARCHAR(16) | Origen del mensaje |

---

## 2. Diseño de API

Control de versiones de API, pipeline de middlewares, estadísticas de endpoints y la norma de respuesta unificada, ver [Documento de interfaz API](api.md).

---

## 3. Diseño de seguridad

### 3.1 SecurityMiddleware encapsula los 31 detectores de security-php

| # | Tipo | Código de error | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | Inyección SQL | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Recorrido de rutas | 40004 | ✅ | ✅ |
| 5 | Body demasiado grande | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | Subida de archivos | 40009 | ✅ | ✅ |
| 8 | Cabeceras de seguridad de respuesta | — | ✅ | ✅ |
| 9 | Fuerza bruta | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | Método HTTP | 40012 | ✅ | ✅ |
| 13 | Cabecera Host | 40013 | ✅ | — |
| 14 | Enmascaramiento de datos sensibles | — | ✅ | ✅ |
| 15 | Lista blanca CORS | — | ⚠️ | ⚠️ |

### 3.2 Cifrado en tres capas

| Capa | Tecnología | Paquete |
|------|------|-----|
| Capa de transporte | AES-256-CBC | erikwang2013/encryption |
| Capa de base de datos | Trait Encryptable | erikwang2013/encryptable (Maize) |
| Ofuscación de ID | Hashids | erikwang2013/hashids |

---

## 4. Diseño de alta concurrencia

### 4.1 Limitación de velocidad

Token bucket con ventana deslizante (Redis ZSET, vía facade `support\Redis`): por defecto 60s/100 veces, inicio de sesión 10 veces/60s, registro 5 veces/300s, inicio de sesión social 5 veces/300s, pago 5 veces/60s, pedido 3 veces/10s, búsqueda 10 veces/1s

### 4.2 Disyuntor y degradación

Disyuntor Redis (`app\common\CircuitBreaker`): todas las llamadas a API externas como pasarelas de pago/inicio de sesión social pasan por `CircuitBreaker::call()` — 5 fallos consecutivos abren el disyuntor durante 30s; al expirar el TTL, la siguiente petición realiza automáticamente una sonda semiabierta y se restablece si tiene éxito. La lista blanca de rechazos de negocio (tarjeta no válida/token no válido) nunca cuenta como fallo, evitando que los atacantes tumben los servicios dependientes con peticiones basura; cuando Redis no está disponible, degrada automáticamente a pase directo. Mientras el disyuntor está abierto, las APIs devuelven 503 «Servicio no disponible».

### 4.3 Usos de Redis

Redis se usa para el token bucket de limitación (facade `support\Redis`), los códigos de verificación humano-máquina y el almacenamiento de Session; los datos de negocio no tienen caché a nivel de aplicación, se leen directamente de MySQL (separación lectura/escritura + pool de conexiones). Los recursos estáticos (imágenes/documentos) se sirven mediante caché de borde CDN (origin-pull, 7 días immutable, invalidación automática vía `Cdn::purge` en el CRUD, fail-open).

### 4.4 Pool de conexiones

MySQL: 50max/10min/2s de timeout | Separación lectura/escritura: 30max/5min (2 réplicas de lectura, sticky=true) | Redis: 30max/5min



---

## 5. Internacionalización

- Interfaz: zh_CN, zh_HK, en, ja, ko
- Contenido: erik_product_translations con filas independientes por locale
- Precio: erik_product_sku_prices con precios independientes por moneda
- Header: Accept-Language + API-Version

## 6. Documentación de API

Generada automáticamente con hg/apidoc según las anotaciones de los controladores, ver [Documento de interfaz API](api.md). Tras iniciar, acceder a `/apidoc/`.

## 7. Pruebas

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

Detalles: [Documento de diseño de funciones](features.md) | [Documento de arquitectura completa](architecture-full.md) | [Documento de despliegue](deployment.md)
