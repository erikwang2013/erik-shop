# Erik Shop — Cross-Border-E-Commerce-Plattform

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../VERSIONS.md).

Eine auf dem webman-Ökosystem basierende Full-Stack-Cross-Border-E-Commerce-Plattform, die B2C-/B2B-Szenarien und das Onboarding von Drittanbietern abdeckt.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Versionsübersicht

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| **Positionierung** | Einzelentwickler / kleiner E-Commerce | Wachsende Cross-Border-Händler | Enterprise-Full-Stack-Plattform |
| **Lizenz** | MIT Open Source | Kommerzielle Lizenz | Kommerzielle Lizenz |
| **Bezug** | Öffentlicher Download auf GitHub | Kontakt erik@erik.xyz | Kontakt erik@erik.xyz |
| **Branch** | `lite` | `standard` | `full` |
| **Aktuell** | — | — | ✅ |

---

## 2026-08-27 Circuit Breaker & Degradation

- Neuer Redis-Circuit-Breaker `CircuitBreaker` (`service/app/common/CircuitBreaker.php`): Zahlungs-Gateways (Stripe/PayPal/Klarna/Adyen) und Social-Login-Aufrufe einheitlich abgesichert — 5 aufeinanderfolgende Fehler → 30s geöffnet, nach TTL-Ablauf halboffener Test mit automatischer Wiederherstellung
- Whitelist für Geschäftsfehler: ungültige Karte/ungültiges Token zählen nicht als Fehler (verhindert, dass Angreifer den abhängigen Dienst mit ungültigen Requests lahmlegen)
- Bei Redis-Ausfall automatische Degradation mit Durchlass; während der Öffnung gibt die Schnittstelle 503 „Dienst vorübergehend nicht verfügbar" zurück
- Parameter: `config/concurrency.php` → `circuit_breaker` (fail_threshold=5, open_seconds=30)

---

## 2026-08-29 CDN-Unterstützung

- **Origin-Pull-Modell**: Uploads bleiben auf der lokalen Festplatte der Admin-Origin; die Datenbank speichert nur relative Pfade (Null-Migration); an den Ausgabegrenzen schreibt `Cdn::url()` auf `https://{CDN_DOMAIN}{pfad}` um; die CDN-Domain zeigt per CNAME zurück auf die Admin-Domain
- **Einheitliche Provider-Abstraktion**: `CdnProviderInterface` (purge / purgeByTag / preload), implementiert für Cloudflare, AWS CloudFront, Aliyun und Tencent Cloud (Fastly/Akamai reserviert); Fähigkeitsmatrix: purge 4/4, preload 2/4 (Aliyun/Tencent), purgeByTag 1/4 (Cloudflare)
- **Verwaltungskonfiguration**: CDN-Verwaltungsseite (3 Tabs: Konfiguration/Invalidierung/Protokolle) — Provider-Aktivierungsschalter, Zugangsdaten (Konfigurations-JSON verschlüsselt gespeichert), Verbindungstest, manuelle Invalidierung/Preload, Invalidierungsprotokolle (Tabellen `wa_cdn_providers` / `wa_cdn_purge_logs`); DB-Konfiguration überschreibt .env; der globale Ein/Aus-Schalter propagiert über gemeinsames Redis (Präfix `shop:`, 60s TTL) an den Service
- **Auto-Invalidierung (fail-open)**: Produkt- und Banner-CRUD löst die Invalidierung automatisch aus; ein CDN-Ausfall blockiert den Admin-CRUD nie
- **Edge-Caching**: nginx `location /app/admin/upload/` `expires 7d; Cache-Control public, max-age=604800, immutable`; Upload-Verzeichnisse bleiben über Docker-Volumes persistent (`admin_uploads:/app/plugin/admin/public/upload`, `service_public:/app/public/documents`)
- **Konfiguration**: `config/cdn.php` (admin + service) + 13 `CDN_*`-Umgebungsvariablen (CDN_ENABLED / CDN_DEFAULT_PROVIDER / CDN_DOMAIN / Zugangsdaten je Provider)

---

## Fehlerbehebungsprotokoll 2026-08-07

| # | Problem | Schweregrad | Lösung |
|---|------|--------|------|
| 1 | API-Antwortverschlüsselung nicht an Middleware angebunden | Medium | EncryptionMiddleware neu erstellt (per X-Encrypt-Response-Header gesteuert), als Stufe 10 der service-Pipeline registriert |
| 2 | Klassenname Encryption / Dateiname EncryptionHelper.php nicht passend | Medium | In Encryption.php umbenannt, PSR-4-Autoloading repariert |
| 3 | JWT_SECRET_KEY leer | Low | 32-Byte-Schlüssel generiert, JWT_SECRET und JWT_SECRET_KEY gesetzt |
| 4 | config/middleware.php als Index-Array → "Bad middleware config"-Absturz aller Worker | Critical | Auf `'' => [...]`-Standardstruktur umgestellt (webman verlangt appName => Liste) |
| 5 | security-php-Plugin-Konfiguration ohne enable-Schlüssel, von Config::loadFromDir still übersprungen | Critical | `'enable' => true` in den Plugin-app.php von service/admin ergänzt |
| 6 | config/bootstrap.php referenzierte nicht existierende support\bootstrap\Db/Redis | Critical | Entfernt; Eloquent-Initialisierung erfolgt nun über Db.php von vendor/webman/database, eingebunden von support/bootstrap.php |
| 7 | Globale Funktion redis() existiert nicht (webman 2.x hat diese Funktion nicht), Rate-Limiting/Risikomanagement fiel still aus | High | Neue support\Redis-Fassade (illuminate/redis + phpredis), redis()-Hilfsfunktion in app/functions.php registriert |
| 8 | Fehlende RedisManager-Konstruktorparameter (3 erforderlich: app-Container/driver/config) | High | stdClass-Container-Platzhalter + phpredis-Treiber + Verbindungskonfiguration übergeben |
| 9 | Modelle referenzierten nicht existierenden Trait Erik\Encryptable\Encryptable (im Paket ist es Maize\Encryptable-Namespace mit CastsAttributes) | Critical | Neue Kompatibilitätsschicht service/Erik/Encryptable/Encryptable.php (klassischer Trait, nutzt intern Encryption::php des Pakets) |
| 10 | Composer-Plugin Installer.php: doppelte Deklaration von Top-Level-Funktionen → fatal | Medium | Idempotente function_exists-Absicherung (in beiden vendor-Verzeichnissen von service/admin repariert) |
| 11 | HashidsEncode getHeader() gibt string zurück → implode-Fehler | High | (array)-Umwandlung |
| 12 | docker-compose/.env.example enthielten hartkodierte echte JWT-/Verschlüsselungsschlüssel | Critical | Durch change_me-Platzhalter ersetzt, Installationsassistent generiert Zufallsschlüssel |
| 13 | Bestellerstellung ohne Transaktion, Bestandsabzug nicht atomar (paralleler Überverkauf) | Critical | Db::transaction + bedingte Dekrementierung für atomaren Abzug |
| 14 | Gutscheineinlösung parallele Überausgabe/Übereinlösung | High | Transaktion + Zeilensperre lockForUpdate + atomic-guard für received_qty |
| 15 | PayPal-Webhook-Signaturprüfungsfelder stets leer (verify-webhook-signature schlägt zwangsläufig fehl) | High | Fünf Signaturprüfungsfelder aus den Request-Headern durchgereicht |
| 16 | SQL-Injection im Installationsassistenten (DB-Name/Passwort-Verkettung) | High | quote + Backtick-Escape + var_export zum Schreiben der Konfiguration |
| 17 | Stilles Degradieren bei fehlenden Verschlüsselungs-/Hash-Schlüsseln | High | Encryption/HashidsHelper werfen bei leeren oder ungültig langen Werten eine Exception |
| 18 | Fester Dateiname beim Bestellexport → gleichzeitige Überschreibung | Medium | uniqid-Dateiname + shutdown-Cleanup + try/catch |
| 19 | Hashids-Dekodierung schrieb nicht in die Request-Parameter zurück (Routenparameter/GET/POST) | High | setParams/setGet/setPost schreiben zurück |
| 20 | composer.lock von gitignore erfasst (Build nicht reproduzierbar) | Medium | Ignorierung entfernt, unter Versionskontrolle gestellt |
| 21 | Container ohne Healthcheck, ohne Startabhängigkeiten | Medium | Healthcheck für alle Dienste + depends_on condition |
| 22 | Admin-Dockerfile nicht lauffähig | High | COPY + composer install + EXPOSE + CMD ergänzt |
| 23 | Flutter-Kompilierungsfehler (intl-Konflikt/Generics im Konstruktor/überflüssige Klammern) + ausstehende Timer in Tests | High | intl ^0.20.2, statische Factory, pump treibt die Uhr voran |
| 24 | 27 ArkTS-Kompilierungsfehler in HarmonyOS verhindern den Build | High | Explizite Schnittstellen, Umbenennung reservierter Wörter, Single-Root-Build, @kit-Imports, hvigor-Konfiguration |

---

## Funktionsvergleich

> Hinweis: ◐ = Tabellenstruktur vorhanden, Geschäftslogik ausstehend (derzeit nur Datentabellen und Modelle, keine API-/Geschäftscode oder nur teilweise implementiert)

### Benutzersystem

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| E-Mail-Registrierung/Anmeldung (JWT) | ✅ | ✅ | ✅ |
| Social-Login (Google/Apple/Facebook) | — | ✅ | ✅ |
| Adressverwaltung | ✅ | ✅ | ✅ |
| Mitgliedsstufen + Punkte | — | — | ◐ |
| Geschenkkarten | — | — | ✅ |
| KYC-Identitätsverifizierung | — | — | ✅ |

### Produktsystem

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Kategorienverwaltung (Baumstruktur) | ✅ | ✅ | ✅ |
| SKU + Attribute | ✅ | ✅ | ✅ |
| Produktbilder | ✅ | ✅ | ✅ |
| Mehrsprachige Inhalte | — | ✅ | ✅ |
| Mehrwährungs-Preisgestaltung | — | ✅ | ✅ |
| Produktbewertungen | ✅ | ✅ | ✅ |
| Compliance-Kennzeichnungen (FDA/CE/RoHS) | — | ✅ | ✅ |
| ES-Mehrsprachensuche | — | ✅ | ✅ |
| Produkt-Feed-Synchronisierung (Google/Meta) | — | — | ✅ |
| Größentabellen | — | — | ✅ |

### Transaktionssystem

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Warenkorb | ✅ | ✅ | ✅ |
| Bestellverwaltung | ✅ | ✅ | ✅ |
| Zahlung (Stripe) | ✅ | ✅ | ✅ |
| Zahlung (PayPal) | ✅ | ✅ | ✅ |
| Zahlung (Klarna/Adyen) | — | Platzhalter | Platzhalter |
| BNPL „Kauf jetzt, zahle später“ | — | Platzhalter | Platzhalter |
| Rückerstattung | ✅ | ✅ | ✅ |
| Retourenverwaltung | — | ✅ | ✅ |
| Handelsrechnung/Packliste | — | ✅ | ✅ |
| Transportversicherung | — | — | ◐ |

### Cross-Border-Logistik

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Verwaltung internationaler Logistikunternehmen | — | ✅ | ✅ |
| Logistikzonen + Stufen-Tarife | — | ✅ | ✅ |
| Überseelager (Versand + Retoure) | — | ✅ | ✅ |
| HS-Deklaration | — | in Planung | in Planung |
| Sendungsverfolgung | — | ✅ | ✅ |
| Multi-Warehouse-Bestandsverwaltung | — | — | ✅ |

### Zoll & Steuern

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| HS-Code-Katalog | — | ✅ | ✅ |
| Zolltarifregel-Konfiguration | — | ✅ | ✅ |
| VAT/IOSS-Einstellungen | — | ✅ | ✅ |
| Compliance-Beschränkungen einzelner Länder | — | ✅ | ✅ |
| Preisdisplay-Compliance (inkl./exkl. Steuer) | — | ✅ | ✅ |

### Marketing-Tools

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gutscheine | ✅ | ✅ | ✅ |
| Karussell | ✅ | ✅ | ✅ |
| Blitzverkauf | — | ✅ | ✅ |
| Gruppenkauf | — | ✅ | ✅ |
| Affiliate (Link + Provision + Auszahlung) | — | ✅ | ✅ |
| Regionale Aktionen | — | ✅ | ✅ |

### Lieferkette

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Lieferantenverwaltung | — | — | ✅ |
| Einkaufsbestellungen | — | — | ◐ |
| Qualitätsprüfung (Wareneingangs-/Warenausgangssperre) | — | — | ◐ |
| Bestandsprotokoll (unveränderliches Hauptbuch) | — | — | ✅ |
| Bestandsumlagerung | — | — | ◐ |

### Plattform-Erweiterung

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Multi-Shop-Verwaltung | — | — | ✅ |
| Multi-Vendor-Onboarding (Drittanbieter) | — | — | ✅ |
| Amazon/eBay/Shopee-Listings | — | — | ✅ |
| Bestellaggregation über mehrere Plattformen | — | — | ✅ |
| B2B-Großhandel (Staffelpreise/Preisangebot) | — | — | ✅ |

### Risikomanagement & Compliance

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Basis-Angriffserkennung (XSS/SQLi) | ✅ | ✅ | ✅ |
| Erweiterte Angriffserkennung (XXE/SSRF usw.) | — | — | ✅ |
| PosterVerify-Mensch-Maschine-Verifizierung | — | ✅ | ✅ |
| Risiko-Regel-Engine | — | — | ✅ |
| GDPR/CCPA-Datenanfragen | — | — | ✅ |
| Cookie-Consent-Verwaltung | — | — | ✅ |
| Quellen-Tracking der Plattformen | — | ✅ | ✅ |
| Quellen-Tracking der Plattformen (8 Plattformen) | — | ✅ | ✅ |

### Hohe Nebenläufigkeit

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| DB-Verbindungspool | ✅ | ✅ | ✅ |
| Token-Bucket-Limiting | — | — | ✅ |
| DB-Read/Write-Splitting | — | — | ✅ |
| Cron-Jobs (11) | — | — | ✅ |
| CDN-Edge-Caching (Origin-Pull) | ✅ | ✅ | ✅ |

### Content & Wachstum

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Systembenachrichtigungen | ✅ | ✅ | ✅ |
| E-Mail-Vorlagen | — | — | ✅ |
| Mehrsprachige CMS-Seiten | — | — | ✅ |
| FAQ + Wissensdatenbank | — | — | ◐ |
| Abo-Kauf | — | — | ✅ |
| AB-Tests | — | — | ◐ |
| Echtzeit-Kundenservice (WebSocket-IM) | — | — | ✅ |

### Clients

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| Internationalisierung (5-Sprachen-Übersetzung) | ✅ | ✅ | ✅ |
| API-Dokumentation (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web-Admin | ✅ | ✅ | ✅ |
| Admin-ECharts-Dashboard | ✅ | ✅ | ✅ |
| Admin-Excel/PDF-Export | ✅ | ✅ | ✅ |
| Mehrsprachige Oberfläche (5 Sprachen) | ✅ | ✅ | ✅ |

---

## Designvergleich

### Datenbank

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Tabellen | **23** | **62** | **110** |
| Benutzerbezogen | 3 | 5 | 7 |
| Produktbezogen | 6 | 15 | 19 |
| Transaktionsbezogen | 6 | 9 | 9 |
| Logistikbezogen | 0 | 7 | 9 |
| Zollbezogen | 0 | 5 | 5 |
| Marketingbezogen | 4 | 8 | 8 |
| Lieferkette | 0 | 0 | 5 |
| Risikomanagement & Compliance | 0 | 0 | 5 |
| Multi-Plattform | 0 | 0 | 9 |
| Content & Wachstum | 0 | 1 | 14 |
| Kundenservice/AB/API | 0 | 0 | 5 |

### Middleware-Pipeline

```
Lite:      Cors → Security(4 Kategorien) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4 Kategorien) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31 Kategorien) → RateLimit(Token-Bucket) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(Schnittstellenverschlüsselung)
```

### Codeumfang

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Service-Modelle | 26 | 55 | 111 |
| Service-Controller | 15 | 24 | 39 |
| Service-Middleware | 7 | 9+2 | 12+2 |
| Service-Hilfsklassen | 5 | 5 | 15 |
| Admin-Modelle | 15 | 34 | 76 |
| Admin-Controller | 15 | 27 | 82 |
| Flutter-Seiten | 11 | 11 | 11 |
| HarmonyOS | — | — | 9 Seiten |
| PHPUnit-Tests | 22 | 22 | 54 |

### Technologie-Stack

| Komponente | Lite | Standard | Full |
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

## Upgrade-Pfad

```
Lite (Open Source) ──→ Standard (kommerziell) ──→ Full (kommerziell)

Upgrade-Verfahren:
  1. Kontaktieren Sie erik@erik.xyz, um den Code der jeweiligen Version zu erhalten
  2. Inkrementelles Schema importieren (lite→standard +~40 Tabellen, standard→Full +~48 Tabellen)
  3. Controller/Modelle/Middleware der jeweiligen Version kopieren
  4. composer require für neu hinzukommende Abhängigkeitspakete
```

---

## Bezug

| Version | Weg |
|------|------|
| **Lite** | Open Source auf GitHub [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php), Branch `lite` |
| **Standard** | Kommerzielle Lizenz — Kontakt **erik@erik.xyz** |
| **Full** | Kommerzielle Lizenz — Kontakt **erik@erik.xyz** |

Die kommerzielle Lizenz umfasst: Vollständiger Quellcode / Bereitstellungssupport / Priorisierte Updates / Technische Beratung
