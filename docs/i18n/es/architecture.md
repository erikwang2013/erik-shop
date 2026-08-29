# Plataforma de comercio electrónico transfronterizo — Resumen de arquitectura

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Stack tecnológico

| Capa | Tecnología | Versión |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| Clientes | Flutter (5 plataformas) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| Base de datos | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| Pagos | Stripe / PayPal / Klarna / Adyen | — |

## 2. Estructura de directorios

```
shop-php/
  service/            API de negocio (251 archivos PHP)
    config/            36 configuraciones (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/cdn/...)
    app/controller/    39 controladores (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 modelos (BaseModel + 110 modelos de negocio)
    app/middleware/     14 middlewares (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 clases de utilidades (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (sustituido por install.sql en la raíz) + seeders
    tests/              4 clases de prueba (22 tests, 45 assertions)
  admin/             Panel de administración (239 archivos PHP)
    plugin/admin/app/controller/shop/ 82 controladores
    plugin/admin/app/model/shop/      76 modelos
    plugin/admin/app/view/shop/       Panel ECharts
    app/middleware/    5 middlewares (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              Clientes
    flutter/lib/      25 Dart (11 páginas + capa central + enrutado)
    harmonyos/        14 ArkTS (9 páginas + cliente API + estado global)
  docs/               5 documentos de diseño
  .claude/skills/     38 Skills de normas de desarrollo
```

## 3. Pipeline de middlewares

```
Service: Cors → Security(detección de 31 tipos de ataques) → RateLimit(limitación token bucket) → Platform(identificación de 8 plataformas)
        → GeoIp(región) → Locale(idioma) → HashidsDecode → VersionRoute
        → (PosterVerify verificación humano-máquina) → (JwtAuth Token) → HashidsEncode → Encryption(cifrado de interfaces)

Admin:  Security → Platform → HashidsDecode → AccessControl(RBAC integrado) → HashidsEncode
```

## 4. Seguridad

- **Detección de 31 tipos de ataques**: XSS/inyección SQL/inyección de comandos/CRLF/recorrido de rutas/Body/ContentType/subida de archivos/fuerza bruta/XXE/SSRF/deserialización/LDAP/cabeceras de correo/SSTI/NoSQL/open redirect/ataques JWT/Host/request smuggling/GraphQL/XPATH/Log4Shell/SSI/fórmulas CSV/fugas de datos/prototipo pollution/WebSocket/CORS/DNS rebinding/métodos HTTP/CSRF Origin
- **Cifrado en tres capas**: capa de interfaz (AES-256-CBC) + capa de base de datos (trait Encryptable) + ofuscación de ID (Hashids)
- **Seguimiento de plataforma**: 8 plataformas (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + header X-Platform + registro en 6 tablas

## 5. Alta concurrencia

- **Limitación**: token bucket con ventana deslizante (Redis ZSET), reglas para 6 endpoints
- **Disyuntor/degradación**: disyuntor Redis — llamadas a API externas (pasarelas de pago/inicio de sesión social), 5 fallos consecutivos → 30s abierto, sonda semiabierta con recuperación automática; las excepciones de negocio no cuentan como fallos; ante fallo de Redis, degradación automática con pase directo (503)
- **BD**: separación lectura/escritura (2 réplicas de lectura + sticky) + pool de conexiones (50/10)
- **Operaciones lentas**: gestionadas por procesos Cron independientes (sincronización de Feed/cálculo de recomendaciones/conciliación de pagos/liquidación de repartos, etc.)

## 6. Pruebas

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+fugas de datos
- JwtTest (4): validación encode/decode
- ApiResponseTest (3): success/fail/paginate

## 7. Despliegue

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# Manual
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **CDN**: caché de borde origin-pull — las URLs de recursos se emiten por el dominio CDN en la salida (`Cdn::url()` reescribe a `https://{CDN_DOMAIN}`, CNAME de vuelta al dominio de admin); cabeceras de caché nginx `immutable` (7 días); invalidación automática en el CRUD de productos/banners (fail-open)
- **Multilingüe (i18n)**: archivos de traducción de 5 idiomas + LocaleMiddleware + Flutter AppLocalizations
- **Documentación de API**: generada automáticamente por hg/apidoc (6 grupos, impulsada por anotaciones de controladores)
- **Seguimiento de plataforma**: 8 plataformas, header X-Platform + registro en BD

Detalles: [Documento de despliegue](deployment.md) | [Documento de arquitectura completa](architecture-full.md) | [Documento de diseño de funciones](features.md)
