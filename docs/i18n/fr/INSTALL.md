# Plateforme de commerce électronique transfrontalier — Guide d'installation

> Cross-border E-Commerce Platform Installation Guide
>
> [README chinois](../../../README.md) | [English README](../../README-EN.md) | [Rapport d'audit](../../AUDIT-REPORT.md)

---

## Exigences

| Composant | Version minimale | Version recommandée |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (facultatif/optional) |

### Extensions PHP

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Méthodes d'installation

### Méthode 1 (recommandée) : Assistant d'installation Web en un clic

Accédez à la page d'installation via le navigateur, renseignez les informations de la base de données et le compte administrateur : **création des tables, configuration et création de l'administrateur entièrement automatisées**.

```bash
# 1. Installer les dépendances
cd admin/
composer install

# 2. Démarrer le panneau d'administration
php start.php start

# 3. Accéder via le navigateur (redirection automatique vers la page d'installation au premier accès)
# http://127.0.0.1:8788/app/admin/install/step1
```

L'assistant d'installation effectue **automatiquement** :
- Création de la base de données MySQL (si elle n'existe pas)
- Import des 117 tables de `install.sql` (7 tables `wa_` + 110 tables `erik_`)
- Import des menus du panneau d'administration
- Génération de `plugin/admin/config/database.php` et `thinkorm.php`
- Génération de `service/.env` (avec clés JWT/Hashids/chiffrement générées aléatoirement)
- Création du compte super administrateur
- Envoi du signal SIGUSR1 pour déclencher le rechargement des services

> Après l'installation, il faut également démarrer le service API `service/` (voir étape 5 ci-dessous).

---

### Méthode 2 : Installation manuelle / Manual Installation

<details>
<summary>Adapté au déploiement en ligne de commande ou à un environnement de base de données existant</summary>

### 1. Créer la base de données

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Importer la base de données

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` contient **117 tables** et les données de démarrage par défaut.

### 3. Configurer service/.env

```bash
cd service/
cp .env.example .env
# Modifier .env avec les paramètres réels de base de données/Redis/JWT etc.
```

**Paramètres clés :**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<clé aléatoire de 32 octets>
HASHIDS_SALT=<sel aléatoire>
ENCRYPTION_KEY=<clé aléatoire de 32 octets>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. Configurer admin/

```bash
cd admin/
cp .env.example .env
# Modifier .env avec les mêmes informations de base de données que service
```

### 5. Créer le compte administrateur

```sql
-- Le mot de passe doit être généré via bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Super administrateur', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Méthode 3 : Déploiement Docker / Docker Deployment

```bash
# 1. Configurer les variables d'environnement
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Démarrer tous les services
docker-compose up -d

# 3. Exécuter l'assistant d'installation Web
# http://localhost/app/admin/install/step1
```

Services Docker : Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Démarrer les services / Start Services

```bash
# Installer les dépendances (les deux projets)
cd service/ && composer install
cd admin/ && composer install

# Démarrer le service API
cd service/
php start.php start -d

# Démarrer le panneau d'administration
cd admin/
php start.php start -d
```

| Service | Port par défaut | Vérification |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Panneau d'administration | 8788 | Accès navigateur `http://127.0.0.1:8788/app/admin` |

### Importer les données de démarrage (facultatif) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # Pays/régions
php start.php seed:currencies    # Devises
php start.php seed:hs_codes      # Codes HS Code
php start.php seed:compliance    # Catégories de conformité
```

---

## Structure du répertoire / Directory Structure

```
shop-php/
├── install.sql              # SQL d'installation complet fusionné
├── admin/                   # Panneau d'administration (webman-admin + LayUI)
│   ├── config/database.php  # Configuration de la base de données
│   ├── plugin/admin/        # Plugin webman-admin
│   └── start.php
├── service/                 # Service API (webman RESTful)
│   ├── config/              # Fichiers de configuration
│   ├── database/schema.sql  # SQL des tables métier d'origine (remplacé par install.sql)
│   ├── database/seeders/    # Données de démarrage
│   └── start.php
```

---

## Aperçu du schéma de base de données / Database Schema Overview

| Module | Préfixe de table | Nombre de tables | Description |
|------|--------|--------|------|
| Système du panneau d'administration | `wa_` | 7 | Administrateurs/rôles/permissions/configurations/pièces jointes |
| Utilisateurs et comptes | `erik_users_*` | 7 | Utilisateurs/adresses/social/KYC/favoris/membres |
| Produits et catégories | `erik_product_*` | 16 | Produits/SKU/multilingue/multidevise/avis/conformité/HS |
| Panier et commandes | `erik_order_*` | 9 | Panier/commandes/paiements/remboursements/retours/dédouanement |
| Pays/devises/logistique | `erik_shipping_*` | 11 | Pays/devises/taux de change/logistique/zones/entrepôts/stocks |
| Douanes et fiscalité | `erik_hs_*` | 5 | Codes HS/droits de douane/TVA/restrictions de conformité |
| Paiements et fonds | `erik_payment_*` | 6 | Passerelles de paiement/règlements de plateforme/règlements fournisseurs/gains et pertes de change |
| Marketing | `erik_coupon_*` | 9 | Coupons/ventes flash/achats groupés/distribution |
| Chaîne d'approvisionnement | `erik_supplier_*` | 7 | Fournisseurs/achats/contrôle qualité |
| Risque et conformité | `erik_risk_*` | 6 | Règles de risque/GDPR/Cookie/confidentialité |
| Multiplateforme | `erik_platform_*` | 8 | Multi-boutiques/comptes de plateforme/annonces/vendeurs |
| Contenu et expérience | `erik_*` | 12 | CMS/Feed/tailles/notifications/e-mail/recherche/journaux d'opérations |
| Abonnements/points etc. | `erik_*` | 7 | Abonnements/points/cartes cadeaux/B2B |
| Tests AB/API/paramètres | `erik_*` | 7 | Tests AB/limitation de débit/documentation API/configuration système |

---

## Dépannage / Troubleshooting

### Erreur MySQL "Specified key was too long"

```sql
-- S'assurer d'utiliser utf8mb4 + InnoDB avec innodb_large_prefix activé
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Conflit de port / Port Conflict

Modifier `APP_PORT` dans `admin/.env` ou `service/.env`.

### Échec de connexion Redis

Vérifier que l'extension Redis est installée et que le service Redis est démarré :
```bash
redis-cli ping  # doit renvoyer PONG
```

### Conflit d'ID Snowflake

Si plusieurs serveurs instancient simultanément, s'assurer que `SNOWFLAKE_WORKER_ID` est différent sur chaque serveur (0-31).

---

## Référence rapide des commandes de développement / Development Commands

```bash
# service/ (API)
php start.php start          # Démarrer
php start.php start -d       # Démon
php start.php reload         # Rechargement à chaud
php start.php stop           # Arrêter
php start.php status         # Statut

# admin/ (panneau d'administration)
php start.php start
php start.php start -d
php start.php reload
```
