# Cross-Border-E-Commerce-Plattform — Installationsanleitung

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../INSTALL.md).
>
> Cross-border E-Commerce Platform Installation Guide
>
> [Chinesisches README](../../../README.md) | [English README](../../README-EN.md) | [Prüfbericht](../../AUDIT-REPORT.md)

---

## Umgebungsanforderungen / Requirements

| Komponente | Mindestversion | Empfohlene Version |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (optional) |

### PHP-Erweiterungen

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Installationsmethoden

### Methode 1 (empfohlen): Web-basierter Ein-Klick-Installationsassistent

Rufen Sie die Installationsseite im Browser auf, tragen Sie Datenbankinformationen und Administrator-Konto ein. **Tabellenerstellung, Konfiguration und Administrator-Anlage erfolgen vollautomatisch.**

```bash
# 1. Abhängigkeiten installieren
cd admin/
composer install

# 2. Verwaltungskonsole starten
php start.php start

# 3. Im Browser öffnen (beim ersten Start automatische Weiterleitung zur Installationsseite)
# http://127.0.0.1:8788/app/admin/install/step1
```

Der Installationsassistent erledigt **automatisch**:
- MySQL-Datenbank anlegen (falls nicht vorhanden)
- Alle 117 Tabellen aus `install.sql` importieren (7 `wa_` + 110 `erik_`)
- Menüs der Verwaltungskonsole importieren
- `plugin/admin/config/database.php` und `thinkorm.php` erzeugen
- `service/.env` erzeugen (mit zufällig generierten JWT-/Hashids-/Verschlüsselungsschlüsseln)
- Super-Administrator-Konto anlegen
- SIGUSR1-Signal zum Neuladen der Dienste senden

> Nach der Installation muss der service/-API-Dienst noch gestartet werden (siehe Schritt 5 unten).

---

### Methode 2: Manuelle Installation

<details>
<summary>Geeignet für Bereitstellung per Kommandozeile oder bestehende Datenbank-Umgebung</summary>

### 1. Datenbank anlegen

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Datenbank importieren

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` enthält **117 Tabellen** sowie Standard-Seed-Daten.

### 3. service/.env konfigurieren

```bash
cd service/
cp .env.example .env
# .env bearbeiten und tatsächliche Datenbank-/Redis-/JWT-Parameter eintragen
```

**Wichtige Konfigurationsoptionen:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<zufälliger 32-Byte-Schlüssel>
HASHIDS_SALT=<zufälliger Salt-Wert>
ENCRYPTION_KEY=<zufälliger 32-Byte-Schlüssel>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. admin/ konfigurieren

```bash
cd admin/
cp .env.example .env
# .env bearbeiten und dieselben Datenbankinformationen wie bei service eintragen
```

### 5. Administrator-Konto anlegen

```sql
-- Das Passwort muss mit bcrypt generiert werden
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Superadministrator', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Methode 3: Docker-Bereitstellung

```bash
# 1. Umgebungsvariablen konfigurieren
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Alle Dienste starten
docker-compose up -d

# 3. Web-Installationsassistent ausführen
# http://localhost/app/admin/install/step1
```

Docker-Dienste: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Dienste starten

```bash
# Abhängigkeiten installieren (für beide Projekte erforderlich)
cd service/ && composer install
cd admin/ && composer install

# API-Dienst starten
cd service/
php start.php start -d

# Verwaltungskonsole starten
cd admin/
php start.php start -d
```

| Dienst | Standard-Port | Verifizierungsmethode |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Verwaltungskonsole | 8788 | Browser: `http://127.0.0.1:8788/app/admin` |

### Seed-Daten importieren (optional)

```bash
cd service/
php start.php seed:countries     # Länder/Regionen
php start.php seed:currencies    # Währungen
php start.php seed:hs_codes      # HS-Codes
php start.php seed:compliance    # Compliance-Kategorien
```

---

## Verzeichnisstruktur

```
shop-php/
├── install.sql              # Konsolidiertes vollständiges Installations-SQL
├── admin/                   # Verwaltungskonsole (webman-admin + LayUI)
│   ├── config/database.php  # Datenbankkonfiguration
│   ├── plugin/admin/        # webman-admin-Plugin
│   └── start.php
├── service/                 # API-Dienst (webman RESTful)
│   ├── config/              # Konfigurationsdateien
│   ├── database/schema.sql  # Ursprüngliches Geschäftstabellen-SQL (durch install.sql ersetzt)
│   ├── database/seeders/    # Seed-Daten
│   └── start.php
```

---

## Datenbankschema-Übersicht

| Modul | Tabellenpräfix | Anzahl Tabellen | Beschreibung |
|------|--------|--------|------|
| Verwaltungskonsolen-System | `wa_` | 7 | Administrator/Rollen/Berechtigungen/Konfiguration/Anhänge |
| Benutzer & Konten | `erik_users_*` | 7 | Benutzer/Adressen/Sozial/KYC/Favoriten/Mitgliedschaft |
| Produkte & Kategorien | `erik_product_*` | 16 | Produkte/SKU/mehrsprachig/mehrwährig/Bewertungen/Compliance/HS |
| Warenkorb & Bestellungen | `erik_order_*` | 9 | Warenkorb/Bestellungen/Zahlung/Rückerstattung/Retoure/Zollabfertigung |
| Länder/Währungen/Logistik | `erik_shipping_*` | 11 | Länder/Währungen/Wechselkurse/Logistik/Zonen/Lager/Bestände |
| Zoll & Steuern | `erik_hs_*` | 5 | HS-Codes/Zolltarife/VAT/Compliance-Beschränkungen |
| Zahlung & Finanzen | `erik_payment_*` | 6 | Zahlungs-Gateways/Plattformabrechnung/Lieferantenabrechnung/Wechselkurs-Gewinne/-Verluste |
| Marketing | `erik_coupon_*` | 9 | Gutscheine/Blitzverkäufe/Gruppenkäufe/Affiliate |
| Lieferkette | `erik_supplier_*` | 7 | Lieferanten/Einkauf/Qualitätsprüfung |
| Risikomanagement & Compliance | `erik_risk_*` | 6 | Risikoregeln/GDPR/Cookies/Datenschutz |
| Multi-Plattform | `erik_platform_*` | 8 | Multi-Shop/Plattformkonten/Listings/Verkäufer |
| Content & Erlebnis | `erik_*` | 12 | CMS/Feed/Größen/Benachrichtigungen/E-Mail/Suche/Operationsprotokolle |
| Abos/Punkte usw. | `erik_*` | 7 | Abos/Punkte/Geschenkkarten/B2B |
| AB-Tests/API/Einstellungen | `erik_*` | 7 | AB-Tests/Rate-Limiting/API-Dokumente/Systemkonfiguration |

---

## Fehlerbehebung

### MySQL-Fehler "Specified key was too long"

```sql
-- Sicherstellen, dass utf8mb4 + InnoDB verwendet wird und innodb_large_prefix aktiviert ist
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Port-Konflikt

`APP_PORT` in `admin/.env` oder `service/.env` ändern.

### Redis-Verbindung fehlgeschlagen

Prüfen, ob die Redis-Erweiterung installiert und der Redis-Dienst gestartet ist:
```bash
redis-cli ping  # sollte PONG zurückgeben
```

### Snowflake-ID-Konflikt

Wenn mehrere Server gleichzeitig instanziiert werden, muss `SNOWFLAKE_WORKER_ID` auf jedem Server unterschiedlich sein (0-31).

---

## Kurzreferenz der Entwicklungsbefehle

```bash
# service/ (API)
php start.php start          # Starten
php start.php start -d       # Daemon
php start.php reload         # Heißes Neuladen
php start.php stop           # Stoppen
php start.php status         # Status

# admin/ (Verwaltungskonsole)
php start.php start
php start.php start -d
php start.php reload
```
