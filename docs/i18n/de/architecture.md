# Cross-Border-E-Commerce-Plattform — Architekturübersicht

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../architecture.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Technologie-Stack

| Ebene | Technologie | Version |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| Clients | Flutter (5 Plattformen) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| Datenbank | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| Zahlung | Stripe / PayPal / Klarna / Adyen | — |

## 2. Verzeichnisstruktur

```
shop-php/
  service/            Geschäfts-API (251 PHP-Dateien)
    config/            35 Konfigurationen (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39 Controller (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 Modelle (BaseModel + 110 Geschäftsmodelle)
    app/middleware/     14 Middleware (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 Hilfsklassen (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (wurde durch install.sql im Wurzelverzeichnis ersetzt) + seeders
    tests/              4 Testklassen (22 Tests, 45 Assertions)
  admin/              Verwaltungskonsole (239 PHP-Dateien)
    plugin/admin/app/controller/shop/ 82 Controller
    plugin/admin/app/model/shop/      76 Modelle
    plugin/admin/app/view/shop/       ECharts-Dashboard
    app/middleware/    5 Middleware (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/               Clients
    flutter/lib/      25 Dart (11 Seiten + Kernschicht + Routing)
    harmonyos/        14 ArkTS (9 Seiten + API-Client + globaler Zustand)
  docs/               5 Designdokumente
  .claude/skills/     38 Entwicklungs-Skills
```

## 3. Middleware-Pipeline

```
Service: Cors → Security(Erkennung von 31 Angriffsarten) → RateLimit(Token-Bucket-Limiting) → Platform(Erkennung von 8 Plattformen)
        → GeoIp(Region) → Locale(Sprache) → HashidsDecode → VersionRoute
        → (PosterVerify Mensch-Maschine-Verifizierung) → (JwtAuth Token) → HashidsEncode → Encryption(Schnittstellenverschlüsselung)

Admin:  Security → Platform → HashidsDecode → AccessControl(integriertes RBAC) → HashidsEncode
```

## 4. Sicherheit

- **Erkennung von 31 Angriffsarten**: XSS/SQL-Injection/Befehlsinjektion/CRLF/Pfad-Traversal/Body/ContentType/Datei-Upload/Brute-Force/XXE/SSRF/Deserialisierung/LDAP/E-Mail-Header/SSTI/NoSQL/Open-Redirect/JWT-Angriff/Host/Request-Smuggling/GraphQL/XPATH/Log4Shell/SSI/CSV-Formel/Datenleck/Prototype-Pollution/WebSocket/CORS/DNS-Rebinding/HTTP-Methode/CSRF-Origin
- **Drei-Schichten-Verschlüsselung**: Schnittstellenebene (AES-256-CBC) + Datenbankebene (Encryptable-Trait) + ID-Verschleierung (Hashids)
- **Plattform-Tracking**: 8 Plattformen (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform-Header + Aufzeichnung in 6 Tabellen

## 5. Hohe Nebenläufigkeit

- **Rate-Limiting**: Token-Bucket-Sliding-Window (Redis ZSET), 6 Endpunkt-Regeln
- **Circuit Breaker/Degradation**: Redis-Circuit-Breaker — externe API-Aufrufe (Zahlungs-Gateways/Social-Login): 5 aufeinanderfolgende Fehler → 30s geöffnet, halboffener Test mit automatischer Wiederherstellung; Geschäftsfehler zählen nicht als Fehler; bei Redis-Ausfall automatische Degradation mit Durchlass (503)
- **DB**: Read/Write-Splitting (2 Lesereplikate + sticky) + Verbindungspool (50/10)
- **Langsame Operationen**: werden von separaten Cron-Prozessen verarbeitet (Feed-Synchronisierung/Empfehlungsberechnung/Zahlungsabgleich/Settlement-Berechnung usw.)

## 6. Tests

22 Tests / 45 Assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+Datenleck
- JwtTest (4): encode/decode-Validierung
- ApiResponseTest (3): success/fail/paginate

## 7. Bereitstellung

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# Manuell
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **Mehrsprachigkeit (i18n)**: Übersetzungsdateien für 5 Sprachen + LocaleMiddleware + Flutter AppLocalizations
- **API-Dokumentation**: automatisch von hg/apidoc generiert (6 Gruppen, controller-annotationsgesteuert)
- **Plattform-Tracking**: X-Platform-Header für 8 Plattformen + DB-Aufzeichnung

Siehe: [Bereitstellungsdokument](deployment.md) | [Vollständiges Architekturdokument](architecture-full.md) | [Funktionsdesigndokument](features.md)
