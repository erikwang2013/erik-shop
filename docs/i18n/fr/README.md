# Erik Shop — Plateforme de commerce électronique transfrontalier, version complète (Full)

> Ce document est une traduction automatique de la documentation originale chinoise. Original : [中文原版](../../../README.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Version

> Édition simplifiée (open source MIT) : `lite` | Édition standard (commerciale) : `standard` | Édition complète (commerciale) : `full`
>
> Licence commerciale : contactez **erik@erik.xyz** | Comparatif des versions : [VERSIONS.md](VERSIONS.md)

## Langues / Languages

| Langue | Lien |
|------|------|
| Chinois | [README.md](../../../README.md) |
| Anglais | [docs/i18n/en/README.md](../en/README.md) |
| Coréen | [docs/i18n/ko/README.md](../ko/README.md) |
| Russe | [docs/i18n/ru/README.md](../ru/README.md) |
| Allemand | [docs/i18n/de/README.md](../de/README.md) |
| Français | [docs/i18n/fr/README.md](../fr/README.md) |
| Espagnol | [docs/i18n/es/README.md](../es/README.md) |
| Portugais | [docs/i18n/pt/README.md](../pt/README.md) |
| Hindi | [docs/i18n/hi/README.md](../hi/README.md) |
| Arabe | [docs/i18n/ar/README.md](../ar/README.md) |
| Bengali | [docs/i18n/bn/README.md](../bn/README.md) |
| Indonésien | [docs/i18n/id/README.md](../id/README.md) |
| Japonais | [docs/i18n/ja/README.md](../ja/README.md) |

## Présentation du projet

Plateforme de commerce électronique transfrontalier full-stack basée sur l'écosystème webman, couvrant les scénarios B2C/B2B et l'hébergement de vendeurs tiers.

### Architecture technique

| Niveau | Technologie | Répertoire |
|------|------|------|
| API métier | webman + illuminate/database + erikwang2013/* | `service/` |
| Panneau d'administration | webman-admin + LayUI + ECharts | `admin/` |
| Clients | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| Client HarmonyOS | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### Pile technologique

**Serveur :** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**Paquets principaux :** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**Paiement :** Stripe, PayPal (complets) ; Klarna, Adyen (placeholders, `PaymentGateway::make` non implémenté, voir docs/PLAN.md)
**Clients :** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## Collection de diagrammes d'architecture

> Collection complète et vue agrandie : [diagrams.md](diagrams.md)

### Diagramme d'architecture système

![Diagramme d'architecture système](diagrams/01-system-architecture.svg)

### Diagramme de flux de traitement des requêtes

![Diagramme de flux de traitement des requêtes](diagrams/02-request-processing-flow.svg)

### Vue d'ensemble des modules fonctionnels

![Vue d'ensemble des modules fonctionnels](diagrams/03-feature-module-map.svg)

### Diagramme de cycle de vie des requêtes

![Diagramme de cycle de vie des requêtes](diagrams/04-request-lifecycle.svg)

> Plus de détails dans la [collection complète de diagrammes](diagrams.md) (cycle de vie des commandes, architecture de déploiement, architecture de sécurité, règlement multidevise, etc., 8 diagrammes)

### Diagramme d'architecture de sécurité

![Diagramme d'architecture de sécurité](diagrams/07-security-architecture.svg)

### Diagramme de flux de règlement multidevise

![Diagramme de flux de règlement multidevise](diagrams/08-multi-currency-settlement.svg)

### Explication du règlement multidevise

**Tarification multidevise** : les SKU produits sont tarifés par devise selon `currency_code` ; à la commande, la devise de paiement est verrouillée (USD / EUR / GBP / CNY, etc.).

**Service de taux de change** : la table des taux `erik_exchange_rates` prend en charge la maintenance manuelle et la récupération automatique via exchangerate-api, versionnée par date d'effet `effective_at` ; le règlement utilise un instantané du taux au moment du paiement.

**Débit en devise d'origine** : Stripe / PayPal débite en devise de la commande (Klarna/Adyen sont des placeholders, non intégrés) ; le statut du paiement et de la commande est mis à jour après vérification de la signature du Webhook.

**Règlement de répartition** : après paiement réussi, `PlatformSettlements` est automatiquement généré (montant total de la commande + commission de la plateforme + frais de passerelle de paiement, comptabilisés dans la devise de la commande) ; le règlement des vendeurs `MerchantSettlements` (montant de la commande → taux de commission → montant du règlement), le règlement des fournisseurs `SupplierSettlements` et le retrait des commissions d'affiliation `AffiliatePayouts` sont quatre lignes de règlement indépendantes, statut 0 en attente de règlement / 1 réglé.

**Gains et pertes de change** : `CurrencyExchangeGainsLosses` suit l'écart entre la devise de paiement et la devise de règlement, en comparant le taux au moment du paiement et le taux au moment du règlement ; positif = gain de change, négatif = perte de change, pour le rapprochement et l'audit multidevise du commerce transfrontalier.

## Démarrage rapide

### Méthode 1 : Installation Web en un clic (recommandée)

```bash
# 1. Installer les dépendances admin
cd admin && composer install

# 2. Démarrer le panneau d'administration
php start.php start -d

# 3. Ouvrir l'assistant d'installation dans le navigateur
# http://127.0.0.1:8788/app/admin/install/step1
# Renseigner les informations de la base de données → configurer le compte administrateur → terminer

# 4. Installer les dépendances et démarrer l'API
cd ../service && composer install && php start.php start -d
```

> L'assistant d'installation effectue automatiquement : création de la base → import des 117 tables → génération de service/.env et admin/.env (avec clés aléatoires) → création de l'administrateur → rechargement des services

### Méthode 2 : Installation manuelle en ligne de commande

Voir [INSTALL.md](../../INSTALL.md)

### Déploiement Docker

```bash
# Configurer les variables d'environnement
cp .env.example .env  # ou définir DB_PASS / JWT_SECRET etc.
```

```bash
# 1. Démarrer tous les services en une commande
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

Voir [documentation de déploiement](deployment.md)

## Structure du projet

```
shop-php/
  install.sql       # SQL d'installation en un clic (117 tables), importé automatiquement par l'assistant Web
  service/          API métier PHP (webman)        — 39 contrôleurs + 111 modèles + 14 middlewares
  admin/            Panneau d'administration (webman-admin)      — 82 contrôleurs + 76 modèles + tableau de bord ECharts + assistant d'installation Web
  apps/flutter/     Client Flutter              — 11 pages + 5 langues + adaptation PC
  apps/harmonyos/   Client HarmonyOS                  — 9 pages + ArkTS
  docker/           Déploiement Docker                  — Nginx + PHP + MySQL + Redis + ES
  docs/             Documentation de conception
```

## Couverture fonctionnelle

| Dimension | Couverture |
|------|---------|
| **Vente au détail B2C** | Produits multilingues, tarification par devise, SKU, panier, commandes, paiement, remboursements, retours |
| **Vente en gros B2B** | Tarification par paliers (MOQ), certification entreprise (numéro de TVA/licence commerciale), demande de devis |
| **Hébergement multi-vendeurs** | Validation des vendeurs, validation des produits, répartition et règlement |
| **Conformité transfrontalière** | Base de codes HS, règles tarifaires, TVA/IOSS, étiquettes de conformité par pays (FDA/CE/RoHS) |
| **Logistique internationale** | Frais d'expédition par zone, entrepôts à l'étranger (entrepôt d'expédition + entrepôt de retour), facture commerciale/bordereau de colisage, déclaration HS (prévue) |
| **Paiement** | Stripe/PayPal (complets), Klarna/Adyen (placeholders), BNPL (placeholder), vérification 3DS |
| **Marketing** | Coupons (par zone + nouveau/ancien client), bannières (visibilité par région), ventes flash, achats groupés, distribution (liens + commissions + retraits) |
| **Multiplateforme** | Publication de produits Amazon/eBay/Shopee/Lazada/Temu + agrégation des commandes |
| **Chaîne d'approvisionnement** | Évaluation des fournisseurs, achat → contrôle qualité → réception, journal de stock (grand livre immuable), transferts |
| **Gestion des risques et conformité** | Moteur de règles (scoring en parallèle), KYC, demandes de données GDPR/CCPA, consentement Cookie |
| **Protection de la sécurité** | Détection de 31 types d'attaques (XSS/Injection SQL/XXE/SSRF/CRLF/traversée de chemin/téléversement de fichiers/force brute/méthodes HTTP/Host/CORS, etc.) |
| **Haute concurrence** | Limitation de débit par seau à jetons, séparation lecture/écriture DB, optimisation des pools de connexions |
| **Croissance des membres** | Règles de points, avantages des niveaux de membre, cartes cadeaux, alertes de baisse de prix, abonnements récurrents, tests AB |
| **Gestion de contenu** | Pages CMS multilingues, FAQ, base de connaissances, tableaux de tailles, modèles d'e-mail, synchronisation des flux produits |
| **Service client** | Messagerie instantanée WebSocket, base de connaissances (structure de tables créée) |
| **Infrastructure** | IDs distribués Snowflake, obscurcissement d'interface Hashids, authentification JWT, chiffrement AES, identification de région GeoIP |
| **Couverture multi-appareils** | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) + HarmonyOS (ArkTS) + Admin Web |
| **Suivi de plateforme** | Identification de 8 sources de plateforme (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + enregistrement DB |
| **Tests** | 22 tests / 45 assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## Conception principale

- **Clés primaires Snowflake** : les 117 tables utilisent toutes des IDs bigint générés par `erikwang2013/snowflake-php`
- **Interfaces Hashids** : encodage/décodage automatique par middleware, transparent pour les contrôleurs
- **Chiffrement Encryptable** : chiffrement au niveau base de données des champs sensibles tels que email/mobile/adresse
- **Authentification JWT** : HS256 + jetons access/refresh à double renouvellement automatique
- **Version d'API** : routage par en-tête `API-Version`, pas dans l'URL
- **Vérification Poster** : vérification aléatoire homme-machine pour les opérations sensibles (inscription/commande/paiement)

## Documentation

| Document | Description |
|------|------|
| [README-EN.md](../../README-EN.md) | Documentation anglaise |
| [INSTALL.md](../../INSTALL.md) | Guide d'installation (installation Web en un clic + manuelle) |
| [AUDIT-REPORT.md](../../AUDIT-REPORT.md) | Rapport d'audit du système d'installation |
| [Planification du projet](PLAN.md) | Planification de projet par phases produite par l'équipe (feuille de route en 4 phases + risques clés + Quick Wins) |
| [Détails de la recherche d'équipe](PLAN-RESEARCH.md) | Recherche sur 7 domaines : implémenté / écarts / risques / recommandations |
| [Document de conception fonctionnelle](features.md) | Matrice fonctionnelle complète, processus métier, machines d'état |
| [Collection de diagrammes d'architecture](diagrams.md) | Diagrammes d'architecture, de flux, fonctionnels, de cycle de vie, de déploiement, de règlement multidevise (8 diagrammes Mermaid) |
| [Document de conception d'architecture](architecture-full.md) | Diagramme d'architecture système, pipeline de middlewares, architecture de données, architecture de sécurité, architecture de paiement |
| [Document de conception](design.md) | Conception des tables de base de données, spécifications API, solutions de sécurité, internationalisation |
| [Document d'architecture](architecture.md) | Structure des répertoires, chaîne d'héritage des modèles, paquets clés |
| [Documentation des interfaces API](api.md) | 71 points de terminaison API (documentation statique) |
| [Documentation d'interface hg/apidoc](http://localhost:8787/apidoc/) | Génération automatique hg/apidoc (6 groupes : authentification/produits/transactions/logistique et douanes/marketing utilisateur/opérations) |
| [Documentation de déploiement](deployment.md) | Déploiement Docker/manuel, variables d'environnement, commandes d'exploitation |


## Le logiciel libre ne se fait pas sans efforts, merci de votre soutien

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png "WeChat") | ![Alipay](../../alipay.png "Alipay") |

### Virement bancaire international (ZA Bank)

**Informations du bénéficiaire**

- Nom du bénéficiaire : WANG KEXUN
- Numéro de compte : 881015918251

**Banque bénéficiaire**

- Code SWIFT : AABLHKHHXXX
- Nom de la banque : ZA Bank Limited
- Code banque : 387
- Adresse de la banque : Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banque intermédiaire pour virements internationaux (si nécessaire)**

> Il s'agit des informations de la banque intermédiaire (banque de transit) pour les virements internationaux, et non des informations de la banque bénéficiaire. Veuillez demander à votre banque d'émission si ces informations sont nécessaires.

- **Pour les virements en HKD, CNY et USD** (banque intermédiaire Citibank) :
  - Nom de la banque : Citibank N.A. Hong Kong
  - Code SWIFT : CITIHKHXXXX
  - Code banque : 006
  - Nom de la succursale : Hong Kong Branch
  - Numéro de succursale : 391
  - Adresse de la banque : Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Pour les autres devises** (banque intermédiaire BNY Mellon) :
  - Nom de la banque : THE BANK OF NEW YORK MELLON
  - Code SWIFT : IRVTUS3NXXX
  - Adresse de la banque : THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---


## Tests

```bash
make test             # Méthode recommandée
cd service && php vendor/bin/phpunit tests/   # Commande native
# 22 tests, 45 assertions — ALL PASS

# Audit de sécurité des dépendances (1 CVE à faible risque connu : CVE-2025-45769 firebase/php-jwt <7.0.0,
# impossible à mettre à niveau en raison de la contrainte jwt-webman ^6.0, l'utilisation de la signature symétrique HS256 n'est pas affectée)
composer audit
```

## Outils de développement

```bash
make help             # Voir toutes les commandes
make lint             # Vérification de la syntaxe PHP
make check            # Analyse statique phpstan
make fix              # Formatage du code php-cs-fixer
```

CI/CD : `.github/workflows/ci.yml` — Tests matriciels PHP 8.3/8.4

## Licence

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
