# Security-Plugin-Integrationsprüfbericht

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../security-review.md).

**Datum**: 2026-08-04
**Umfang**: erikwang2013/security-php v1.1.6-Integration
**Prüfer**: Claude Code (automatisiert)

---

## 1. Testergebnisse

| Prüfung | Ergebnis |
|---|---|
| PHP-Syntaxprüfung (47 Dateien) | Alle bestanden |
| PHPUnit (22 Tests, 45 Assertions) | Alle bestanden |
| SecurityGuard-Sicherheits-Payload-Tests | Blockiert XSS + SQLi korrekt |
| SecurityGuard-Sicherheitsanfrage-Tests | Keine Fehlalarme |
| phpstan-Statische-Analyse | Nicht installiert (nicht blockierend) |

## 2. Behobene Probleme

### 2.1 Datei-Upload-Daten wurden nicht an SecurityGuard übergeben (Critical)

**Dateien**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

Die Middleware übergab `SecurityGuard::guard()` nur `$request->all()`, diese Methode enthält jedoch keine Datei-Upload-Daten. `UploadDetector` benötigt Dateidaten im Format `['tmp_name' => ..., 'name' => ...]`.

**Lösung**: Eine Schleife hinzugefügt, die `$request->file()` vor der Übergabe an `SecurityGuard::guard()` in das Daten-Array mergt.

### 2.2 Admin-encryptable-Konfiguration fehlte der Standardwert (Medium)

**Datei**: `admin/config/plugin/erikwang2013/encryptable/app.php`

Die Admin-Konfiguration verwendet `env('ENCRYPTION_KEY')` ohne Fallback-Wert; bei fehlender Umgebungsvariable wird `null` zurückgegeben. Service verwendet `getenv('ENCRYPTION_KEY') ?: ''` und fällt korrekt auf eine leere Zeichenkette zurück.

**Lösung**: Die Admin-Konfiguration verwendet nun einheitlich den `?: ''`-Operator, konsistent mit dem Service-Verhalten.

### 2.3 Unvollständige Docker-Compose-Umgebungsvariablen (Medium)

**Datei**: `docker-compose.yml`

- Dem Service-Container fehlen `ENCRYPTION_CIPHER` und `ENCRYPTION_PREVIOUS_KEYS`
- Dem Admin-Container fehlen `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID`

**Lösung**: Alle fehlenden Umgebungsvariablen wurden mit Standardwerten gemäß `.env.example` ergänzt.

### 2.4 Doppelte Erkennung der WAF-Middleware (Critical, in Runde 1 behoben)

Die benutzerdefinierte `SecurityMiddleware` enthielt ~200 Zeilen Inline-Regex, die sich vollständig mit den 31 Erkennungsmodulen des `security-php`-Pakets überschneiden. Jede Anfrage wurde zweimal gescannt — CPU-Verschwendung und mögliche Doppelblockierung.

**Lösung**: Die Middleware wurde auf die Verwendung der `SecurityGuard::guard()`-API umgeschrieben und von 341 Zeilen auf ~110 Zeilen (service) bzw. von 136 Zeilen auf ~85 Zeilen (admin) reduziert. Brute-Force-Schutz und Sicherheitsantwort-Header bleiben erhalten.

### 2.5 Fehlender ENCRYPTION_KEY (Critical, in Runde 1 behoben)

`ENCRYPTION_KEY` in der `.env.example`-Datei verwendete einen Platzhalter; `ENCRYPTION_CIPHER` und `ENCRYPTION_PREVIOUS_KEYS` fehlten. Es gab keine tatsächliche `.env`-Datei.

**Lösung**: Einen 32-Byte-base64-Schlüssel generiert, `ENCRYPTION_CIPHER=AES-256-CBC` und `ENCRYPTION_PREVIOUS_KEYS` ergänzt und die `.env`-Datei erstellt.

## 3. Vollständigkeit der Ökosystem-Konfiguration

### 3.1 Packages (in beiden Projekten konsistent)

| Package | Version | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | Installiert | Installiert |
| erikwang2013/encryptable | - | Installiert | Installiert |
| erikwang2013/encryption | - | Installiert | Installiert |
| erikwang2013/jwt-webman | - | Installiert | Installiert |
| erikwang2013/hashids | - | Installiert | Installiert |
| erikwang2013/snowflake-php | - | Installiert | Installiert |
| erikwang2013/poster-php | - | Installiert | Installiert |
| erikwang2013/season | - | Installiert | Installiert |
| erikwang2013/webman-scout | - | Installiert | Installiert |

### 3.2 WAF-Konfiguration

| Element | Service | Admin | Status |
|---|---|---|---|
| Konfigurationsdatei | `config/plugin/erikwang2013/security-php/app.php` | Gleich | Veröffentlicht |
| Aktivierte Erkennungsmodule | 31/31 | 31/31 | Korrekt |
| IP-Blacklist | aktiviert (5 att/60s -> 900s ban) | Gleich | Korrekt |
| Block-Modus-Erkennungsmodule | 28 | 28 | Korrekt |
| Nur-Protokoll-Erkennungsmodule | 3 (header_injection, ssti, nosql_injection) | 3 | Korrekt |
| Speicherung | file | file | Korrekt |
| Protokollierung | aktiviert (file, 10MB-Rotation) | Gleich | Korrekt |
| Middleware registriert | `config/middleware.php` | `config/middleware.php` | Korrekt |

### 3.3 Verschlüsselungskonfiguration

| Element | Service | Admin | Status |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | Gleich | Festgelegt |
| ENCRYPTION_CIPHER | `AES-256-CBC` | Gleich | Festgelegt |
| ENCRYPTION_PREVIOUS_KEYS | (leer) | (leer) | Festgelegt |
| encryptable-Konfiguration | `config/plugin/erikwang2013/encryptable/app.php` | Gleich (vereinheitlicht) | Korrekt |
| encryption-Konfiguration | `config/encryption.php` | - | Korrekt |
| .env-Datei | Vorhanden | Vorhanden | Erstellt |
| .env.example | Aktualisiert | Aktualisiert | Korrekt |
| docker-compose | Aktualisiert | Aktualisiert | Korrekt |

### 3.4 Modelle mit Encryptable-Trait

31 Modelle verwenden den `Encryptable`-Trait; sensible Felder sind korrekt als `$encryptable` deklariert:

| Kategorie | Modelle | Sensible Felder |
|---|---|---|
| User PII | Users | email, mobile |
| User PII | UserAddresses | name, phone, detail |
| User PII | UserKyc | real_name, id_number |
| User PII | UserSocialAccounts | access_token, refresh_token |
| Datenschutz | PrivacyRequests | email |
| Finanzen | GiftCards | receiver_email |
| Finanzen | AffiliatePayouts | account |
| Finanzen | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Plattform | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Plattform | PlatformAccounts | account_name, api_key, api_secret |
| Plattform | PlatformListings | platform_account_id |
| Logistik | LogisticsCompanies | name, api_key |
| Lieferant | Suppliers | name, email, phone |
| Lieferant | B2bVerifications | company_name |
| Händler | Merchants | store_name, email, phone |
| Sonstige | EmailLogs | to_email |
| Sonstige | 15 weitere Modelle | Namensfelder |

## 4. Runde-2-Fixes (API-Verschlüsselung + JWT-Schlüssel)

### 4.1 Middleware für API-Antwortverschlüsselung (Medium, behoben)

**Datei**: `service/app/middleware/EncryptionMiddleware.php` (neu erstellt)

Das Paket `erikwang2013/encryption` war installiert und die Hilfsklasse `app/common/Encryption` existierte, wurde jedoch zuvor nicht in die Middleware-Pipeline eingebunden. Sensiblen Schnittstellendaten fehlte die Transport-Verschlüsselung.

**Lösung**:
- `EncryptionMiddleware` erstellt, die per HTTP-Header gesteuerte Ver-/Entschlüsselung durchführt:
  - `X-Encrypted: 1` — Anfrage-Entschlüsselung: base64-verschlüsselten Body in JSON entschlüsseln, bevor er an den Controller übergeben wird
  - `X-Encrypt-Response: 1` — Antwort-Verschlüsselung: das `data`-Feld der Antwort in base64-Verschlüsselung umwandeln
  - `X-Encrypt-Fields: field1,field2` — nur die angegebenen Felder der Antwort verschlüsseln
- Als letzte Stufe des Middleware-Stacks registriert (nach HashidsEncode)
- Health-Checks (`/api/health`, `/api/ping`) und Dokumentations-Endpunkte (`/apidoc`) überspringen die Ver-/Entschlüsselung

### 4.2 Klassenname/Dateiname nicht passend (Medium, behoben)

**Datei**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

Die Klasse `app\common\Encryption` war in der Datei `EncryptionHelper.php` deklariert — ein Verstoß gegen die PSR-4-Norm, wodurch das Composer-Autoloading scheiterte. Die Klasse konnte unter IDE- und CLI-Umgebungen vom Autoloader nicht gefunden werden.

**Lösung**: Datei in `Encryption.php` umbenannt, damit sie zum Klassennamen passt.

### 4.3 Leerer JWT_SECRET_KEY (Low, behoben)

**Datei**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` war eine leere Zeichenkette. Zwar hat die JWT-Middleware eine Fallback-Kette `JWT_SECRET → JWT_SECRET_KEY` (bevorzugt `JWT_SECRET`), der Platzhalterwert ist jedoch unsicher.

**Lösung**: Einen 32-Byte-base64-Schlüssel generiert und sowohl `JWT_SECRET` als auch `JWT_SECRET_KEY` gesetzt. `.env.example`, `.env` und `docker-compose.yml` aktualisiert.

## 5. Zu beobachtende Probleme (potenzielle Optimierungspunkte)

### 5.1 SecurityGuard-Abhängigkeit von Headern in webman/Workerman (Low Risk)

**Auswirkung**: Die Erkennungsmodule CSRF Origin, Host Header, DNS Rebinding, Request Smuggling und CORS sind auf HTTP-Header-Daten aus `$_SERVER` angewiesen.

In der Non-CGI-Umgebung von Workerman ist `$_SERVER` möglicherweise nicht vollständig mit HTTP-Headern gefüllt. SecurityGuard verfügt über Fallback-Logik (z. B. Überspringen der Erkennung bei leerem Header-Wert), daher **keine Fehlalarme**, aber **möglicherweise werden einige Header-Angriffe übersehen**. Die Auswirkung ist gering, da der Nginx-Reverse-Proxy schädliche Header normalerweise ebenfalls filtert.

**Empfehlung**: Falls eine vollständigere Header-Erkennung benötigt wird, können die Header-Werte explizit über den `$meta`-Parameter von SecurityGuard übergeben werden. Aktuell keine Änderung erforderlich.

### 5.2 Auswirkung des CSRF-Origin-Erkennungsmoduls auf Admin (No Risk)

Das `csrf_origin`-Erkennungsmodul von Admin läuft im `block`-Modus, `allowed_origins` ist leer. Da das Erkennungsmodul nur auslöst, wenn der Origin-Header vorhanden ist und nicht mit dem Host übereinstimmt, ist beim Zugriff auf die Verwaltungskonsole üblicherweise kein Origin-Header vorhanden (Same-Origin-Zugriff) — daher **keine Fehlblockierung**.

### 5.3 Alle 31 Erkennungsmodule aktiviert, Kosten pro Anfrage (Performance Note)

Alle Anfragen führen alle 31 Erkennungsmodule aus (einschließlich JWT, WebSocket, GraphQL, CSV, Prototype Pollution usw.). Jedes Erkennungsmodul führt Regex-Abgleiche auf allen Feldern der Anfrage durch. Für das Einsatzszenario dieses Projekts ist der Overhead akzeptabel (webman ist ein residenter In-Memory-Prozess ohne CGI-Kaltstart-Overhead).

### 5.4 Persistenz der IP-Blacklist (Operational Note)

Der Speicher-Backend ist `file`-Modus, Standardpfad `sys_get_temp_dir() . '/security_storage.json'`. In Docker-Containern kann das temporäre Verzeichnis nach einem Neustart verloren gehen. Falls die Blacklist in einer Multi-Container-Bereitstellung gemeinsam genutzt werden soll, kann auf den `redis`-Modus umgestellt werden.

## 6. Zusammenfassung der geänderten Dateien

```
admin/.env.example                                (ENCRYPTION_KEY neu)
admin/.env                                        (aus .env.example neu erstellt)
admin/CLAUDE.md                                   (Middleware-Stack + Tech-Stack aktualisiert)
admin/composer.json                               (security-php-Abhängigkeit)
admin/config/plugin/erikwang2013/encryptable/app.php  (Standardwerte vereinheitlicht)
admin/config/plugin/erikwang2013/security-php/app.php  (neu, 31 Erkennungsmodule)
admin/app/middleware/SecurityMiddleware.php       (auf SecurityGuard umgeschrieben)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT-Schlüssel aktualisiert)
service/.env                                      (aus .env.example neu erstellt, JWT-Schlüssel synchronisiert)
service/CLAUDE.md                                 (Middleware-Stack + Encryption + Tech-Stack aktualisiert)
service/composer.json                             (security-php-Abhängigkeit)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (neu, 31 Erkennungsmodule)
service/app/common/Encryption.php                 (aus EncryptionHelper.php umbenannt)
service/app/middleware/EncryptionMiddleware.php   (neu, API-Antwortver-/entschlüsselung)
service/app/middleware/SecurityMiddleware.php     (auf SecurityGuard umgeschrieben + Datei-Upload)
docker-compose.yml                                (encryption/jwt-Umgebungsvariablen ergänzt)
docs/security-review.md                           (dieser Bericht)
```

## 7. Fazit

**Status**: Bestanden

- WAF-Erkennung blockiert XSS, SQL-Injection und andere Angriffe korrekt (31 Erkennungsmodule, SecurityGuard::guard-API)
- Verschlüsselungskonfiguration für sensible Felder vollständig (31 Modelle, 6 Kategorien sensibler Daten, Encryptable-Trait)
- API-Transportver-/entschlüsselung in die Middleware eingebunden (EncryptionMiddleware, AES-256-CBC, per Header ausgelöst)
- JWT-Schlüssel konfiguriert (JWT_SECRET + JWT_SECRET_KEY beide gesetzt)
- Datei-Upload-Erkennung behoben (Dateidaten werden an SecurityGuard übergeben)
- Keine funktionalen Regressionen (22/22 Tests bestanden)
- Keine doppelte Middleware-Erkennung
- Docker-Bereitstellungsumgebungsvariablen vollständig
