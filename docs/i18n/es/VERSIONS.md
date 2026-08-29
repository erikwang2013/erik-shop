# Erik Shop — Plataforma de comercio electrónico transfronterizo
Plataforma de comercio electrónico transfronterizo full-stack construida sobre la familia de paquetes webman, que cubre los escenarios B2C/B2B y la incorporación de vendedores de terceros.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Resumen de versiones

| | Versión simplificada (Lite) | Versión estándar (Standard) | Versión completa (Full) |
|---|:---:|:---:|:---:|
| **Posicionamiento** | Desarrollador individual / pequeña tienda online | Comerciante transfronterizo en crecimiento | Plataforma full-stack de nivel empresarial |
| **Licencia** | MIT código abierto | Licencia comercial | Licencia comercial |
| **Obtención** | Descarga pública en GitHub | Contactar erik@erik.xyz | Contactar erik@erik.xyz |
| **Rama** | `lite` | `standard` | `full` |
| **Actual** | — | — | ✅ |

---

## 2026-08-27 Disyuntor y degradación

- Nuevo disyuntor Redis `CircuitBreaker` (`service/app/common/CircuitBreaker.php`): llamadas externas de pasarelas de pago (Stripe/PayPal/Klarna/Adyen) e inicio de sesión social protegidas uniformemente — 5 fallos consecutivos → 30s abierto, al expirar el TTL sonda semiabierta con recuperación automática
- Lista blanca de rechazos de negocio: tarjeta no válida/token no válido no cuentan como fallos del disyuntor (evita que peticiones basura tumben los servicios dependientes)
- Fallo de Redis: degradación automática con pase directo; mientras está abierto, las APIs devuelven 503 «Servicio no disponible»
- Parámetros: `config/concurrency.php` → `circuit_breaker` (fail_threshold=5, open_seconds=30)

---

## 2026-08-29 Soporte CDN

- **Modelo origin-pull**: las subidas permanecen en el disco local del origin de admin; la base de datos solo guarda rutas relativas (migración cero); en los límites de salida `Cdn::url()` reescribe a `https://{CDN_DOMAIN}{ruta}`; el dominio CDN apunta por CNAME de vuelta al dominio de admin
- **Abstracción unificada de proveedores**: `CdnProviderInterface` (purge / purgeByTag / preload), implementada para Cloudflare, AWS CloudFront, Aliyun y Tencent Cloud (Fastly/Akamai reservados); matriz de capacidades: purge 4/4, preload 2/4 (Aliyun/Tencent), purgeByTag 1/4 (Cloudflare)
- **Configuración en el panel de administración**: página de gestión CDN (3 pestañas: Configuración/Invalidación/Registros) — interruptores de activación por proveedor, credenciales (JSON de configuración cifrado en reposo), prueba de conectividad, invalidación/preload manuales, registros de invalidación (tablas `wa_cdn_providers` / `wa_cdn_purge_logs`); la configuración de BD sobrescribe .env; el interruptor global se propaga al servicio vía Redis compartido (prefijo `shop:`, TTL 60s)
- **Invalidación automática (fail-open)**: el CRUD de productos y banners dispara la invalidación automáticamente; un fallo de CDN nunca bloquea el CRUD de admin
- **Caché de borde**: nginx `location /app/admin/upload/` `expires 7d; Cache-Control public, max-age=604800, immutable`; los directorios de subida persisten mediante volúmenes Docker (`admin_uploads:/app/plugin/admin/public/upload`, `service_public:/app/public/documents`)
- **Configuración**: `config/cdn.php` (admin + service) + 13 variables de entorno `CDN_*` (CDN_ENABLED / CDN_DEFAULT_PROVIDER / CDN_DOMAIN / credenciales por proveedor)

---

## Registro de correcciones 2026-08-07

| # | Problema | Gravedad | Corrección |
|---|------|--------|------|
| 1 | El cifrado de respuestas API no estaba conectado al middleware | Media | Se creó EncryptionMiddleware (impulsado por el header X-Encrypt-Response), registrado como nivel 10 del pipeline de service |
| 2 | Desajuste entre el nombre de clase Encryption y el nombre de archivo EncryptionHelper.php | Media | Renombrado a Encryption.php, corrección del autoloading PSR-4 |
| 3 | JWT_SECRET_KEY vacío | Baja | Se generó una clave de 32 bytes, configurando tanto JWT_SECRET como JWT_SECRET_KEY |
| 4 | config/middleware.php como array indexado causaba "Bad middleware config" y el colapso de todos los workers | Crítica | Cambiado a la estructura estándar `'' => [...]` (webman exige appName => lista) |
| 5 | La configuración del plugin security-php sin clave enable era omitida silenciosamente por Config::loadFromDir | Crítica | Se añadió `'enable' => true` al app.php del plugin en service/admin |
| 6 | config/bootstrap.php referenciaba support\bootstrap\Db/Redis inexistentes | Crítica | Eliminado; la inicialización de Eloquent ahora se hace vía support/bootstrap.php que requiere Db.php de vendor/webman/database |
| 7 | La función global redis() no existe (webman 2.x no la tiene), limitación/gestión de riesgos fallaban silenciosamente | Alta | Se creó la facade support\Redis (illuminate/redis + phpredis), se registró la función auxiliar redis() en app/functions.php |
| 8 | Faltaban parámetros del constructor de RedisManager (requiere 3: contenedor app/driver/config) | Alta | Se pasó un contenedor stdClass como placeholder + driver phpredis + configuración de conexión |
| 9 | El modelo referenciaba el trait Erik\Encryptable\Encryptable inexistente (el paquete contiene CastsAttributes del namespace Maize\Encryptable) | Crítica | Se creó la capa de compatibilidad con el trait clásico service/Erik/Encryptable/Encryptable.php (reutiliza internamente Encryption::php del paquete) |
| 10 | El Installer.php del plugin de composer con funciones de nivel superior duplicadas causaba error fatal | Media | Guarda idempotente function_exists (corregido en los dos vendor de service/admin) |
| 11 | getHeader() de HashidsEncode devolvía string y causaba error en implode | Alta | Conversión forzada a (array) |
| 12 | docker-compose/.env.example tenían claves JWT/cifrado reales hardcodeadas | Crítica | Sustituidas por placeholders change_me; el asistente de instalación genera claves aleatorias |
| 13 | Creación de pedidos sin transacción, decremento de inventario no atómico (sobreventa concurrente) | Crítica | Db::transaction + decrement atómico condicional |
| 14 | Sobredistribución/sobrecanje concurrente al recibir cupones | Alta | Transacción + bloqueo de fila lockForUpdate + candado atómico de received_qty |
| 15 | Campos de verificación de firma del Webhook de PayPal siempre vacíos (verify-webhook-signature falla siempre) | Alta | Los cinco campos de verificación se reenvían desde los headers de la solicitud |
| 16 | Inyección SQL en el asistente de instalación (nombre de base de datos/contraseña concatenados) | Alta | quote + escape con backticks + escritura de configuración con var_export |
| 17 | Degradación silenciosa cuando faltan claves de cifrado/hash | Alta | Encryption/HashidsHelper lanzan excepción si el valor está vacío o la longitud es inválida |
| 18 | Exportación de pedidos con nombre de archivo fijo que se sobrescribía en concurrencia | Media | Nombre de archivo uniqid + limpieza en shutdown + try/catch |
| 19 | La decodificación Hashids no escribía de vuelta en los parámetros de solicitud (parámetros de ruta/GET/POST) | Alta | setParams/setGet/setPost escriben de vuelta |
| 20 | composer.lock ignorado por gitignore (build no reproducible) | Media | Eliminado del ignore, incluido en el control de versiones |
| 21 | Contenedores sin healthcheck, sin dependencias de arranque | Media | healthcheck en todos los servicios + condition en depends_on |
| 22 | Dockerfile de admin no ejecutable | Alta | Se añadieron COPY + composer install + EXPOSE + CMD |
| 23 | Errores de compilación de Flutter (conflicto de intl/genéricos de constructores/paréntesis sobrantes) + test pendiente de Timer | Alta | intl ^0.20.2, fábricas estáticas, pump para avanzar el reloj |
| 24 | 27 errores de compilación ArkTS en HarmonyOS que impedían generar el paquete | Alta | Interfaces explícitas, renombrado de palabras reservadas, build de raíz única, imports @kit, configuración de hvigor |

---

## Comparación de funciones

> Nota: ◐ = estructura de tablas creada, negocio pendiente de implementar (actualmente solo existen tablas y modelos, sin API/código de negocio o solo implementación parcial)

### Sistema de usuarios

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Registro/inicio de sesión por email (JWT) | ✅ | ✅ | ✅ |
| Inicio de sesión social (Google/Apple/Facebook) | — | ✅ | ✅ |
| Gestión de direcciones | ✅ | ✅ | ✅ |
| Niveles de membresía + puntos | — | — | ◐ |
| Tarjetas de regalo | — | — | ✅ |
| Verificación de identidad KYC | — | — | ✅ |

### Sistema de productos

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de categorías (árbol) | ✅ | ✅ | ✅ |
| SKU + atributos | ✅ | ✅ | ✅ |
| Imágenes de producto | ✅ | ✅ | ✅ |
| Contenido multilingüe | — | ✅ | ✅ |
| Precios independientes por moneda | — | ✅ | ✅ |
| Reseñas de productos | ✅ | ✅ | ✅ |
| Etiquetas de cumplimiento (FDA/CE/RoHS) | — | ✅ | ✅ |
| Búsqueda multilingüe ES | — | ✅ | ✅ |
| Sincronización de Feed de productos (Google/Meta) | — | — | ✅ |
| Tabla de tallas | — | — | ✅ |

### Sistema de transacciones

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Carrito de compras | ✅ | ✅ | ✅ |
| Gestión de pedidos | ✅ | ✅ | ✅ |
| Pagos (Stripe) | ✅ | ✅ | ✅ |
| Pagos (PayPal) | ✅ | ✅ | ✅ |
| Pagos (Klarna/Adyen) | — | Placeholder | Placeholder |
| BNPL compra ahora paga después | — | Placeholder | Placeholder |
| Reembolsos | ✅ | ✅ | ✅ |
| Gestión de devoluciones | — | ✅ | ✅ |
| Factura comercial/lista de embalaje | — | ✅ | ✅ |
| Seguro de envío | — | — | ◐ |

### Logística transfronteriza

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de operadores logísticos internacionales | — | ✅ | ✅ |
| Zonas logísticas + tarifas escalonadas | — | ✅ | ✅ |
| Almacenes en el extranjero (envío + devolución) | — | ✅ | ✅ |
| Declaración HS | — | En planificación | En planificación |
| Seguimiento de envíos | — | ✅ | ✅ |
| Gestión de inventario multi-almacén | — | — | ✅ |

### Aduanas e impuestos

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Biblioteca de códigos HS Code | — | ✅ | ✅ |
| Configuración de reglas arancelarias | — | ✅ | ✅ |
| Configuración de VAT/IOSS | — | ✅ | ✅ |
| Restricciones de cumplimiento por país | — | ✅ | ✅ |
| Cumplimiento de precios en la visualización (con/sin impuestos) | — | ✅ | ✅ |

### Herramientas de marketing

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cupones | ✅ | ✅ | ✅ |
| Banners | ✅ | ✅ | ✅ |
| Ventas flash | — | ✅ | ✅ |
| Compras grupales | — | ✅ | ✅ |
| Distribución (enlace + comisión + retiro) | — | ✅ | ✅ |
| Promociones por región | — | ✅ | ✅ |

### Cadena de suministro

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de proveedores | — | — | ✅ |
| Órdenes de compra | — | — | ◐ |
| Inspección de calidad (control de entrada + salida) | — | — | ◐ |
| Registro de inventario (libro mayor inmutable) | — | — | ✅ |
| Transferencia de inventario | — | — | ◐ |

### Expansión de plataforma

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de múltiples tiendas | — | — | ✅ |
| Incorporación de múltiples vendedores (terceros) | — | — | ✅ |
| Publicación en Amazon/eBay/Shopee | — | — | ✅ |
| Agregación de pedidos multi-plataforma | — | — | ✅ |
| Mayorista B2B (precios escalonados/cotizaciones) | — | — | ✅ |

### Gestión de riesgos y cumplimiento

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Detección de ataques básica (XSS/SQLi) | ✅ | ✅ | ✅ |
| Detección de ataques ampliada (XXE/SSRF, etc.) | — | — | ✅ |
| Verificación humano-máquina PosterVerify | — | ✅ | ✅ |
| Motor de reglas de riesgo | — | — | ✅ |
| Solicitudes de datos GDPR/CCPA | — | — | ✅ |
| Gestión de consentimiento de cookies | — | — | ✅ |
| Seguimiento de origen de plataforma | — | ✅ | ✅ |
| Seguimiento de origen de plataforma (8 plataformas) | — | ✅ | ✅ |

### Alta concurrencia

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| Pool de conexiones de BD | ✅ | ✅ | ✅ |
| Limitación con token bucket | — | — | ✅ |
| Separación lectura/escritura de BD | — | — | ✅ |
| Tareas programadas Cron (11) | — | — | ✅ |
| Caché de borde CDN (origin-pull) | ✅ | ✅ | ✅ |

### Contenido y crecimiento

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Notificaciones del sistema | ✅ | ✅ | ✅ |
| Plantillas de correo | — | — | ✅ |
| Páginas CMS multilingües | — | — | ✅ |
| FAQ + base de conocimientos | — | — | ◐ |
| Compras por suscripción | — | — | ✅ |
| Pruebas A/B | — | — | ◐ |
| Atención al cliente en tiempo real (IM WebSocket) | — | — | ✅ |

### Clientes

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| Internacionalización (traducción de 5 idiomas) | ✅ | ✅ | ✅ |
| Documentación de API (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Panel ECharts de Admin | ✅ | ✅ | ✅ |
| Exportación Excel/PDF de Admin | ✅ | ✅ | ✅ |
| Interfaz multilingüe (5 idiomas) | ✅ | ✅ | ✅ |

---

## Comparación de diseño

### Base de datos

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Tablas de datos | **23** | **62** | **110** |
| Relacionadas con usuarios | 3 | 5 | 7 |
| Relacionadas con productos | 6 | 15 | 19 |
| Relacionadas con transacciones | 6 | 9 | 9 |
| Relacionadas con logística | 0 | 7 | 9 |
| Relacionadas con aduanas | 0 | 5 | 5 |
| Relacionadas con marketing | 4 | 8 | 8 |
| Cadena de suministro | 0 | 0 | 5 |
| Riesgo y cumplimiento | 0 | 0 | 5 |
| Multiplataforma | 0 | 0 | 9 |
| Contenido y crecimiento | 0 | 1 | 14 |
| Atención al cliente/AB/API | 0 | 0 | 5 |

### Pipeline de middlewares

```
Lite:      Cors → Security(4 tipos) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4 tipos) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31 tipos) → RateLimit(token bucket) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(cifrado de interfaz)
```

### Tamaño del código

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Modelos de Service | 26 | 55 | 111 |
| Controladores de Service | 15 | 24 | 39 |
| Middlewares de Service | 7 | 9+2 | 12+2 |
| Clases de utilidad de Service | 5 | 5 | 15 |
| Modelos de Admin | 15 | 34 | 76 |
| Controladores de Admin | 15 | 27 | 82 |
| Páginas de Flutter | 11 | 11 | 11 |
| HarmonyOS | — | — | 9 páginas |
| Pruebas PHPUnit | 22 | 22 | 54 |

### Stack tecnológico

| Componente | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| snowflake-php | ✅ | ✅ | ✅ |
| hashids | ✅ | ✅ | ✅ |
| jwt-webman | ✅ | ✅ | ✅ |
| encryption | ✅ | ✅ | ✅ |
| encryptable | ✅ | ✅ | ✅ |
| season | — | ✅ | ✅ |
| poster-php | — | ✅ | ✅ |
| webman-scout | — | ✅ | ✅ |
| phpspreadsheet | ✅ | ✅ | ✅ |
| dompdf | ✅ | ✅ | ✅ |
| stripe/stripe-php | — | ✅ | ✅ |
| maxmind/GeoIP2 | — | ✅ | ✅ |
| guzzlehttp/guzzle | — | ✅ | ✅ |

---

## Ruta de actualización

```
Lite (código abierto) ──→ Standard (comercial) ──→ Full (comercial)

Cómo actualizar:
  1. Contactar con erik@erik.xyz para obtener el código de la versión correspondiente
  2. Importar el schema incremental (lite→standard añade ~40 tablas, standard→Full añade ~48 tablas)
  3. Copiar los controladores/modelos/middlewares de la versión correspondiente
  4. composer require con los nuevos paquetes de dependencias
```

---

## Obtención

| Versión | Método |
|------|------|
| **Versión simplificada (Lite)** | Código abierto en GitHub [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) rama `lite` |
| **Versión estándar (Standard)** | Licencia comercial — contactar con **erik@erik.xyz** |
| **Versión completa (Full)** | Licencia comercial — contactar con **erik@erik.xyz** |

La licencia comercial incluye: código fuente completo / soporte de despliegue / actualizaciones prioritarias / consultoría técnica
