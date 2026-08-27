# Erik Shop — Plateforme de commerce électronique transfrontalier
Plateforme de commerce électronique transfrontalier full-stack basée sur l'écosystème webman, couvrant les scénarios B2C/B2B et l'hébergement de vendeurs tiers.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Aperçu des versions

| | Édition simplifiée (Lite) | Édition standard (Standard) | Édition complète (Full) |
|---|:---:|:---:|:---:|
| **Positionnement** | Développeurs individuels / petite e-commerce | Marchands transfrontaliers en croissance | Plateforme full-stack d'entreprise |
| **Licence** | Open source MIT | Licence commerciale | Licence commerciale |
| **Obtenir** | Téléchargement public GitHub | Contacter erik@erik.xyz | Contacter erik@erik.xyz |
| **Branche** | `lite` | `standard` | `full` |
| **Actuelle** | — | — | ✅ |

---

## 2026-08-27 Disjoncteur et dégradation

- Nouveau disjoncteur Redis `CircuitBreaker` (`service/app/common/CircuitBreaker.php`) : appels externes des passerelles de paiement (Stripe/PayPal/Klarna/Adyen) et de connexion sociale protégés uniformément — 5 échecs consécutifs → ouvert 30s, sonde semi-ouverte avec rétablissement automatique après expiration du TTL
- Liste blanche des rejets métier : carte invalide/jeton invalide ne comptent pas comme échecs du disjoncteur (empêche les requêtes indésirables de faire tomber les services dépendants)
- Panne Redis : dégradation automatique en passage direct ; tant qu'il est ouvert, les API renvoient 503 « Service indisponible »
- Paramètres : `config/concurrency.php` → `circuit_breaker` (fail_threshold=5, open_seconds=30)

---

## Journal des corrections 2026-08-07

| # | Problème | Sévérité | Correction |
|---|------|--------|------|
| 1 | Le chiffrement des réponses API n'était pas branché au middleware | Moyenne | Création de EncryptionMiddleware (piloté par l'en-tête X-Encrypt-Response), enregistré comme niveau 10 du pipeline service |
| 2 | Nom de classe Encryption / nom de fichier EncryptionHelper.php incohérents | Moyenne | Renommé en Encryption.php, correction de l'auto-chargement PSR-4 |
| 3 | JWT_SECRET_KEY vide | Faible | Génération d'une clé de 32 octets, définition de JWT_SECRET et JWT_SECRET_KEY |
| 4 | config/middleware.php en tableau indexé provoquant "Bad middleware config" et le crash de tous les workers | Critique | Remplacé par la structure standard `'' => [...]` (webman exige appName => liste) |
| 5 | La clé enable manquante dans la configuration du plugin security-php était ignorée silencieusement par Config::loadFromDir | Critique | Ajout de `'enable' => true` dans app.php des plugins service/admin |
| 6 | config/bootstrap.php référençait support\bootstrap\Db/Redis inexistants | Critique | Supprimé ; l'initialisation Eloquent passe désormais par support/bootstrap.php qui require vendor/webman/database Db.php |
| 7 | La fonction globale redis() n'existe pas (webman 2.x ne l'a pas), la limitation de débit/gestion des risques échouait silencieusement | Haute | Création de la facade support\Redis (illuminate/redis + phpredis), enregistrement de la fonction d'aide redis() dans app/functions.php |
| 8 | Paramètres de construction RedisManager manquants (3 requis : conteneur app/driver/config) | Haute | Passage d'un conteneur stdClass en placeholder + driver phpredis + configuration de connexion |
| 9 | Les modèles référençaient le trait Erik\Encryptable\Encryptable inexistant (le paquet contient CastsAttributes dans l'espace de noms Maize\Encryptable) | Critique | Création d'une couche de compatibilité trait classique service/Erik/Encryptable/Encryptable.php (réutilise le paquet Encryption::php sous-jacent) |
| 10 | Déclaration en double des fonctions de premier niveau dans le plugin composer Installer.php, erreur fatale | Moyenne | Garde d'idempotence function_exists (les deux vendor service/admin sont corrigés) |
| 11 | getHeader() de HashidsEncode renvoyait une chaîne provoquant une erreur implode | Haute | Cast (array) |
| 12 | docker-compose/.env.example avec clés JWT/chiffrement réelles codées en dur | Critique | Remplacement par des placeholders change_me, l'assistant d'installation génère des clés aléatoires |
| 13 | Création de commande sans transaction, décrément de stock non atomique (survente concurrente) | Critique | Db::transaction + décrément atomique conditionnel |
| 14 | Surémission/surattribution concurrente de coupons | Haute | Transaction + verrou de ligne lockForUpdate + verrou atomique received_qty |
| 15 | Champs de vérification Webhook PayPal toujours vides (verify-webhook-signature échoue forcément) | Haute | Transmission des cinq champs de vérification depuis les en-têtes de requête |
| 16 | Injection SQL dans l'assistant d'installation (concaténation nom de base de données/mot de passe) | Haute | quote + échappement des backticks + var_export pour écrire la configuration |
| 17 | Dégradation silencieuse en cas de clés de chiffrement/hachage manquantes | Haute | Encryption/HashidsHelper lancent une exception si la valeur est vide ou de longueur invalide |
| 18 | Nom de fichier d'export de commandes fixe écrasé en concurrence | Moyenne | Nom de fichier uniqid + nettoyage à l'arrêt + try/catch |
| 19 | Décodage Hashids sans réécriture des paramètres de requête (paramètres de route/GET/POST) | Haute | setParams/setGet/setPost réécrivent |
| 20 | composer.lock ignoré par gitignore (builds non reproductibles) | Moyenne | Suppression de l'ignorance, inclusion dans le contrôle de version |
| 21 | Conteneurs sans healthcheck, sans dépendance de démarrage | Moyenne | healthcheck + condition depends_on sur tous les services |
| 22 | Dockerfile admin non exécutable | Haute | Ajout de COPY + composer install + EXPOSE + CMD |
| 23 | Erreurs de compilation Flutter (conflit intl/génériques de constructeur/parenthèses superflues) + test sur Timer en attente | Haute | intl ^0.20.2, factory statique, pump avance l'horloge |
| 24 | 27 erreurs de compilation ArkTS HarmonyOS empêchant l'empaquetage | Haute | Interfaces explicites, renommage des mots réservés, build à racine unique, import @kit, configuration hvigor |

---

## Comparatif des fonctionnalités

> Note : ◐ = structure de tables créée, logique métier à implémenter (actuellement seuls les tables et modèles existent, pas d'API/code métier ou implémentation partielle)

### Système utilisateur

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Inscription/connexion par e-mail (JWT) | ✅ | ✅ | ✅ |
| Connexion sociale (Google/Apple/Facebook) | — | ✅ | ✅ |
| Gestion des adresses | ✅ | ✅ | ✅ |
| Niveaux de membre + points | — | — | ◐ |
| Cartes cadeaux | — | — | ✅ |
| Vérification d'identité KYC | — | — | ✅ |

### Système de produits

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion des catégories (arborescente) | ✅ | ✅ | ✅ |
| SKU + attributs | ✅ | ✅ | ✅ |
| Images de produits | ✅ | ✅ | ✅ |
| Contenu multilingue | — | ✅ | ✅ |
| Tarification indépendante multidevise | — | ✅ | ✅ |
| Avis produits | ✅ | ✅ | ✅ |
| Étiquettes de conformité (FDA/CE/RoHS) | — | ✅ | ✅ |
| Recherche multilingue ES | — | ✅ | ✅ |
| Synchronisation des flux produits (Google/Meta) | — | — | ✅ |
| Tableaux de tailles | — | — | ✅ |

### Système de transactions

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Panier | ✅ | ✅ | ✅ |
| Gestion des commandes | ✅ | ✅ | ✅ |
| Paiement (Stripe) | ✅ | ✅ | ✅ |
| Paiement (PayPal) | ✅ | ✅ | ✅ |
| Paiement (Klarna/Adyen) | — | placeholder | placeholder |
| BNPL acheter maintenant, payer plus tard | — | placeholder | placeholder |
| Remboursements | ✅ | ✅ | ✅ |
| Gestion des retours | — | ✅ | ✅ |
| Facture commerciale/bordereau de colisage | — | ✅ | ✅ |
| Assurance logistique | — | — | ◐ |

### Logistique transfrontalière

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion des transporteurs internationaux | — | ✅ | ✅ |
| Zones logistiques + tarifs par paliers | — | ✅ | ✅ |
| Entrepôts à l'étranger (expédition + retour) | — | ✅ | ✅ |
| Déclaration HS | — | prévu | prévu |
| Suivi des trajets logistiques | — | ✅ | ✅ |
| Gestion des stocks multi-entrepôts | — | — | ✅ |

### Douanes et fiscalité

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Base de codes HS Code | — | ✅ | ✅ |
| Configuration des règles tarifaires | — | ✅ | ✅ |
| Paramètres VAT/IOSS | — | ✅ | ✅ |
| Restrictions de conformité par pays | — | ✅ | ✅ |
| Conformité d'affichage des prix (TTC/HT) | — | ✅ | ✅ |

### Outils marketing

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Coupons | ✅ | ✅ | ✅ |
| Bannières carrousel | ✅ | ✅ | ✅ |
| Ventes flash | — | ✅ | ✅ |
| Achats groupés | — | ✅ | ✅ |
| Distribution (liens + commissions + retraits) | — | ✅ | ✅ |
| Promotions par région | — | ✅ | ✅ |

### Chaîne d'approvisionnement

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion des fournisseurs | — | — | ✅ |
| Bons de commande d'achat | — | — | ◐ |
| Contrôle qualité (contrôle à l'entrée + sortie) | — | — | ◐ |
| Journal de stock (grand livre immuable) | — | — | ✅ |
| Transferts de stock | — | — | ◐ |

### Extension de plateforme

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion multi-boutiques | — | — | ✅ |
| Hébergement multi-vendeurs (vendeurs tiers) | — | — | ✅ |
| Publication Amazon/eBay/Shopee | — | — | ✅ |
| Agrégation des commandes multiplateformes | — | — | ✅ |
| Vente en gros B2B (tarification par paliers/devis) | — | — | ✅ |

### Gestion des risques et conformité

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Détection d'attaques de base (XSS/SQLi) | ✅ | ✅ | ✅ |
| Détection d'attaques étendue (XXE/SSRF etc.) | — | — | ✅ |
| Vérification homme-machine PosterVerify | — | ✅ | ✅ |
| Moteur de règles de gestion des risques | — | — | ✅ |
| Demandes de données GDPR/CCPA | — | — | ✅ |
| Gestion du consentement Cookie | — | — | ✅ |
| Suivi de la source de plateforme | — | ✅ | ✅ |
| Suivi de la source de plateforme (8 plateformes) | — | ✅ | ✅ |

### Haute concurrence

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| Pool de connexions DB | ✅ | ✅ | ✅ |
| Limitation par seau à jetons | — | — | ✅ |
| Séparation lecture/écriture DB | — | — | ✅ |
| Tâches planifiées Cron (11) | — | — | ✅ |

### Contenu et croissance

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Notifications système | ✅ | ✅ | ✅ |
| Modèles d'e-mail | — | — | ✅ |
| Pages CMS multilingues | — | — | ✅ |
| FAQ + base de connaissances | — | — | ◐ |
| Abonnements récurrents | — | — | ✅ |
| Tests AB | — | — | ◐ |
| Service client en temps réel (WebSocket IM) | — | — | ✅ |

### Clients

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| Internationalisation (traductions en 5 langues) | ✅ | ✅ | ✅ |
| Documentation API (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Tableau de bord Admin ECharts | ✅ | ✅ | ✅ |
| Export Admin Excel/PDF | ✅ | ✅ | ✅ |
| Interface multilingue (5 langues) | ✅ | ✅ | ✅ |

---

## Comparatif de conception

### Base de données

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Tables de données | **23** | **62** | **110** |
| Liées aux utilisateurs | 3 | 5 | 7 |
| Liées aux produits | 6 | 15 | 19 |
| Liées aux transactions | 6 | 9 | 9 |
| Liées à la logistique | 0 | 7 | 9 |
| Liées aux douanes | 0 | 5 | 5 |
| Liées au marketing | 4 | 8 | 8 |
| Chaîne d'approvisionnement | 0 | 0 | 5 |
| Gestion des risques et conformité | 0 | 0 | 5 |
| Multiplateforme | 0 | 0 | 9 |
| Contenu et croissance | 0 | 1 | 14 |
| Service client/AB/API | 0 | 0 | 5 |

### Pipeline de middlewares

```
Lite:      Cors → Security(4 types) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4 types) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31 types) → RateLimit(seau à jetons) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(chiffrement d'interface)
```

### Taille du code

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Modèles Service | 26 | 55 | 111 |
| Contrôleurs Service | 15 | 24 | 39 |
| Middlewares Service | 7 | 9+2 | 12+2 |
| Classes utilitaires Service | 5 | 5 | 15 |
| Modèles Admin | 15 | 34 | 76 |
| Contrôleurs Admin | 15 | 27 | 82 |
| Pages Flutter | 11 | 11 | 11 |
| HarmonyOS | — | — | 9 pages |
| Tests PHPUnit | 22 | 22 | 54 |

### Pile technologique

| Composant | Lite | Standard | Full |
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

## Parcours de mise à niveau

```
Lite (open source) ──→ Standard (commercial) ──→ Full (commercial)

Méthode de mise à niveau :
  1. Contacter erik@erik.xyz pour obtenir le code de la version correspondante
  2. Importer le schéma incrémental (lite→standard ajoute ~40 tables, standard→Full ajoute ~48 tables)
  3. Copier les contrôleurs/modèles/middlewares de la version correspondante
  4. composer require les nouveaux paquets de dépendances
```

---

## Obtenir les versions

| Version | Méthode |
|------|------|
| **Édition simplifiée (Lite)** | Open source GitHub [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) branche `lite` |
| **Édition standard (Standard)** | Licence commerciale — contacter **erik@erik.xyz** |
| **Édition complète (Full)** | Licence commerciale — contacter **erik@erik.xyz** |

La licence commerciale comprend : code source complet / support de déploiement / mises à jour prioritaires / conseil technique
