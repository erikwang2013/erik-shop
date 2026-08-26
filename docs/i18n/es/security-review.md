# Informe de revisión de integración de Security Plugin

**Fecha**: 2026-08-04
**Alcance**: integración de erikwang2013/security-php v1.1.6
**Revisor**: Claude Code (automatizado)

---

## 1. Resultados de las pruebas

| Verificación | Resultado |
|---|---|
| Comprobación de sintaxis PHP (47 archivos) | Todos aprobados |
| PHPUnit (22 tests, 45 assertions) | Todos aprobados |
| Prueba de carga de seguridad de SecurityGuard | Bloquea correctamente XSS + SQLi |
| Prueba de solicitudes seguras de SecurityGuard | Sin falsos positivos |
| Análisis estático phpstan | No instalado (no bloqueante) |

## 2. Problemas corregidos

### 2.1 Los datos de subida de archivos no se pasaban a SecurityGuard (Crítico)

**Archivo**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

El middleware solo pasaba `$request->all()` a `SecurityGuard::guard()`, pero ese método no incluye los datos de subida de archivos. `UploadDetector` necesita los datos de archivo en formato `['tmp_name' => ..., 'name' => ...]`.

**Corrección**: se añadió un bucle que fusiona `$request->file()` en el array de datos antes de pasarlo a `SecurityGuard::guard()`.

### 2.2 La configuración de encryptable de Admin carecía de valor por defecto (Medio)

**Archivo**: `admin/config/plugin/erikwang2013/encryptable/app.php`

La configuración de admin usa `env('ENCRYPTION_KEY')` sin valor de respaldo, devolviendo `null` cuando falta la variable de entorno. Service usa `getenv('ENCRYPTION_KEY') ?: ''` y vuelve correctamente a una cadena vacía.

**Corrección**: la configuración de admin ahora usa el operador `?: ''`, coherente con el comportamiento de service.

### 2.3 Variables de entorno de Docker Compose incompletas (Medio)

**Archivo**: `docker-compose.yml`

- Al contenedor de service le faltaban `ENCRYPTION_CIPHER` y `ENCRYPTION_PREVIOUS_KEYS`
- Al contenedor de admin le faltaban `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID`

**Corrección**: se añadieron todas las variables de entorno faltantes, usando valores por defecto coherentes con `.env.example`.

### 2.4 Detección duplicada del middleware WAF (Crítico, corregido en la primera ronda)

El `SecurityMiddleware` personalizado contenía ~200 líneas de regex inline, completamente duplicadas con los 31 detectores del paquete `security-php`. Cada solicitud se escaneaba dos veces, desperdiciando CPU y pudiendo bloquear dos veces.

**Corrección**: el middleware se reescribió para usar la API `SecurityGuard::guard()`, reduciéndolo de 341 líneas a ~110 (service) y de 136 a ~85 (admin). Se mantuvieron la protección contra fuerza bruta y las cabeceras de seguridad de respuesta.

### 2.5 ENCRYPTION_KEY ausente (Crítico, corregido en la primera ronda)

El archivo `.env.example` usaba un placeholder para `ENCRYPTION_KEY` y le faltaban `ENCRYPTION_CIPHER` y `ENCRYPTION_PREVIOUS_KEYS`. No existía ningún archivo `.env` real.

**Corrección**: se generó una clave de 32 bytes en base64, se añadieron `ENCRYPTION_CIPHER=AES-256-CBC` y `ENCRYPTION_PREVIOUS_KEYS`, y se creó el archivo `.env`.

## 3. Integridad de la configuración del ecosistema

### 3.1 Paquetes (coherentes en ambos proyectos)

| Package | Versión | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | Instalado | Instalado |
| erikwang2013/encryptable | - | Instalado | Instalado |
| erikwang2013/encryption | - | Instalado | Instalado |
| erikwang2013/jwt-webman | - | Instalado | Instalado |
| erikwang2013/hashids | - | Instalado | Instalado |
| erikwang2013/snowflake-php | - | Instalado | Instalado |
| erikwang2013/poster-php | - | Instalado | Instalado |
| erikwang2013/season | - | Instalado | Instalado |
| erikwang2013/webman-scout | - | Instalado | Instalado |

### 3.2 Configuración WAF

| Elemento | Service | Admin | Estado |
|---|---|---|---|
| Archivo de configuración | `config/plugin/erikwang2013/security-php/app.php` | Igual | Publicado |
| Detectores habilitados | 31/31 | 31/31 | Correcto |
| Lista negra de IP | habilitada (5 att/60s -> 900s ban) | Igual | Correcto |
| Detectores en modo bloqueo | 28 | 28 | Correcto |
| Detectores solo registro | 3 (header_injection, ssti, nosql_injection) | 3 | Correcto |
| Almacenamiento | file | file | Correcto |
| Registro | habilitado (file, rotación 10MB) | Igual | Correcto |
| Middleware registrado | `config/middleware.php` | `config/middleware.php` | Correcto |

### 3.3 Configuración de cifrado

| Elemento | Service | Admin | Estado |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | Igual | Configurado |
| ENCRYPTION_CIPHER | `AES-256-CBC` | Igual | Configurado |
| ENCRYPTION_PREVIOUS_KEYS | (vacío) | (vacío) | Configurado |
| Config de encryptable | `config/plugin/erikwang2013/encryptable/app.php` | Igual (unificado) | Correcto |
| Config de encryption | `config/encryption.php` | - | Correcto |
| Archivo .env | Existe | Existe | Creado |
| .env.example | Actualizado | Actualizado | Correcto |
| docker-compose | Actualizado | Actualizado | Correcto |

### 3.4 Modelos con el trait Encryptable

31 modelos usan el trait `Encryptable`, con los campos sensibles correctamente declarados como `$encryptable`:

| Categoría | Modelos | Campos sensibles |
|---|---|---|
| PII de usuario | Users | email, mobile |
| PII de usuario | UserAddresses | name, phone, detail |
| PII de usuario | UserKyc | real_name, id_number |
| PII de usuario | UserSocialAccounts | access_token, refresh_token |
| Privacidad | PrivacyRequests | email |
| Finanzas | GiftCards | receiver_email |
| Finanzas | AffiliatePayouts | account |
| Finanzas | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Plataforma | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Plataforma | PlatformAccounts | account_name, api_key, api_secret |
| Plataforma | PlatformListings | platform_account_id |
| Logística | LogisticsCompanies | name, api_key |
| Proveedor | Suppliers | name, email, phone |
| Proveedor | B2bVerifications | company_name |
| Vendedor | Merchants | store_name, email, phone |
| Otros | EmailLogs | to_email |
| Otros | 15 modelos más | campos name |

## 4. Segunda ronda de correcciones (cifrado de API + clave JWT)

### 4.1 Middleware de cifrado de respuestas API (Medio, corregido)

**Archivo**: `service/app/middleware/EncryptionMiddleware.php` (nuevo)

El paquete `erikwang2013/encryption` estaba instalado y existía la clase de utilidad `app/common/Encryption`, pero no estaba conectada al pipeline de middlewares. A los datos sensibles de las interfaces les faltaba cifrado/descifrado en la capa de transporte.

**Corrección**:
- Se creó `EncryptionMiddleware` con cifrado/descifrado impulsado por headers HTTP:
  - `X-Encrypted: 1` — descifrado de solicitudes: descifra el body base64 a JSON antes de pasarlo al controlador
  - `X-Encrypt-Response: 1` — cifrado de respuestas: cifra el campo `data` de la respuesta a texto cifrado base64
  - `X-Encrypt-Fields: field1,field2` — cifra solo los campos especificados de la respuesta
- Registrado como el último nivel de la pila de middlewares (después de HashidsEncode)
- Las comprobaciones de salud (`/api/health`, `/api/ping`) y los endpoints de documentación (`/apidoc`) omiten el cifrado/descifrado

### 4.2 Desajuste de nombre de clase/archivo (Medio, corregido)

**Archivo**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

La clase `app\common\Encryption` estaba declarada en el archivo `EncryptionHelper.php`, lo que no cumple la norma PSR-4 y hace fallar el autoloading de Composer. En entornos IDE y CLI, el autoloader podría no encontrar la clase.

**Corrección**: el archivo se renombró a `Encryption.php` para que coincida con el nombre de la clase.

### 4.3 JWT_SECRET_KEY vacío (Bajo, corregido)

**Archivo**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` era una cadena vacía; aunque el middleware JWT tiene una cadena de respaldo `JWT_SECRET → JWT_SECRET_KEY` (prioriza `JWT_SECRET`), el valor placeholder no es seguro.

**Corrección**: se generó una clave de 32 bytes en base64, configurando tanto `JWT_SECRET` como `JWT_SECRET_KEY`. Se actualizaron `.env.example`, `.env` y `docker-compose.yml`.

## 5. Problemas a observar (puntos de optimización potenciales)

### 5.1 Dependencia de SecurityGuard de los headers de webman/Workerman (Riesgo bajo)

**Impacto**: los detectores de CSRF Origin, Host Header, DNS Rebinding, Request Smuggling y CORS dependen de los datos de cabeceras HTTP en `$_SERVER`.

En el entorno no CGI de Workerman, `$_SERVER` puede no estar completamente poblado con las cabeceras HTTP. SecurityGuard ya tiene lógica de respaldo (p. ej., si el valor de la cabecera está vacío, omite la detección), por lo que **no se producen falsos positivos**, pero **algunos ataques vía cabeceras podrían no detectarse**. El impacto es bajo porque el proxy inverso Nginx normalmente también filtra las cabeceras maliciosas.

**Sugerencia**: si se necesita una detección de cabeceras más completa, se pueden pasar los valores de cabecera explícitamente en el parámetro `$meta` de SecurityGuard. Actualmente no requiere cambios.

### 5.2 Impacto del detector CSRF Origin en Admin (Sin riesgo)

El detector `csrf_origin` de Admin está en modo `block` con `allowed_origins` vacío. Sin embargo, como el detector solo se activa cuando el header Origin existe y no coincide con Host, el panel de administración normalmente no envía header Origin (acceso mismo origen), por lo que **no hay bloqueos erróneos**.

### 5.3 Los 31 detectores todos habilitados, coste por solicitud (Nota de rendimiento)

Todas las solicitudes ejecutan los 31 detectores (incluidos JWT, WebSocket, GraphQL, CSV, prototype pollution, etc.). Cada detector ejecuta coincidencias regex sobre todos los campos de la solicitud. Para el escenario de uso de este proyecto, el coste es aceptable (webman es un proceso en memoria residente, sin coste de arranque en frío CGI).

### 5.4 Persistencia de la lista negra de IP (Nota operativa)

El backend de almacenamiento está en modo `file`, con ruta por defecto `sys_get_temp_dir() . '/security_storage.json'`. En contenedores Docker, el directorio temporal puede perderse tras un reinicio. Si se necesita compartir la lista negra en despliegues multicontenedor, se puede cambiar al modo `redis`.

## 6. Resumen de archivos modificados

```
admin/.env.example                                (ENCRYPTION_KEY añadido)
admin/.env                                        (creado a partir de .env.example)
admin/CLAUDE.md                                   (actualización de pila de middlewares + tech stack)
admin/composer.json                               (dependencia security-php)
admin/config/plugin/erikwang2013/encryptable/app.php  (valores por defecto unificados)
admin/config/plugin/erikwang2013/security-php/app.php  (nuevo, 31 detectores)
admin/app/middleware/SecurityMiddleware.php       (reescrito para usar SecurityGuard)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + clave JWT actualizados)
service/.env                                      (creado a partir de .env.example, clave JWT sincronizada)
service/CLAUDE.md                                 (actualización de pila de middlewares + Encryption + tech stack)
service/composer.json                             (dependencia security-php)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (nuevo, 31 detectores)
service/app/common/Encryption.php                 (renombrado desde EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (nuevo, cifrado/descifrado de respuestas API)
service/app/middleware/SecurityMiddleware.php     (reescrito para usar SecurityGuard + subida de archivos)
docker-compose.yml                                (variables de entorno de encryption/jwt completadas)
docs/security-review.md                           (este informe)
```

## 7. Conclusión

**Estado**: Aprobado

- La detección WAF bloquea correctamente ataques como XSS e inyección SQL (31 detectores, API SecurityGuard::guard)
- La configuración de cifrado de campos sensibles está completa (31 modelos, 6 categorías de datos sensibles, trait Encryptable)
- El cifrado/descifrado de transporte de API está conectado al middleware (EncryptionMiddleware, AES-256-CBC, activado por header)
- La clave JWT está configurada (tanto JWT_SECRET como JWT_SECRET_KEY)
- La detección de subida de archivos está corregida (los datos $_FILES se fusionan y se pasan a SecurityGuard)
- Sin regresiones funcionales (22/22 pruebas aprobadas)
- Sin detección duplicada de middlewares
- Variables de entorno del despliegue Docker completas
