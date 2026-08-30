> Este documento es una traducción automática de la documentación original en chino. Original: [中文原版](../../../README.md).

# Erik Shop — Plataforma de comercio electrónico transfronterizo (versión completa, Full)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Versión

> Versión simplificada (código abierto MIT): `lite` | Versión estándar (comercial): `standard` | Versión completa (comercial): `full`
>
> Licencia comercial: **erik@erik.xyz** | Comparación de versiones: [VERSIONS.md](VERSIONS.md)

## Idioma / Languages

| Idioma | Enlace |
|------|------|
| Chino | [README.md](README.md) |
| Inglés | [docs/i18n/en/README.md](../en/README.md) |
| Coreano | [docs/i18n/ko/README.md](../ko/README.md) |
| Ruso | [docs/i18n/ru/README.md](../ru/README.md) |
| Alemán | [docs/i18n/de/README.md](../de/README.md) |
| Francés | [docs/i18n/fr/README.md](../fr/README.md) |
| Español | [docs/i18n/es/README.md](../es/README.md) |
| Portugués | [docs/i18n/pt/README.md](../pt/README.md) |
| Hindi | [docs/i18n/hi/README.md](../hi/README.md) |
| Árabe | [docs/i18n/ar/README.md](../ar/README.md) |
| Bengalí | [docs/i18n/bn/README.md](../bn/README.md) |
| Indonesio | [docs/i18n/id/README.md](../id/README.md) |
| Japonés | [docs/i18n/ja/README.md](../ja/README.md) |

## Introducción del proyecto

Plataforma de comercio electrónico transfronterizo full-stack construida sobre la familia de paquetes webman, que cubre escenarios B2C/B2B y la incorporación de vendedores de terceros.

### Arquitectura técnica

| Capa | Tecnología | Directorio |
|------|------|------|
| API de negocio | webman + illuminate/database + erikwang2013/* | `service/` |
| Panel de administración | webman-admin + LayUI + ECharts | `admin/` |
| Clientes | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| Cliente HarmonyOS | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### Stack tecnológico

**Servidor:** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**Paquetes principales:** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**Pagos:** Stripe, PayPal (completos); Klarna, Adyen (placeholder, `PaymentGateway::make` no implementado, ver [PLAN.md](PLAN.md))
**Clientes:** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## Colección de diagramas de arquitectura

> Colección completa y vistas ampliadas: [diagrams.md](diagrams.md)

### Diagrama de arquitectura del sistema

![Diagrama de arquitectura del sistema](diagrams/01-system-architecture.svg)

### Diagrama de flujo de procesamiento de solicitudes

![Diagrama de flujo de procesamiento de solicitudes](diagrams/02-request-processing-flow.svg)

### Mapa general de módulos funcionales

![Mapa general de módulos funcionales](diagrams/03-feature-module-map.svg)
> El mapa cubre 19 módulos funcionales principales (incluidos el centro de informes y las estadísticas de plataforma).

### Diagrama del ciclo de vida de las solicitudes

![Diagrama del ciclo de vida de las solicitudes](diagrams/04-request-lifecycle.svg)

> Más detalles en la [colección completa de diagramas de arquitectura](diagrams.md) (8 diagramas: ciclo de vida de pedidos, arquitectura de despliegue, arquitectura de seguridad, liquidación multimoneda, etc.)

### Diagrama de arquitectura de seguridad

![Diagrama de arquitectura de seguridad](diagrams/07-security-architecture.svg)

**Resiliencia (disyuntor):** un CircuitBreaker respaldado por Redis protege todas las llamadas externas de pago (Stripe/PayPal/Klarna/Adyen) e inicio de sesión social — 5 fallos consecutivos abren el disyuntor durante 30s y, después, una sonda semiabierta lo recupera automáticamente. Los rechazos de negocio (tarjeta rechazada, token no válido) están en lista blanca y nunca cuentan, por lo que las peticiones basura no pueden tumbar las dependencias. Si Redis falla, el disyuntor degrada a pase directo; mientras está abierto, las APIs devuelven 503.

### Diagrama de flujo de liquidación multimoneda

![Diagrama de flujo de liquidación multimoneda](diagrams/08-multi-currency-settlement.svg)

### Explicación de la liquidación multimoneda

**Precio multimoneda:** los SKU de los productos se fijan por moneda mediante `currency_code`; al realizar el pedido, este fija la moneda de cobro (USD / EUR / GBP / CNY, etc.).

**Servicio de tipos de cambio:** la tabla de tipos de cambio `erik_exchange_rates` admite mantenimiento manual (`manual`) y obtención automática vía exchangerate-api, versionada por fecha de entrada en vigor `effective_at`; en la liquidación se toma la instantánea del tipo de cambio en el momento del pago.

**Cobro en moneda original:** Stripe / PayPal cobran en la moneda del pedido (Klarna/Adyen son placeholder, no integrados); tras verificar la firma del Webhook y confirmar la recepción, se actualizan los estados del pago y del pedido.

**Liquidación por reparto:** tras el pago exitoso se generan automáticamente los repartos de plataforma `PlatformSettlements` (total del pedido + comisión de la plataforma + comisión de la pasarela de pago, contabilizados en la moneda del pedido); la liquidación del vendedor `MerchantSettlements` (importe del pedido → tasa de comisión → importe liquidado), la liquidación del proveedor `SupplierSettlements` y el retiro de comisiones de afiliados `AffiliatePayouts` forman cuatro líneas de liquidación independientes, con estado 0 pendiente de liquidar / 1 liquidado.

**Pérdidas y ganancias por tipo de cambio:** `CurrencyExchangeGainsLosses` rastrea la diferencia entre la moneda de cobro y la moneda de liquidación, comparando el tipo de cambio al pagar con el de la liquidación; positivo = ganancia cambiaria, negativo = pérdida cambiaria, lo que sustenta la conciliación y auditoría multimoneda del comercio transfronterizo.

## Inicio rápido

### Método 1: instalación web con un clic (recomendado)

```bash
# 1. Instalar dependencias de admin
cd admin && composer install

# 2. Iniciar el panel de administración
php start.php start -d

# 3. Abrir el asistente de instalación en el navegador
# http://127.0.0.1:8788/app/admin/install/step1
# Rellenar la información de la base de datos → configurar la cuenta de administrador → finalizar

# 4. Instalar dependencias e iniciar la API
cd ../service && composer install && php start.php start -d
```

> El asistente de instalación completa automáticamente: crear la base de datos → importar las 117 tablas → generar service/.env y admin/.env (con claves aleatorias) → crear el administrador → recargar el servicio

### Método 2: instalación manual por línea de comandos

Ver [INSTALL.md](../../INSTALL.md)

### Despliegue con Docker

```bash
# Configurar variables de entorno
cp .env.example .env  # o establecer variables como DB_PASS / JWT_SECRET

# Iniciar todos los servicios con un clic
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

Ver [documento de despliegue](deployment.md)

## Uso

### Panel de administración

Abra `http://127.0.0.1:8788/app/admin` en su navegador para iniciar sesión en el panel de administración (en el primer uso cree la cuenta de administrador mediante el asistente de instalación):

- **Panel**: GMV, volumen de pedidos, crecimiento de usuarios y otras métricas clave de un vistazo
- **Centro de informes**: resumen de ventas, tendencia de 30 días, TOP productos, distribución por método de pago / estado de pedido
- Gestión diaria de productos, pedidos, marketing, cadena de suministro y otros módulos

### Llamadas a la API

```bash
# Obtener la lista de productos
curl http://127.0.0.1:8787/api/products \
  -H "API-Version: 2026-05-20" \
  -H "X-Platform: web"

# Estadísticas de la página de inicio de la plataforma (totales de usuarios/productos/pedidos/GMV y nuevos de hoy)
curl http://127.0.0.1:8787/
```

> La versión de la API se indica mediante el encabezado `API-Version` (no en la URL); los endpoints sensibles requieren `Authorization: Bearer <token>` (JWT).

### Clientes

- **Cliente Flutter**: `apps/flutter/` (iOS / Android / macOS / Windows / Linux)
- **Cliente HarmonyOS**: `apps/harmonyos/` (HarmonyOS NEXT, ArkTS + ArkUI)

## Estructura del proyecto

```
shop-php/
  install.sql       # SQL de instalación con un clic (117 tablas), importado automáticamente por el asistente web
  service/          API de negocio PHP (webman)        — 39 controladores + 111 modelos + 14 middlewares
  admin/            Panel de administración (webman-admin)      — 83 controladores + 76 modelos + panel ECharts + asistente de instalación web
  apps/flutter/     Cliente Flutter              — 11 páginas + 5 idiomas + adaptación PC
  apps/harmonyos/   Cliente HarmonyOS                  — 9 páginas + ArkTS
  docker/           Despliegue Docker                  — Nginx + PHP + MySQL + Redis + ES
  docs/             Documentación de diseño
```

## Cobertura de funciones

| Dimensión | Contenido cubierto |
|------|---------|
| **Retail B2C** | Productos multilingües, precios por moneda, SKU, carrito, pedidos, pagos, reembolsos, devoluciones |
| **Mayoreo B2B** | Precios escalonados (MOQ), verificación empresarial (NIF/registro mercantil), solicitudes de cotización |
| **Incorporación multimercado** | Revisión de vendedores, revisión de productos, reparto y liquidación |
| **Cumplimiento transfronterizo** | Biblioteca de códigos HS Code, reglas arancelarias, VAT/IOSS, etiquetas de cumplimiento por país (FDA/CE/RoHS) |
| **Logística internacional** | Fletes por zonas logísticas, almacenes en el extranjero (almacén de envío + almacén de devolución), factura comercial/lista de embalaje, declaración HS (en planificación) |
| **Pagos** | Stripe/PayPal (completos), Klarna/Adyen (placeholder), BNPL compra ahora paga después (placeholder), verificación 3DS |
| **Marketing** | Cupones (por zona + nuevos/antiguos clientes), banners (visibilidad por región), ventas flash, compras grupales, distribución (enlace + comisión + retiro) |
| **Multiplataforma** | Publicación de productos y agregación de pedidos en Amazon/eBay/Shopee/Lazada/Temu |
| **Cadena de suministro** | Calificación de proveedores, compra → inspección de calidad → entrada en almacén, registro de inventario (libro mayor inmutable), transferencias |
| **Gestión de riesgos y cumplimiento** | Motor de reglas (puntuación paralela), verificación de identidad KYC, solicitudes de datos GDPR/CCPA, consentimiento de cookies |
| **Protección de seguridad** | Detección de 31 tipos de ataques (XSS/SQL injection/XXE/SSRF/CRLF/path traversal/subida de archivos/fuerza bruta/métodos HTTP/Host/CORS, etc.) |
| **Alta concurrencia** | Limitación de velocidad con token bucket, separación lectura/escritura de la BD, optimización de pool de conexiones |
| **Soporte CDN** | Caché de borde origin-pull, abstracción unificada de proveedores (Cloudflare/CloudFront/Aliyun/Tencent), invalidación automática (fail-open), página de gestión CDN (Configuración/Invalidación/Registros) |
| **Análisis de informes** | Centro de informes del panel de administración: resumen de ventas, tendencia de 30 días, TOP productos, distribución por método de pago / estado de pedido |
| **Estadísticas de plataforma** | Estadísticas de la página de inicio de service: totales de usuarios/productos/pedidos/GMV y nuevos de hoy |
| **Crecimiento de miembros** | Reglas de puntos, beneficios por nivel de membresía, tarjetas de regalo, alertas de bajada de precio, compras por suscripción, pruebas A/B |
| **Gestión de contenido** | Páginas CMS multilingües, FAQ, base de conocimientos, tabla de tallas, plantillas de correo, sincronización de feeds de productos |
| **Atención al cliente** | IM en tiempo real por WebSocket, base de conocimientos (estructura de tablas creada) |
| **Infraestructura** | ID distribuido Snowflake, ofuscación de interfaces Hashids, autenticación JWT, cifrado AES, identificación de región GeoIP |
| **Cobertura multi-dispositivo** | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) + HarmonyOS (ArkTS) + Web Admin |
| **Seguimiento de plataforma** | Identificación de 8 plataformas (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + registro en BD |
| **Pruebas** | 22 tests / 45 assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## Diseño principal

- **Clave primaria Snowflake**: las 117 tablas usan ID bigint generados por `erikwang2013/snowflake-php`
- **Interfaces Hashids**: los middlewares codifican/decodifican automáticamente, los controladores no lo perciben
- **Cifrado Encryptable**: cifrado a nivel de base de datos para campos sensibles como email/mobile/address
- **Autenticación JWT**: HS256 + doble token access/refresh con renovación automática
- **Versión de API**: enrutado por el header `API-Version`, no en la URL
- **Verificación Poster**: verificación aleatoria humano-máquina para operaciones sensibles (registro/pedido/pago)

## Documentación

| Documento | Descripción |
|------|------|
| [README-EN.md](../../README-EN.md) | Documentación en inglés |
| [INSTALL.md](../../INSTALL.md) | Guía de instalación (instalación web con un clic + instalación manual) |
| [AUDIT-REPORT.md](../../AUDIT-REPORT.md) | Informe de auditoría del sistema de instalación |
| [PLAN.md](PLAN.md) | Planificación del proyecto por fases elaborada por el equipo (hoja de ruta de 4 fases + riesgos clave + Quick Wins) |
| [PLAN-RESEARCH.md](PLAN-RESEARCH.md) | Investigación de estado actual en 7 áreas: implementado / brechas / riesgos / sugerencias |
| [features.md](features.md) | Matriz de funciones completa, procesos de negocio, máquinas de estado |
| [diagrams.md](diagrams.md) | Diagramas de arquitectura, flujos, funciones, ciclos de vida, despliegue y liquidación multimoneda (8 diagramas Mermaid) |
| [architecture-full.md](architecture-full.md) | Diagrama de arquitectura del sistema, pipeline de middlewares, arquitectura de datos, arquitectura de seguridad, arquitectura de pagos |
| [design.md](design.md) | Diseño de tablas de base de datos, especificaciones de API, esquema de seguridad, internacionalización |
| [architecture.md](architecture.md) | Estructura de directorios, cadena de herencia de modelos, paquetes clave |
| [api.md](api.md) | 71 endpoints de API (documentación estática) |
| [Documentación de interfaz hg/apidoc](http://localhost:8787/apidoc/) | Generada automáticamente por hg/apidoc (6 grupos: autenticación/productos/transacciones/logística y aduanas/usuarios-marketing/operaciones) |
| [deployment.md](deployment.md) | Despliegue Docker/manual, variables de entorno (incl. `CDN_*`), comandos de operación |


## El código abierto no es fácil, ¡apóyalo!

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png "WeChat") | ![Alipay](../../alipay.png "Alipay") |

### Transferencia bancaria global (ZA Bank)

**Información del beneficiario**

- Nombre del beneficiario: WANG KEXUN
- Número de cuenta del beneficiario: 881015918251

**Banco receptor**

- Código SWIFT: AABLHKHHXXX
- Nombre del banco: ZA Bank Limited
- Número de banco: 387
- Dirección del banco: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banco corresponsal para transferencias transfronterizas (si es necesario)**

> Esta es la información del banco corresponsal (banco intermediario) para transferencias transfronterizas, no la del banco receptor. Consulte con su banco emisor si debe proporcionarla.

- **Para remesas en dólares de Hong Kong, yuanes y dólares estadounidenses** (banco corresponsal Citibank):
  - Nombre del banco: Citibank N.A. Hong Kong
  - Código SWIFT: CITIHKHXXXX
  - Número de banco: 006
  - Nombre de la sucursal: Hong Kong Branch
  - Número de sucursal: 391
  - Dirección del banco: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Para remesas en otras monedas** (banco corresponsal BNY Mellon):
  - Nombre del banco: THE BANK OF NEW YORK MELLON
  - Código SWIFT: IRVTUS3NXXX
  - Dirección del banco: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### Donación en criptomonedas (Crypto Donation)

Si este proyecto te resulta útil, escanea el código QR para donar, ¡gracias!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---


## Pruebas

```bash
make test             # método recomendado
cd service && php vendor/bin/phpunit tests/   # comando nativo
# 22 tests, 45 assertions — ALL PASS

# Auditoría de seguridad de dependencias (1 CVE de baja gravedad conocido: CVE-2025-45769 firebase/php-jwt <7.0.0,
# restringido por jwt-webman ^6.0 y no actualizable; el uso de firma simétrica HS256 no se ve afectado)
composer audit
```

## Herramientas de desarrollo

```bash
make help             # ver todos los comandos
make lint             # comprobación de sintaxis PHP
make check            # análisis estático phpstan
make fix              # formateo de código php-cs-fixer
```

CI/CD: `.github/workflows/ci.yml` — pruebas en matriz PHP 8.3/8.4

## Licencia

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
