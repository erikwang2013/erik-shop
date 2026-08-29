# Cross-Border-E-Commerce-Plattform — Bereitstellungsdokument

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../deployment.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker-Bereitstellung (empfohlen)

### 1.1 Umgebungsanforderungen

- Docker 24.0+ / Docker Compose v2
- Host: Linux (empfohlen Ubuntu 22.04+)
- Speicher: mindestens 4GB, empfohlen 8GB+

### 1.2 Bereitstellungsschritte

```bash
# 1. Projekt klonen
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Umgebungsvariablen konfigurieren
cp .env.example .env
# .env bearbeiten und alle Passwörter und Schlüssel ändern:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET usw.

# 3. Alle Dienste starten
docker compose up -d

# 4. Logs ansehen
docker compose logs -f service
docker compose logs -f admin

# 5. Zugriff
# API: http://localhost/api
# Verwaltungskonsole: http://admin.localhost
```

### 1.3 Dienstübersicht

| Dienst | Port | Beschreibung |
|------|------|------|
| nginx | 80, 443 | Reverse-Proxy |
| service | 8787 (intern) | PHP-Geschäfts-API |
| admin | 8788 (intern) | Verwaltungskonsole |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Checkliste für die Produktionsumgebung

- [ ] Alle Schlüssel in `.env` wurden auf Zufallswerte geändert
- [ ] `STRIPE_MODE=live` (Produktionsumgebung)
- [ ] `APP_ENV=production`
- [ ] `debug` in `config/app.php` auf `false` gesetzt
- [ ] SSL-Zertifikat konfiguriert (nginx + Let's Encrypt)
- [ ] Datenbank mit dem `install.sql` aus dem Wurzelverzeichnis importiert (117 Tabellen, automatischer Import durch den Web-Installationsassistenten)
- [ ] ES-Index erstellt: `php start.php scout:import "app\model\Products"`
- [ ] Backups für die MySQL-/Redis-/ES-Datenvolumes konfiguriert
- [ ] CDN konfiguriert: `CDN_ENABLED=true`, `CDN_DOMAIN` gesetzt und DNS-CNAME auf die Admin-Domain gerichtet
- [ ] CDN-Anbieter-Zugangsdaten in `.env` eingetragen (Cloudflare/CloudFront/Aliyun/Tencent)

## 2. Manuelle Bereitstellung

### 2.1 Umgebungsabhängigkeiten

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (optional, für die Suchfunktion erforderlich)
- Composer 2.x

### 2.2 Service-API

```bash
cd service
cp ../.env.example .env
# .env bearbeiten
composer install --no-dev --optimize-autoloader
php start.php start -d
# Lauscht auf: http://0.0.0.0:8787
```

### 2.3 Admin-Verwaltungskonsole

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Lauscht auf: http://0.0.0.0:8787 (ein weiterer Port muss per Nginx-Reverse-Proxy unterschieden werden)
```

### 2.4 Nginx-Reverse-Proxy

```nginx
# Siehe docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
# Statische Ressourcen (CDN-Origin-Pull, immutable Caching):
# location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }
```

## 3. Datenbank-Initialisierung

```bash
# Datenbank anlegen
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Tabellenstruktur importieren
mysql -u root -p erik_shop < install.sql

# Seed-Daten importieren (optional)
php -r "
require 'vendor/autoload.php';
// Importiert Seed-Daten für Länder/Währungen/HS-Codes/Logistikzonen usw.
"
```

## 4. Referenz der Umgebungsvariablen

| Variable | Standardwert | Beschreibung |
|------|--------|------|
| APP_ENV | production | Anwendungsumgebung |
| DB_HOST | 127.0.0.1 | Datenbankadresse |
| DB_PORT | 3306 | Datenbankport |
| DB_NAME | erik_shop | Datenbankname |
| DB_USER | erik | Datenbankbenutzer |
| DB_PASS | (erforderlich) | Datenbankpasswort |
| REDIS_HOST | 127.0.0.1 | Redis-Adresse |
| JWT_SECRET | (erforderlich) | JWT-Signaturschlüssel (256bit) |
| HASHIDS_SALT | (erforderlich) | Hashids-Salt |
| ENCRYPTION_KEY | (erforderlich) | AES-Verschlüsselungsschlüssel |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake-Worker-ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripe-Schlüssel |
| STRIPE_WEBHOOK_SECRET | - | Stripe-Webhook-Signaturprüfung |
| CDN_ENABLED | false | CDN-Gesamtschalter (URL-Rewriting + Invalidierung) |
| CDN_DEFAULT_PROVIDER | cloudflare | Standard-CDN-Anbieter (cloudflare/cloudfront/aliyun/tencent) |
| CDN_DOMAIN | - | CDN-Domain (z. B. cdn.erik.xyz, CNAME-Rückführung auf die Admin-Domain) |
| CF_API_TOKEN | - | Cloudflare API-Token |
| CF_ZONE_ID | - | Cloudflare Zone-ID |
| AWS_ACCESS_KEY_ID | - | AWS-Zugriffsschlüssel-ID (CloudFront) |
| AWS_SECRET_ACCESS_KEY | - | AWS-Zugriffsschlüssel-Geheimnis (CloudFront) |
| AWS_REGION | us-east-1 | AWS-Region (CloudFront) |
| CLOUDFRONT_DISTRIBUTION_ID | - | CloudFront-Distribution-ID |
| ALIYUN_ACCESS_KEY_ID | - | Aliyun AccessKey-ID |
| ALIYUN_ACCESS_KEY_SECRET | - | Aliyun AccessKey-Geheimnis |
| TENCENT_SECRET_ID | - | Tencent SecretId |
| TENCENT_SECRET_KEY | - | Tencent SecretKey |

## 5. Betriebsbefehle

```bash
# Service-API
cd service
php start.php status        # Status anzeigen
php start.php reload        # Sanfter Neustart
php start.php stop          # Stoppen

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # Containerstatus anzeigen
docker compose logs -f      # Logs ansehen
docker compose restart      # Alle neu starten
docker compose down         # Stoppen
```
