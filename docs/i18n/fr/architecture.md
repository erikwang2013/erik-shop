# Plateforme de commerce électronique transfrontalier — Aperçu de l'architecture

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Pile technologique

| Niveau | Technologie | Version |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| Clients | Flutter (5 plateformes) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| Base de données | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| Paiement | Stripe / PayPal / Klarna / Adyen | — |

## 2. Structure des répertoires

```
shop-php/
  service/           API métier (251 fichiers PHP)
    config/            35 configurations (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39 contrôleurs (38 v1 + BaseApiController : Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 modèles (BaseModel + 110 modèles métier)
    app/middleware/     14 middlewares (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 classes utilitaires (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (remplacé par install.sql à la racine) + seeders
    tests/              4 classes de test (22 tests, 45 assertions)
  admin/             Panneau d'administration (239 fichiers PHP)
    plugin/admin/app/controller/shop/ 82 contrôleurs
    plugin/admin/app/model/shop/      76 modèles
    plugin/admin/app/view/shop/       Tableau de bord ECharts
    app/middleware/    5 middlewares (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              Clients
    flutter/lib/      25 Dart (11 pages + couche de base + routage)
    harmonyos/        14 ArkTS (9 pages + client API + état global)
  docs/               5 documents de conception
  .claude/skills/     38 Skills de normes de développement
```

## 3. Pipeline de middlewares

```
Service: Cors → Security(détection de 31 types d'attaques) → RateLimit(limitation par seau à jetons) → Platform(identification de 8 plateformes)
        → GeoIp(région) → Locale(langue) → HashidsDecode → VersionRoute
        → (PosterVerify vérification homme-machine) → (JwtAuth Token) → HashidsEncode → Encryption(chiffrement d'interface)

Admin:  Security → Platform → HashidsDecode → AccessControl(RBAC intégré) → HashidsEncode
```

## 4. Sécurité

- **Détection de 31 types d'attaques** : XSS/Injection SQL/Injection de commandes/CRLF/traversée de chemin/Body/ContentType/téléversement de fichiers/force brute/XXE/SSRF/désérialisation/LDAP/en-têtes d'e-mail/SSTI/NoSQL/redirection ouverte/attaques JWT/Host/contrebande de requêtes/GraphQL/XPATH/Log4Shell/SSI/formules CSV/fuite de données/pollution de prototypes/WebSocket/CORS/rebinding DNS/méthodes HTTP/Origin CSRF
- **Chiffrement à trois couches** : couche d'interface (AES-256-CBC) + couche de base de données (trait Encryptable) + obscurcissement d'ID (Hashids)
- **Suivi de plateforme** : 8 plateformes (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + en-tête X-Platform + enregistrement dans 6 tables

## 5. Haute concurrence

- **Limitation de débit** : seau à jetons à fenêtre glissante (ZSET Redis), règles pour 6 endpoints
- **Disjoncteur/dégradation** : disjoncteur Redis — appels d'API externes (passerelles de paiement/connexion sociale), 5 échecs consécutifs → ouvert 30s, sonde semi-ouverte avec rétablissement automatique ; les exceptions métier ne comptent pas comme échecs ; en cas de panne Redis, dégradation automatique en passage direct (503)
- **DB** : séparation lecture/écriture (2 réplicas de lecture + sticky) + pools de connexions (50/10)
- **Opérations lentes** : traitées par des processus Cron indépendants (synchronisation Feed/calcul de recommandations/rapprochement de paiements/règlement de répartition etc.)

## 6. Tests

22 tests / 45 assertions — ALL PASS
- SecurityTest (12) : XSS+SQLi+XXE+SSRF+Path+fuite de données
- JwtTest (4) : validation encode/decode
- ApiResponseTest (3) : succès/échec/pagination

## 7. Déploiement

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# Manuel
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **Multilingue (i18n)** : fichiers de traduction de 5 langues + LocaleMiddleware + Flutter AppLocalizations
- **Documentation API** : génération automatique hg/apidoc (6 groupes, pilotée par les annotations des contrôleurs)
- **Suivi de plateforme** : en-tête X-Platform de 8 plateformes + enregistrement DB

Voir : [Document de déploiement](deployment.md) | [Document complet d'architecture](architecture-full.md) | [Document de conception fonctionnelle](features.md)
