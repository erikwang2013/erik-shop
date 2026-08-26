# Plataforma de Comércio Eletrônico Transfronteiriço — Visão Geral da Arquitetura

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Stack tecnológico

| Camada | Tecnologia | Versão |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| Cliente | Flutter (5 plataformas) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| Banco de dados | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| Pagamento | Stripe / PayPal / Klarna / Adyen | — |

## 2. Estrutura de diretórios

```
shop-php/
  service/           API de negócio (251 arquivos PHP)
    config/            35 configurações (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39 controladores (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 modelos (BaseModel + 110 modelos de negócio)
    app/middleware/     14 middlewares (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 classes utilitárias (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (substituído pelo install.sql da raiz) + seeders
    tests/              4 classes de teste (22 tests, 45 assertions)
  admin/             Painel administrativo (239 arquivos PHP)
    plugin/admin/app/controller/shop/ 82 controladores
    plugin/admin/app/model/shop/      76 modelos
    plugin/admin/app/view/shop/       dashboards ECharts
    app/middleware/    5 middlewares (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              Clientes
    flutter/lib/      25 Dart (11 telas + camada principal + rotas)
    harmonyos/        14 ArkTS (9 telas + cliente de API + estado global)
  docs/               5 documentos de design
  .claude/skills/     38 Skills de padrões de desenvolvimento
```

## 3. Pipeline de middlewares

```
Service: Cors → Security (detecção de 31 tipos de ataque) → RateLimit (limitação token bucket) → Platform (identificação de 8 plataformas)
        → GeoIp (região) → Locale (idioma) → HashidsDecode → VersionRoute
        → (PosterVerify verificação humano) → (JwtAuth Token) → HashidsEncode → Encryption (criptografia da interface)

Admin:  Security → Platform → HashidsDecode → AccessControl (RBAC embutido) → HashidsEncode
```

## 4. Segurança

- **Detecção de 31 tipos de ataques**: XSS/Injeção SQL/Injeção de comandos/CRLF/Path Traversal/Body/ContentType/upload de arquivos/força bruta/XXE/SSRF/desserialização/LDAP/cabeçalho de e-mail/SSTI/NoSQL/open redirect/ataques JWT/Host/request smuggling/GraphQL/XPATH/Log4Shell/SSI/fórmula CSV/vazamento de dados/prototype pollution/WebSocket/CORS/DNS rebinding/métodos HTTP/CSRF Origin
- **Criptografia em três camadas**: camada de interface (AES-256-CBC) + camada de banco de dados (trait Encryptable) + ofuscação de ID (Hashids)
- **Rastreamento de plataforma**: 8 plataformas (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + header X-Platform + registro em 6 tabelas

## 5. Alta concorrência

- **Limitação de taxa**: token bucket com janela deslizante (Redis ZSET), regras para 6 endpoints
- **DB**: separação leitura/escrita (2 réplicas de leitura + sticky) + pool de conexões (50/10)
- **Operações lentas**: tratadas por processos Cron independentes (sincronização de Feed/cálculo de recomendações/conciliação de pagamentos/liquidação de repartições etc.)

## 6. Testes

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+vazamento de dados
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. Implantação

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# Manual
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **Multilíngue (i18n)**: arquivos de tradução de 5 idiomas + LocaleMiddleware + AppLocalizations Flutter
- **Documentação da API**: gerada automaticamente por hg/apidoc (6 grupos, orientada por anotações nos controladores)
- **Rastreamento de plataforma**: 8 plataformas, header X-Platform + registro em DB

Consulte: [Documentação de implantação](deployment.md) | [Documento de arquitetura completo](architecture-full.md) | [Documento de design funcional](features.md)
