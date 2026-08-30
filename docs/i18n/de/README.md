# Erik Shop — Cross-Border-E-Commerce-Plattform (Vollversion, Full)

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../../README.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Version

> Vereinfachte Version (MIT Open Source): `lite` | Standardversion (kommerziell): `standard` | Vollversion (kommerziell): `full`
>
> Kontakt für kommerzielle Lizenz: **erik@erik.xyz** | Versionsvergleich: [VERSIONS.md](VERSIONS.md)

## Sprache / Languages

| Sprache | Link |
|------|------|
| Chinesisch | [README.md](README.md) |
| Englisch | [docs/i18n/en/README.md](../en/README.md) |
| Koreanisch | [docs/i18n/ko/README.md](../ko/README.md) |
| Russisch | [docs/i18n/ru/README.md](../ru/README.md) |
| Deutsch | [docs/i18n/de/README.md](../de/README.md) |
| Französisch | [docs/i18n/fr/README.md](../fr/README.md) |
| Spanisch | [docs/i18n/es/README.md](../es/README.md) |
| Portugiesisch | [docs/i18n/pt/README.md](../pt/README.md) |
| Hindi | [docs/i18n/hi/README.md](../hi/README.md) |
| Arabisch | [docs/i18n/ar/README.md](../ar/README.md) |
| Bengalisch | [docs/i18n/bn/README.md](../bn/README.md) |
| Indonesisch | [docs/i18n/id/README.md](../id/README.md) |
| Japanisch | [docs/i18n/ja/README.md](../ja/README.md) |

## Projektübersicht

Full-Stack-Cross-Border-E-Commerce-Plattform auf Basis des webman-Ökosystems, mit Unterstützung für B2C-/B2B-Szenarien und Onboarding von Drittanbietern.

### Technische Architektur

| Ebene | Technologie | Verzeichnis |
|------|------|------|
| Geschäfts-API | webman + illuminate/database + erikwang2013/* | `service/` |
| Verwaltungskonsole | webman-admin + LayUI + ECharts | `admin/` |
| Client | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| HarmonyOS-Client | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### Technologie-Stack

**Serverseitig:** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**Kernpakete:** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**Zahlung:** Stripe, PayPal (vollständig); Klarna, Adyen (Platzhalter, `PaymentGateway::make` nicht implementiert, siehe PLAN.md)
**Clients:** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## Architektur-Diagramme

> Vollständige Diagrammsammlung und große Ansicht: [diagrams.md](diagrams.md)

### Systemarchitektur

![Systemarchitektur](diagrams/01-system-architecture.svg)

### Anfrageverarbeitungs-Fluss

![Anfrageverarbeitungs-Fluss](diagrams/02-request-processing-flow.svg)

### Funktionsmodul-Übersicht

![Funktionsmodul-Übersicht](diagrams/03-feature-module-map.svg)
> Die Karte umfasst 19 große Funktionsmodule (einschließlich Berichtszentrum und Plattformstatistik).

### Anfrage-Lebenszyklus

![Anfrage-Lebenszyklus](diagrams/04-request-lifecycle.svg)

> Weitere Details siehe [Vollständige Diagrammsammlung](diagrams.md) (8 Diagramme, darunter Bestelllebenszyklus, Bereitstellungsarchitektur, Sicherheitsarchitektur, Mehrwährungsabwicklung usw.)

### Sicherheitsarchitektur

![Sicherheitsarchitektur](diagrams/07-security-architecture.svg)

### Mehrwährungs-Abwicklungsfluss

![Mehrwährungs-Abwicklungsfluss](diagrams/08-multi-currency-settlement.svg)

### Erläuterung der Mehrwährungsabwicklung

**Mehrwährungs-Preise:** Produkt-SKUs werden nach `currency_code` je Währung bepreist; bei der Bestellung wird die Zahlungswährung der Bestellung fixiert (USD / EUR / GBP / CNY usw.).

**Wechselkursservice:** Die Wechselkurstabelle `erik_exchange_rates` unterstützt manuelle Pflege sowie automatisches Abrufen über exchangerate-api, versioniert über die Gültigkeitszeit `effective_at`; für die Abrechnung wird eine Kurssnapshot zum Zahlungszeitpunkt verwendet.

**Belastung in Originalwährung:** Stripe / PayPal belasten in der Bestellwährung (Klarna/Adyen sind Platzhalter, nicht angebunden); nach Webhook-Signaturprüfung und Bestätigung des Geldeingangs werden Zahlungs- und Bestellstatus aktualisiert.

**Aufteilung und Abrechnung:** Nach erfolgreicher Zahlung werden automatisch `PlatformSettlements` erzeugt (Bestellsumme + Plattformprovision + Gateway-Gebühr, Buchung in Bestellwährung); Händlerabrechnung `MerchantSettlements` (Bestellbetrag → Provisionssatz → Abrechnungsbetrag), Lieferantenabrechnung `SupplierSettlements`, Auszahlung der Affiliate-Provision `AffiliatePayouts` — vier unabhängige Abrechnungslinien, Status 0 ausstehend / 1 abgerechnet.

**Wechselkurs-Gewinne/-Verluste:** `CurrencyExchangeGainsLosses` verfolgt die Differenz zwischen Einzahlungswährung und Abrechnungswährung, vergleicht den Kurs zum Zahlungszeitpunkt mit dem Kurs zum Abrechnungszeitpunkt; positiv = Wechselkursgewinn, negativ = Wechselkursverlust — unterstützt die mehrwährungsübergreifende Abstimmung und Prüfung im E-Commerce.

## Schnellstart

### Methode 1: Web-basierte Installation (empfohlen)

```bash
# 1. Admin-Abhängigkeiten installieren
cd admin && composer install

# 2. Verwaltungskonsole starten
php start.php start -d

# 3. Installationsassistent im Browser öffnen
# http://127.0.0.1:8788/app/admin/install/step1
# Datenbankinformationen eintragen → Administrator-Konto anlegen → Fertig

# 4. Abhängigkeiten installieren und API starten
cd ../service && composer install && php start.php start -d
```

> Der Installationsassistent erledigt automatisch: Datenbank anlegen → 117 Tabellen importieren → service/.env und admin/.env erzeugen (mit Zufallsschlüsseln) → Administrator anlegen → Dienste neu laden

### Methode 2: Manuelle Installation per Kommandozeile

Siehe [INSTALL.md](../../INSTALL.md)

### Docker-Bereitstellung

```bash
# Umgebungsvariablen konfigurieren
cp .env.example .env  # oder Variablen wie DB_PASS / JWT_SECRET setzen

# Alle Dienste mit einem Befehl starten
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

Siehe [Bereitstellungsdokumentation](deployment.md)

## Verwendung

### Verwaltungskonsole

Öffnen Sie `http://127.0.0.1:8788/app/admin` im Browser und melden Sie sich in der Verwaltungskonsole an (beim ersten Mal wird das Administratorkonto über den Installationsassistenten erstellt):

- **Dashboard**: GMV, Bestellvolumen, Nutzerwachstum und weitere Kernkennzahlen auf einen Blick
- **Berichtszentrum**: Verkaufsübersicht, 30-Tage-Trend, TOP-Produkte, Verteilung nach Zahlungsart/Bestellstatus
- Tägliche Verwaltung von Produkten, Bestellungen, Marketing, Lieferkette und anderen Modulen

### API-Aufrufe

```bash
# Produktliste abrufen
curl http://127.0.0.1:8787/api/products \
  -H "API-Version: 2026-05-20" \
  -H "X-Platform: web"

# Plattformstatistik der Startseite (Benutzer/Produkte/Bestellungen/GMV gesamt und heute neu)
curl http://127.0.0.1:8787/
```

> Die API-Version wird über den `API-Version`-Header übergeben (nicht in der URL); geschützte Endpunkte erfordern `Authorization: Bearer <token>` (JWT).

### Clients

- **Flutter-Client**: `apps/flutter/` (iOS / Android / macOS / Windows / Linux)
- **HarmonyOS-Client**: `apps/harmonyos/` (HarmonyOS NEXT, ArkTS + ArkUI)

## Projektstruktur

```
shop-php/
  install.sql       # Einmaliges Installations-SQL (117 Tabellen), automatisch vom Web-Installationsassistenten importiert
  service/          PHP-Geschäfts-API (webman)          — 39 Controller + 111 Modelle + 14 Middleware
  admin/            Verwaltungskonsole (webman-admin)   — 83 Controller + 76 Modelle + ECharts-Dashboard + Web-Installationsassistent
  apps/flutter/     Flutter-Client                      — 11 Seiten + 5 Sprachen + PC-adaptiv
  apps/harmonyos/   HarmonyOS-Client                    — 9 Seiten + ArkTS
  docker/           Docker-Bereitstellung               — Nginx + PHP + MySQL + Redis + ES
  docs/             Designdokumente
```

## Funktionsumfang

| Dimension | Abgedeckte Inhalte |
|------|---------|
| **B2C-Einzelhandel** | Mehrsprachige Produkte, währungsbezogene Preisgestaltung, SKU, Warenkorb, Bestellung, Zahlung, Rückerstattung, Retoure |
| **B2B-Großhandel** | Staffelpreise (MOQ), Unternehmenszertifizierung (Steuernummer/Gewerbeschein), Preisangebot |
| **Multi-Vendor-Onboarding** | Verkäuferprüfung, Produktprüfung, Umsatzbeteiligung und -aufteilung |
| **Cross-Border-Compliance** | HS-Code-Katalog, Zolltarifregeln, VAT/IOSS, länderspezifische Compliance-Kennzeichnungen (FDA/CE/RoHS) |
| **Internationale Logistik** | Logistik-Zonen-Fracht, Überseelager (Versandlager + Retourenlager), Handelsrechnung/Packliste, HS-Deklaration (in Planung) |
| **Zahlung** | Stripe/PayPal (vollständig), Klarna/Adyen (Platzhalter), BNPL (Platzhalter), 3DS-Verifizierung |
| **Marketing** | Gutscheine (Zonen + Neu-/Bestandskunden), Karussell (regionale Sichtbarkeit), Blitzverkauf, Gruppenkauf, Affiliate (Link + Provision + Auszahlung) |
| **Multi-Plattform** | Amazon/eBay/Shopee/Lazada/Temu-Produktlistungen + Bestellaggregation |
| **Lieferkette** | Lieferantenbewertung, Einkauf → Qualitätsprüfung → Wareneingang, Lagerbestandsprotokoll (unveränderliches Hauptbuch), Umlagerung |
| **Risikomanagement & Compliance** | Regel-Engine (Bypass-Scoring), KYC-Verifizierung, GDPR/CCPA-Datenanfragen, Cookie-Consent |
| **Sicherheitsschutz** | 31 Angriffserkennungstypen (XSS/SQL-Injection/XXE/SSRF/CRLF/Pfad-Traversal/Datei-Upload/Brute-Force/HTTP-Methode/Host/CORS usw.) |
| **Hohe Nebenläufigkeit** | Token-Bucket-Rate-Limiting, DB-Read/Write-Splitting, Verbindungspool-Optimierung |
| **CDN-Unterstützung** | Origin-Pull-Edge-Caching, einheitliche Provider-Abstraktion (Cloudflare/CloudFront/Aliyun/Tencent), Auto-Invalidierung (fail-open), CDN-Verwaltungsseite (Konfiguration/Invalidierung/Protokolle) |
| **Berichtsanalyse** | Berichtszentrum im Admin-Panel: Verkaufsübersicht, 30-Tage-Trend, TOP-Produkte, Zahlungsarten-/Bestellstatus-Verteilung |
| **Plattformstatistik** | Statistiken auf der service-Startseite: Benutzer/Produkte/Bestellungen/GMV gesamt und heute neu |
| **Mitgliederwachstum** | Punktregeln, Mitgliedsstufen-Berechtigungen, Geschenkkarten, Preisalarme, Abonnement-Kauf, AB-Tests |
| **Content-Management** | Mehrsprachige CMS-Seiten, FAQ, Wissensdatenbank, Größentabellen, E-Mail-Vorlagen, Produkt-Feed-Synchronisierung |
| **Kundenservice** | WebSocket-Echtzeit-IM, Wissensdatenbank (Tabellenstruktur vorhanden) |
| **Infrastruktur** | Snowflake-verteilte ID, Hashids-Schnittstellen-Verschleierung, JWT-Authentifizierung, AES-Verschlüsselung, GeoIP-Erkennung |
| **Multi-Endgeräte** | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) + HarmonyOS (ArkTS) + Web Admin |
| **Plattform-Tracking** | 8-Plattform-Erkennung (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + DB-Erfassung |
| **Tests** | 22 Tests / 45 Assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## Kerndesign

- **Snowflake-Primärschlüssel**: Alle 117 Tabellen verwenden die von `erikwang2013/snowflake-php` generierten bigint-IDs
- **Hashids-Schnittstelle**: Middleware kodiert/dekodiert automatisch, Controller bleiben unbeeinflusst
- **Encryptable-Verschlüsselung**: Datenbankebenen-Verschlüsselung sensibler Felder wie email/mobile/address
- **JWT-Authentifizierung**: HS256 + automatisches Refresh der beiden Tokens access/refresh
- **API-Versionierung**: Routing über `API-Version`-Header, nicht in der URL
- **Poster-Verifizierung**: Zufällige Mensch-Maschine-Prüfung bei sensiblen Aktionen (Registrierung/Bestellung/Zahlung)

## Dokumente

| Dokument | Beschreibung |
|------|------|
| [README-EN.md](../../README-EN.md) | Englische Dokumentation |
| [INSTALL.md](../../INSTALL.md) | Installationsanleitung (Web-Ein-Klick-Installation + manuelle Installation) |
| [AUDIT-REPORT.md](../../AUDIT-REPORT.md) | Installations- und Systemprüfbericht |
| [Projektplanung](PLAN.md) | Phasenweise Projektplanung des Teams (4-Phasen-Roadmap + Schlüsselrisiken + Quick Wins) |
| [Team-Recherchedetails](PLAN-RESEARCH.md) | Recherche über 7 Bereiche: Implementiert / Lücken / Risiken / Empfehlungen |
| [Funktionsdesigndokument](features.md) | Vollständige Funktionsmatrix, Geschäftsabläufe, Zustandsautomaten |
| [Diagrammsammlung](diagrams.md) | Architektur-, Fluss-, Funktions-, Lebenszyklus-, Bereitstellungs- und Mehrwährungsdiagramme (8 Mermaid-Diagramme) |
| [Architekturdesigndokument](architecture-full.md) | Systemarchitektur, Middleware-Pipeline, Datenarchitektur, Sicherheitsarchitektur, Zahlungsarchitektur |
| [Designdokument](design.md) | Datenbanktabellen-Design, API-Spezifikation, Sicherheitskonzept, Internationalisierung |
| [Architekturdokument](architecture.md) | Verzeichnisstruktur, Modell-Vererbungskette, Schlüsselpakete |
| [API-Schnittstellendokument](api.md) | 71 API-Endpunkte (statisches Dokument) |
| [hg/apidoc-Schnittstellendokumentation](http://localhost:8787/apidoc/) | Automatisch von hg/apidoc generiert (6 Gruppen: Authentifizierung/Produkte/Transaktionen/Logistik-Zoll/Benutzer-Marketing/Betrieb) |
| [Bereitstellungsdokument](deployment.md) | Docker/manuelle Bereitstellung, Umgebungsvariablen (inkl. `CDN_*`), Betriebsbefehle |


## Open Source ist harte Arbeit — wir freuen uns über Unterstützung

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png "WeChat") | ![Alipay](../../alipay.png "Alipay") |

### Internationale Überweisung (ZA Bank)

**Empfängerinformationen**

- Empfängername: WANG KEXUN
- Empfängerkontonummer: 881015918251

**Empfängerbank**

- SWIFT Code: AABLHKHHXXX
- Bankname: ZA Bank Limited
- Bankleitzahl: 387
- Bankadresse: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Korrespondenzbank für Auslandsüberweisungen (falls erforderlich)**

> Dies sind die Informationen der Korrespondenzbank (Zwischenbank) für Auslandsüberweisungen, nicht die der Empfängerbank. Fragen Sie bei Ihrer überweisenden Bank nach, ob diese Angaben benötigt werden.

- **Einzahlung in Hongkong-Dollar, RMB und US-Dollar** (Korrespondenzbank Citibank):
  - Bankname: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - Bankleitzahl: 006
  - Filialname: Hong Kong Branch
  - Filialnummer: 391
  - Bankadresse: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Einzahlung in anderen Währungen** (Korrespondenzbank BNY Mellon):
  - Bankname: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - Bankadresse: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### Krypto-Spenden (Crypto Donation)

Wenn dieses Projekt Ihnen hilft, scannen Sie gerne den QR-Code, um zu spenden. Vielen Dank!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---


## Tests

```bash
make test             # Empfohlene Methode
cd service && php vendor/bin/phpunit tests/   # Nativer Befehl
# 22 Tests, 45 Assertions — ALL PASS

# Sicherheitsprüfung der Abhängigkeiten (1 bekanntes CVE mit niedrigem Risiko: CVE-2025-45769 firebase/php-jwt <7.0.0,
# durch jwt-webman ^6.0 eingeschränkt, kein Upgrade möglich; die HS256-Symmetric-Signatur-Nutzung ist nicht betroffen)
composer audit
```

## Entwicklungswerkzeuge

```bash
make help             # Alle Befehle anzeigen
make lint             # PHP-Syntaxprüfung
make check            # phpstan statische Analyse
make fix              # php-cs-fixer Codeformatierung
```

CI/CD: `.github/workflows/ci.yml` — PHP 8.3/8.4-Matrix-Tests

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
