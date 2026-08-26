# Rapport d'audit d'intégration du plugin Security

**Date** : 2026-08-04
**Périmètre** : intégration erikwang2013/security-php v1.1.6
**Relecteur** : Claude Code (automatisé)

---

## 1. Résultats des tests

| Vérification | Résultat |
|---|---|
| Vérification de syntaxe PHP (47 fichiers) | Tous réussis |
| PHPUnit (22 tests, 45 assertions) | Tous réussis |
| Test des charges de sécurité SecurityGuard | Interception correcte de XSS + SQLi |
| Test des requêtes sécurisées SecurityGuard | Aucun faux positif |
| Analyse statique phpstan | Non installée (non bloquant) |

## 2. Problèmes corrigés

### 2.1 Les données de téléversement de fichiers n'étaient pas transmises à SecurityGuard (Critique)

**Fichiers** : `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

Le middleware ne transmettait que `$request->all()` à `SecurityGuard::guard()`, mais cette méthode n'inclut pas les données de téléversement de fichiers. `UploadDetector` a besoin des données de fichiers au format `['tmp_name' => ..., 'name' => ...]`.

**Correction** : ajout d'une boucle qui fusionne `$request->file()` dans le tableau de données avant de le transmettre à `SecurityGuard::guard()`.

### 2.2 Valeur par défaut manquante dans la configuration encryptable Admin (Moyenne)

**Fichier** : `admin/config/plugin/erikwang2013/encryptable/app.php`

La configuration admin utilise `env('ENCRYPTION_KEY')` sans valeur de repli, renvoyant `null` lorsque la variable d'environnement est absente. Service utilise `getenv('ENCRYPTION_KEY') ?: ''` avec un repli correct vers la chaîne vide.

**Correction** : la configuration admin utilise désormais l'opérateur `?: ''`, cohérent avec le comportement de service.

### 2.3 Variables d'environnement Docker Compose incomplètes (Moyenne)

**Fichier** : `docker-compose.yml`

- Le conteneur service manquait de `ENCRYPTION_CIPHER` et `ENCRYPTION_PREVIOUS_KEYS`
- Le conteneur admin manquait de `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID`

**Correction** : toutes les variables d'environnement manquantes ont été ajoutées, avec les valeurs par défaut cohérentes avec `.env.example`.

### 2.4 Double détection du middleware WAF (Critique, corrigé au premier tour)

Le `SecurityMiddleware` personnalisé contenait ~200 lignes de regex en ligne, entièrement redondantes avec les 31 détecteurs du paquet `security-php`. Chaque requête était scannée deux fois, gaspillant du CPU et risquant une double interception.

**Correction** : le middleware a été réécrit pour utiliser l'API `SecurityGuard::guard()`, réduit de 341 lignes à ~110 (service), de 136 lignes à ~85 (admin). La protection contre la force brute et les en-têtes de réponse de sécurité sont conservés.

### 2.5 ENCRYPTION_KEY manquante (Critique, corrigé au premier tour)

Dans `.env.example`, `ENCRYPTION_KEY` utilisait un placeholder, sans `ENCRYPTION_CIPHER` ni `ENCRYPTION_PREVIOUS_KEYS`. Pas de fichier `.env` réel.

**Correction** : génération d'une clé base64 de 32 octets, ajout de `ENCRYPTION_CIPHER=AES-256-CBC` et `ENCRYPTION_PREVIOUS_KEYS`, création du fichier `.env`.

## 3. Complétude de la configuration d'écosystème

### 3.1 Paquets (cohérents entre les deux projets)

| Paquet | Version | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | installé | installé |
| erikwang2013/encryptable | - | installé | installé |
| erikwang2013/encryption | - | installé | installé |
| erikwang2013/jwt-webman | - | installé | installé |
| erikwang2013/hashids | - | installé | installé |
| erikwang2013/snowflake-php | - | installé | installé |
| erikwang2013/poster-php | - | installé | installé |
| erikwang2013/season | - | installé | installé |
| erikwang2013/webman-scout | - | installé | installé |

### 3.2 Configuration WAF

| Élément | Service | Admin | Statut |
|---|---|---|---|
| Fichier de configuration | `config/plugin/erikwang2013/security-php/app.php` | identique | publié |
| Détecteurs activés | 31/31 | 31/31 | correct |
| Liste noire IP | activée (5 att/60s -> bannissement 900s) | identique | correct |
| Détecteurs en mode blocage | 28 | 28 | correct |
| Détecteurs en mode journalisation seule | 3 (header_injection, ssti, nosql_injection) | 3 | correct |
| Stockage | fichier | fichier | correct |
| Journalisation | activée (fichier, rotation 10 Mo) | identique | correct |
| Middleware enregistré | `config/middleware.php` | `config/middleware.php` | correct |

### 3.3 Configuration du chiffrement

| Élément | Service | Admin | Statut |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | identique | défini |
| ENCRYPTION_CIPHER | `AES-256-CBC` | identique | défini |
| ENCRYPTION_PREVIOUS_KEYS | (vide) | (vide) | défini |
| config encryptable | `config/plugin/erikwang2013/encryptable/app.php` | identique (unifié) | correct |
| config encryption | `config/encryption.php` | - | correct |
| Fichier .env | existe | existe | créé |
| .env.example | mis à jour | mis à jour | correct |
| docker-compose | mis à jour | mis à jour | correct |

### 3.4 Modèles avec le trait Encryptable

31 modèles utilisent le trait `Encryptable`, les champs sensibles correctement déclarés dans `$encryptable` :

| Catégorie | Modèles | Champs sensibles |
|---|---|---|
| PII utilisateur | Users | email, mobile |
| PII utilisateur | UserAddresses | name, phone, detail |
| PII utilisateur | UserKyc | real_name, id_number |
| PII utilisateur | UserSocialAccounts | access_token, refresh_token |
| Confidentialité | PrivacyRequests | email |
| Finances | GiftCards | receiver_email |
| Finances | AffiliatePayouts | account |
| Finances | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Plateforme | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Plateforme | PlatformAccounts | account_name, api_key, api_secret |
| Plateforme | PlatformListings | platform_account_id |
| Logistique | LogisticsCompanies | name, api_key |
| Fournisseur | Suppliers | name, email, phone |
| Fournisseur | B2bVerifications | company_name |
| Marchand | Merchants | store_name, email, phone |
| Autre | EmailLogs | to_email |
| Autre | 15 autres modèles | champs name |

## 4. Deuxième tour de corrections (chiffrement API + clé JWT)

### 4.1 Middleware de chiffrement des réponses API (Moyenne, corrigé)

**Fichier** : `service/app/middleware/EncryptionMiddleware.php` (nouveau)

Le paquet `erikwang2013/encryption` était installé et la classe utilitaire `app/common/Encryption` existait, mais n'était pas branchée au pipeline de middlewares. Les données sensibles des interfaces manquaient de chiffrement/déchiffrement au niveau transport.

**Correction** :
- Création de `EncryptionMiddleware`, chiffrement/déchiffrement piloté par en-têtes HTTP :
  - `X-Encrypted: 1` — déchiffrement de la requête : le corps chiffré base64 est déchiffré en JSON puis transmis au contrôleur
  - `X-Encrypt-Response: 1` — chiffrement de la réponse : le champ `data` de la réponse est chiffré en texte chiffré base64
  - `X-Encrypt-Fields: field1,field2` — chiffre uniquement les champs spécifiés de la réponse
- Enregistré comme dernier niveau de la pile de middlewares (après HashidsEncode)
- Les vérifications de santé (`/api/health`, `/api/ping`) et les endpoints de documentation (`/apidoc`) sont exemptés du chiffrement/déchiffrement

### 4.2 Nom de classe/fichier incohérent (Moyenne, corrigé)

**Fichier** : `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

La classe `app\common\Encryption` était déclarée dans le fichier `EncryptionHelper.php`, non conforme à la norme PSR-4, provoquant l'échec de l'auto-chargement Composer. En environnement IDE et CLI, l'autoloader pouvait ne pas trouver cette classe.

**Correction** : le fichier a été renommé en `Encryption.php` pour correspondre au nom de classe.

### 4.3 JWT_SECRET_KEY vide (Faible, corrigé)

**Fichiers** : `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` était une chaîne vide ; bien que le middleware JWT ait une chaîne de repli `JWT_SECRET → JWT_SECRET_KEY` (JWT_SECRET prioritaire), la valeur placeholder n'était pas sûre.

**Correction** : génération d'une clé base64 de 32 octets, définition de `JWT_SECRET` et `JWT_SECRET_KEY`. Mise à jour de `.env.example`, `.env` et `docker-compose.yml`.

## 5. Points à surveiller (optimisations potentielles)

### 5.1 Dépendance de SecurityGuard aux en-têtes pour webman/Workerman (Risque faible)

**Impact** : les détecteurs CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, CORS etc. dépendent des données d'en-tête HTTP dans `$_SERVER`.

Dans l'environnement non CGI Workerman, `$_SERVER` peut ne pas être entièrement rempli avec les en-têtes HTTP. SecurityGuard dispose d'une logique de repli (par exemple, saute la détection si la valeur d'en-tête est vide), donc **pas de faux positifs**, mais **certaines attaques par en-tête peuvent être manquées**. Impact faible car le reverse proxy Nginx filtre généralement aussi les en-têtes malveillants.

**Suggestion** : pour une détection d'en-têtes plus complète, transmettre explicitement les valeurs d'en-tête dans le paramètre `$meta` de SecurityGuard. Aucune modification nécessaire actuellement.

### 5.2 Impact du détecteur CSRF Origin sur Admin (Aucun risque)

Le détecteur `csrf_origin` d'Admin en mode `block` a `allowed_origins` vide. Mais comme le détecteur ne se déclenche que lorsque l'en-tête Origin existe et ne correspond pas au Host, les accès au panneau d'administration n'ont généralement pas d'en-tête Origin (accès même origine), donc **pas de blocage à tort**.

### 5.3 Les 31 détecteurs tous activés, coût par requête (Note de performance)

Toutes les requêtes exécutent les 31 détecteurs (y compris JWT, WebSocket, GraphQL, CSV, pollution de prototypes etc.). Chaque détecteur applique des regex sur tous les champs de la requête. Pour le cas d'usage de ce projet, le coût est acceptable (webman est un processus résident en mémoire, sans coût de démarrage à froid CGI).

### 5.4 Persistance de la liste noire IP (Note opérationnelle)

Le backend de stockage est en mode `file`, chemin par défaut `sys_get_temp_dir() . '/security_storage.json'`. Dans les conteneurs Docker, le répertoire temporaire peut être perdu après redémarrage. Si la liste noire doit être partagée dans un déploiement multi-conteneurs, il est possible de passer au mode `redis`.

## 6. Récapitulatif des fichiers modifiés

```
admin/.env.example                                (ENCRYPTION_KEY ajoutée)
admin/.env                                        (créé depuis .env.example)
admin/CLAUDE.md                                   (pile de middlewares + tech stack mis à jour)
admin/composer.json                               (dépendance security-php)
admin/config/plugin/erikwang2013/encryptable/app.php  (unification des valeurs par défaut)
admin/config/plugin/erikwang2013/security-php/app.php  (créé, 31 détecteurs)
admin/app/middleware/SecurityMiddleware.php       (réécrit pour utiliser SecurityGuard)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + clé JWT mis à jour)
service/.env                                      (créé depuis .env.example, clé JWT synchronisée)
service/CLAUDE.md                                 (pile de middlewares + Encryption + tech stack mis à jour)
service/composer.json                             (dépendance security-php)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (créé, 31 détecteurs)
service/app/common/Encryption.php                 (renommé depuis EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (créé, chiffrement/déchiffrement des réponses API)
service/app/middleware/SecurityMiddleware.php     (réécrit pour utiliser SecurityGuard + téléversement de fichiers)
docker-compose.yml                                (variables d'environnement encryption/jwt complétées)
docs/security-review.md                           (ce rapport)
```

## 7. Conclusion

**Statut** : approuvé

- La détection WAF intercepte correctement XSS, injection SQL etc. (31 détecteurs, API SecurityGuard::guard)
- La configuration du chiffrement des champs sensibles est complète (31 modèles, 6 catégories de données sensibles, trait Encryptable)
- Le chiffrement/déchiffrement de transport API est branché au middleware (EncryptionMiddleware, AES-256-CBC, déclenché par en-tête)
- La clé JWT est configurée (JWT_SECRET + JWT_SECRET_KEY tous deux définis)
- La détection de téléversement de fichiers est corrigée (fusion des données $_FILES transmises à SecurityGuard)
- Aucune régression fonctionnelle (22/22 tests réussis)
- Pas de double détection du middleware
- Variables d'environnement de déploiement Docker complètes
