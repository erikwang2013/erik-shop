# Plataforma de comercio electrónico transfronterizo — Guía de instalación

> Guía de instalación de la plataforma de comercio electrónico transfronterizo
>
> [README en chino](../../../README.md) | [README en inglés](../../README-EN.md) | [Informe de auditoría](../../AUDIT-REPORT.md)

---

## Requisitos del entorno / Requirements

| Componente | Versión mínima | Versión recomendada |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (opcional/optional) |

### Extensiones PHP

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Métodos de instalación / Installation Methods

### Método 1 (recomendado): asistente de instalación web con un clic

Acceda a la página de instalación a través del navegador, rellene la información de la base de datos y la cuenta de administrador; **la creación de tablas, la configuración y la creación del administrador se completan automáticamente**.

```bash
# 1. Instalar dependencias
cd admin/
composer install

# 2. Iniciar el panel de administración
php start.php start

# 3. Acceder desde el navegador (redirige automáticamente a la página de instalación la primera vez)
# http://127.0.0.1:8788/app/admin/install/step1
```

El asistente de instalación **completa automáticamente**:
- Crea la base de datos MySQL (si no existe)
- Importa las 117 tablas de `install.sql` (7 `wa_` + 110 `erik_`)
- Importa los menús del panel de administración
- Genera `plugin/admin/config/database.php` y `thinkorm.php`
- Genera `service/.env` (con claves JWT/Hashids/cifrado generadas aleatoriamente)
- Crea la cuenta de superadministrador
- Envía la señal SIGUSR1 para recargar los servicios

> Tras la instalación, también debe iniciar el servicio API de service/ (ver paso 5 a continuación).

---

### Método 2: instalación manual / Manual Installation

<details>
<summary>Apta para despliegue por línea de comandos o entornos con base de datos existente</summary>

### 1. Crear la base de datos

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Importar la base de datos

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` contiene **117 tablas** y datos semilla por defecto.

### 3. Configurar service/.env

```bash
cd service/
cp .env.example .env
# Editar .env con los parámetros reales de base de datos/Redis/JWT, etc.
```

**Elementos de configuración clave:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<clave aleatoria de 32 bytes>
HASHIDS_SALT=<sal aleatorio>
ENCRYPTION_KEY=<clave aleatoria de 32 bytes>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. Configurar admin/

```bash
cd admin/
cp .env.example .env
# Editar .env con la misma información de base de datos que service
```

### 5. Crear la cuenta de administrador

```sql
-- La contraseña debe generarse con bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Super Administrador', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Método 3: despliegue con Docker / Docker Deployment

```bash
# 1. Configurar variables de entorno
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Iniciar todos los servicios
docker-compose up -d

# 3. Ejecutar el asistente de instalación web
# http://localhost/app/admin/install/step1
```

Servicios Docker: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Iniciar servicios / Start Services

```bash
# Instalar dependencias (se necesitan en ambos proyectos)
cd service/ && composer install
cd admin/ && composer install

# Iniciar el servicio API
cd service/
php start.php start -d

# Iniciar el panel de administración
cd admin/
php start.php start -d
```

| Servicio | Puerto por defecto | Método de verificación |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Panel de administración | 8788 | Acceder desde el navegador a `http://127.0.0.1:8788/app/admin` |

### Importar datos semilla (opcional) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # países/regiones
php start.php seed:currencies    # monedas
php start.php seed:hs_codes      # códigos HS Code
php start.php seed:compliance    # categorías de cumplimiento
```

---

## Estructura de directorios / Directory Structure

```
shop-php/
├── install.sql              # SQL de instalación completo fusionado
├── admin/                   # Panel de administración (webman-admin + LayUI)
│   ├── config/database.php  # Configuración de la base de datos
│   ├── plugin/admin/        # Plugin webman-admin
│   └── start.php
├── service/                 # Servicio API (webman RESTful)
│   ├── config/              # Archivos de configuración
│   ├── database/schema.sql  # SQL de tablas de negocio originales (sustituido por install.sql)
│   ├── database/seeders/    # Datos semilla
│   └── start.php
```

---

## Resumen del esquema de la base de datos / Database Schema Overview

| Módulo | Prefijo de tabla | Nº de tablas | Descripción |
|------|--------|--------|------|
| Sistema del panel | `wa_` | 7 | Administradores/roles/permisos/configuración/archivos adjuntos |
| Usuarios y cuentas | `erik_users_*` | 7 | Usuarios/direcciones/sociales/KYC/favoritos/membresías |
| Productos y categorías | `erik_product_*` | 16 | Productos/SKU/multilingüe/multimoneda/reseñas/cumplimiento/HS |
| Carrito y pedidos | `erik_order_*` | 9 | Carrito/pedidos/pagos/reembolsos/devoluciones/despacho de aduanas |
| País/moneda/logística | `erik_shipping_*` | 11 | País/moneda/tipos de cambio/logística/zonas/almacenes/inventario |
| Aduanas e impuestos | `erik_hs_*` | 5 | Códigos HS/aranceles/VAT/restricciones de cumplimiento |
| Pagos y fondos | `erik_payment_*` | 6 | Pasarelas de pago/reparto de plataforma/liquidación de proveedores/pérdidas cambiarias |
| Marketing | `erik_coupon_*` | 9 | Cupones/ventas flash/compras grupales/distribución |
| Cadena de suministro | `erik_supplier_*` | 7 | Proveedores/compras/inspección de calidad |
| Riesgo y cumplimiento | `erik_risk_*` | 6 | Reglas de riesgo/GDPR/Cookies/privacidad |
| Multiplataforma | `erik_platform_*` | 8 | Multi-tienda/cuentas de plataforma/publicaciones/vendedores |
| Contenido y experiencia | `erik_*` | 12 | CMS/Feed/tallas/notificaciones/correos/búsqueda/registros de operaciones |
| Suscripciones/puntos, etc. | `erik_*` | 7 | Suscripciones/puntos/tarjetas de regalo/B2B |
| Pruebas AB/API/configuración | `erik_*` | 7 | Pruebas AB/limitación de velocidad/documentación API/configuración del sistema |

---

## Preguntas frecuentes / Troubleshooting

### MySQL informa "Specified key was too long"

```sql
-- Asegúrese de usar utf8mb4 + InnoDB y de haber habilitado innodb_large_prefix
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Conflicto de puertos / Port Conflict

Modifique `APP_PORT` en `admin/.env` o `service/.env`.

### Fallo de conexión a Redis

Compruebe que la extensión Redis está instalada y que el servicio Redis está en marcha:
```bash
redis-cli ping  # debe devolver PONG
```

### Conflicto de IDs Snowflake

Si varios servidores se instancian simultáneamente, asegúrese de que `SNOWFLAKE_WORKER_ID` es distinto en cada servidor (0-31).

---

## Referencia rápida de comandos de desarrollo / Development Commands

```bash
# service/ (API)
php start.php start          # iniciar
php start.php start -d       # daemon
php start.php reload         # recarga en caliente
php start.php stop           # detener
php start.php status         # estado

# admin/ (panel de administración)
php start.php start
php start.php start -d
php start.php reload
```
