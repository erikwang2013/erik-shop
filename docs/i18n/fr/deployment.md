# Plateforme de commerce électronique transfrontalier — Document de déploiement

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Déploiement Docker (recommandé)

### 1.1 Exigences d'environnement

- Docker 24.0+ / Docker Compose v2
- Hôte : Linux (recommandé Ubuntu 22.04+)
- Mémoire : minimum 4 Go, recommandé 8 Go+

### 1.2 Étapes de déploiement

```bash
# 1. Cloner le projet
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Configurer les variables d'environnement
cp .env.example .env
# Modifier .env, changer tous les mots de passe et clés :
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET etc.

# 3. Démarrer tous les services
docker compose up -d

# 4. Voir les journaux
docker compose logs -f service
docker compose logs -f admin

# 5. Accès
# API : http://localhost/api
# Panneau d'administration : http://admin.localhost
```

### 1.3 Liste des services

| Service | Port | Description |
|------|------|------|
| nginx | 80, 443 | Reverse proxy |
| service | 8787 (interne) | API métier PHP |
| admin | 8788 (interne) | Panneau d'administration |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Liste de vérification de production

- [ ] Toutes les clés de `.env` ont été remplacées par des valeurs aléatoires
- [ ] `STRIPE_MODE=live` (environnement de production)
- [ ] `APP_ENV=production`
- [ ] `debug` défini sur `false` dans `config/app.php`
- [ ] Certificat SSL configuré (nginx + Let's Encrypt)
- [ ] Base de données importée depuis `install.sql` à la racine (117 tables, importée automatiquement par l'assistant d'installation Web)
- [ ] Index ES créés : `php start.php scout:import "app\model\Products"`
- [ ] Sauvegardes configurées pour les volumes de données MySQL/Redis/ES
- [ ] CDN configuré : `CDN_ENABLED=true`, `CDN_DOMAIN` défini et CNAME DNS pointant vers le domaine admin
- [ ] Identifiants du fournisseur CDN renseignés dans `.env` (Cloudflare/CloudFront/Aliyun/Tencent)

## 2. Déploiement manuel

### 2.1 Dépendances d'environnement

- PHP 8.3+ (ext : pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (facultatif, requis pour la fonction de recherche)
- Composer 2.x

### 2.2 API Service

```bash
cd service
cp ../.env.example .env
# Modifier .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# Écoute : http://0.0.0.0:8787
```

### 2.3 Panneau d'administration Admin

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Écoute : http://0.0.0.0:8787 (un autre port doit être distingué via le reverse proxy Nginx)
```

### 2.4 Reverse proxy Nginx

```nginx
# Voir docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
# Ressources statiques (origin-pull CDN, cache immutable) :
# location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }
```

## 3. Initialisation de la base de données

```bash
# Créer la base de données
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importer la structure des tables
mysql -u root -p erik_shop < install.sql

# Importer les données de démarrage (facultatif)
php -r "
require 'vendor/autoload.php';
// Importer les données de démarrage pays/devises/codes HS/zones logistiques etc.
"
```

## 4. Référence des variables d'environnement

| Variable | Valeur par défaut | Description |
|------|--------|------|
| APP_ENV | production | Environnement d'application |
| DB_HOST | 127.0.0.1 | Adresse de la base de données |
| DB_PORT | 3306 | Port de la base de données |
| DB_NAME | erik_shop | Nom de la base de données |
| DB_USER | erik | Utilisateur de la base de données |
| DB_PASS | (obligatoire) | Mot de passe de la base de données |
| REDIS_HOST | 127.0.0.1 | Adresse Redis |
| JWT_SECRET | (obligatoire) | Clé de signature JWT (256 bits) |
| HASHIDS_SALT | (obligatoire) | Sel Hashids |
| ENCRYPTION_KEY | (obligatoire) | Clé de chiffrement AES |
| SNOWFLAKE_WORKER_ID | 1 | ID worker Snowflake (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Clé Stripe |
| STRIPE_WEBHOOK_SECRET | - | Vérification de signature Webhook Stripe |
| CDN_ENABLED | false | Interrupteur global CDN (réécriture d'URL + invalidation) |
| CDN_DEFAULT_PROVIDER | cloudflare | Fournisseur CDN par défaut (cloudflare/cloudfront/aliyun/tencent) |
| CDN_DOMAIN | - | Domaine CDN (p. ex. cdn.erik.xyz, CNAME vers le domaine admin) |
| CF_API_TOKEN | - | Jeton API Cloudflare |
| CF_ZONE_ID | - | ID de zone Cloudflare |
| AWS_ACCESS_KEY_ID | - | ID de clé d'accès AWS (CloudFront) |
| AWS_SECRET_ACCESS_KEY | - | Clé d'accès secrète AWS (CloudFront) |
| AWS_REGION | us-east-1 | Région AWS (CloudFront) |
| CLOUDFRONT_DISTRIBUTION_ID | - | ID de distribution CloudFront |
| ALIYUN_ACCESS_KEY_ID | - | AccessKey ID Aliyun |
| ALIYUN_ACCESS_KEY_SECRET | - | AccessKey Secret Aliyun |
| TENCENT_SECRET_ID | - | SecretId Tencent |
| TENCENT_SECRET_KEY | - | SecretKey Tencent |

## 5. Commandes d'exploitation

```bash
# API Service
cd service
php start.php status        # Voir le statut
php start.php reload        # Redémarrage en douceur
php start.php stop          # Arrêter

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # Voir l'état des conteneurs
docker compose logs -f      # Voir les journaux
docker compose restart      # Redémarrer tout
docker compose down         # Arrêter
```
