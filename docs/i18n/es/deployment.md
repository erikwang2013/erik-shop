# Plataforma de comercio electrónico transfronterizo — Documento de despliegue

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Despliegue con Docker (recomendado)

### 1.1 Requisitos del entorno

- Docker 24.0+ / Docker Compose v2
- Sistema: Linux (recomendado Ubuntu 22.04+)
- Memoria: mínimo 4GB, recomendado 8GB+

### 1.2 Pasos de despliegue

```bash
# 1. Clonar el proyecto
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Configurar variables de entorno
cp .env.example .env
# Editar .env y modificar todas las contraseñas y claves:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, etc.

# 3. Iniciar todos los servicios
docker compose up -d

# 4. Ver los logs
docker compose logs -f service
docker compose logs -f admin

# 5. Acceso
# API: http://localhost/api
# Panel de administración: http://admin.localhost
```

### 1.3 Lista de servicios

| Servicio | Puerto | Descripción |
|------|------|------|
| nginx | 80, 443 | Proxy inverso |
| service | 8787 (interno) | API de negocio PHP |
| admin | 8788 (interno) | Panel de administración |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Lista de verificación para producción

- [ ] Todas las claves en `.env` han sido cambiadas a valores aleatorios
- [ ] `STRIPE_MODE=live` (entorno de producción)
- [ ] `APP_ENV=production`
- [ ] `debug` en `config/app.php` configurado como `false`
- [ ] Certificado SSL configurado (nginx+Let's Encrypt)
- [ ] Base de datos importada con el `install.sql` de la raíz (117 tablas, el asistente de instalación web lo importa automáticamente)
- [ ] Índice ES creado: `php start.php scout:import "app\model\Products"`
- [ ] Copias de seguridad configuradas para los volúmenes de datos de MySQL/Redis/ES
- [ ] CDN configurado: `CDN_ENABLED=true`, `CDN_DOMAIN` definido y CNAME de DNS apuntando al dominio de admin
- [ ] Credenciales del proveedor CDN en `.env` (Cloudflare/CloudFront/Aliyun/Tencent)

## 2. Despliegue manual

### 2.1 Dependencias del entorno

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (opcional, necesario para la búsqueda)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# Editar .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# Escuchando en: http://0.0.0.0:8787
```

### 2.3 Panel de administración Admin

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Escuchando en: http://0.0.0.0:8787 (para otro puerto se necesita Nginx reverse proxy)
```

### 2.4 Nginx reverse proxy

```nginx
# Ver docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
# Recursos estáticos (origin-pull del CDN, caché immutable):
# location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }
```

## 3. Inicialización de la base de datos

```bash
# Crear la base de datos
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar la estructura de tablas
mysql -u root -p erik_shop < install.sql

# Importar datos semilla (opcional)
php -r "
require 'vendor/autoload.php';
// Importar datos semilla de países/monedas/HS Code/zonas logísticas, etc.
"
```

## 4. Referencia de variables de entorno

| Variable | Valor por defecto | Descripción |
|------|--------|------|
| APP_ENV | production | Entorno de la aplicación |
| DB_HOST | 127.0.0.1 | Dirección de la base de datos |
| DB_PORT | 3306 | Puerto de la base de datos |
| DB_NAME | erik_shop | Nombre de la base de datos |
| DB_USER | erik | Usuario de la base de datos |
| DB_PASS | (obligatorio) | Contraseña de la base de datos |
| REDIS_HOST | 127.0.0.1 | Dirección de Redis |
| JWT_SECRET | (obligatorio) | Clave de firma JWT (256bit) |
| HASHIDS_SALT | (obligatorio) | Sal de Hashids |
| ENCRYPTION_KEY | (obligatorio) | Clave de cifrado AES |
| SNOWFLAKE_WORKER_ID | 1 | Worker ID de Snowflake (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Clave de Stripe |
| STRIPE_WEBHOOK_SECRET | - | Verificación de firma del Webhook de Stripe |
| CDN_ENABLED | false | Interruptor global de CDN (reescritura de URL + invalidación) |
| CDN_DEFAULT_PROVIDER | cloudflare | Proveedor CDN por defecto (cloudflare/cloudfront/aliyun/tencent) |
| CDN_DOMAIN | - | Dominio CDN (p. ej. cdn.erik.xyz, CNAME de vuelta al dominio de admin) |
| CF_API_TOKEN | - | Token de API de Cloudflare |
| CF_ZONE_ID | - | ID de zona de Cloudflare |
| AWS_ACCESS_KEY_ID | - | ID de clave de acceso de AWS (CloudFront) |
| AWS_SECRET_ACCESS_KEY | - | Secreto de clave de acceso de AWS (CloudFront) |
| AWS_REGION | us-east-1 | Región de AWS (CloudFront) |
| CLOUDFRONT_DISTRIBUTION_ID | - | ID de distribución de CloudFront |
| ALIYUN_ACCESS_KEY_ID | - | AccessKey ID de Aliyun |
| ALIYUN_ACCESS_KEY_SECRET | - | AccessKey Secret de Aliyun |
| TENCENT_SECRET_ID | - | SecretId de Tencent |
| TENCENT_SECRET_KEY | - | SecretKey de Tencent |

## 5. Comandos de operación

```bash
# Service API
cd service
php start.php status        # Ver estado
php start.php reload        # Reinicio suave
php start.php stop          # Detener

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # Ver estado de los contenedores
docker compose logs -f      # Ver logs
docker compose restart      # Reiniciar todos
docker compose down         # Detener
```
