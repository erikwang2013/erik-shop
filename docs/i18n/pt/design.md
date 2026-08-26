# Plataforma de Comércio Eletrônico Transfronteiriço — Documento de Design

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Design do banco de dados

### 1.1 Convenção de nomenclatura

- Prefixo de tabela: `erik_`
- Chave primária: `id BIGINT UNSIGNED NOT NULL` (gerada por snowflake, não auto-incremento)
- Timestamps: `created_at`, `updated_at`, `deleted_at` (soft delete)
- Engine: InnoDB, charset: utf8mb4_unicode_ci

### 1.2 Divisão de módulos (110 tabelas)

| Módulo | Nº de tabelas | Tabelas principais |
|------|------|--------|
| Usuários e contas | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Produtos e categorias | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Transações | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Pagamento e fundos | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Logística | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Alfândega e impostos | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Marketing | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Cadeia de suprimentos | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Gestão de risco e conformidade | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Multi-plataforma | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Conteúdo e experiência | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Assinaturas e B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Atendimento ao cliente | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| Testes AB | 3 | ab_tests, ab_test_variants, ab_test_results |
| Governança de API | 2 | api_rate_limits, api_docs |
| Dados básicos | 3 | countries, currencies, exchange_rates |

### 1.3 Campos de rastreamento de plataforma

| Tabela | Campo | Descrição |
|----|------|------|
| orders | platform VARCHAR(16) | Plataforma do pedido |
| payments | platform VARCHAR(16) | Plataforma do pagamento |
| operation_logs | platform VARCHAR(16) | Plataforma da operação |
| users | last_login_platform VARCHAR(16) | Plataforma do último login |
| search_logs | platform VARCHAR(16) | Plataforma da busca |
| chat_messages | platform VARCHAR(16) | Origem da mensagem |

---

## 2. Design da API

Controle de versão da API, pipeline de middlewares, estatísticas de endpoints e padrão de resposta unificado, consulte [Documentação da API](api.md).

---

## 3. Design de segurança

### 3.1 SecurityMiddleware encapsula os 31 detectores do security-php

| # | Tipo | Código de erro | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | Injeção SQL | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Path Traversal | 40004 | ✅ | ✅ |
| 5 | Body muito grande | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | Upload de arquivos | 40009 | ✅ | ✅ |
| 8 | Cabeçalhos de resposta de segurança | — | ✅ | ✅ |
| 9 | Força bruta | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | Métodos HTTP | 40012 | ✅ | ✅ |
| 13 | Cabeçalho Host | 40013 | ✅ | — |
| 14 | Mascaramento de dados sensíveis | — | ✅ | ✅ |
| 15 | Whitelist CORS | — | ⚠️ | ⚠️ |

### 3.2 Criptografia em três camadas

| Camada | Tecnologia | Pacote |
|------|------|-----|
| Camada de transporte | AES-256-CBC | erikwang2013/encryption |
| Camada de banco de dados | Trait Encryptable | erikwang2013/encryptable (Maize) |
| Ofuscação de ID | Hashids | erikwang2013/hashids |

---

## 4. Design de alta concorrência

### 4.1 Limitação de taxa

Token bucket com janela deslizante (Redis ZSET, via facade `support\Redis`): padrão 60s/100 vezes, login 10 vezes/60s, registro 5 vezes/300s, login social 5 vezes/300s, pagamento 5 vezes/60s, pedido 3 vezes/10s, busca 10 vezes/1s

### 4.2 Usos do Redis

O Redis é usado para token bucket de limitação de taxa (facade `support\Redis`), códigos de verificação humano e armazenamento de Session; os dados de negócio não têm cache em nível de aplicação, são lidos diretamente do MySQL (separação leitura/escrita + pool de conexões).

### 4.3 Pool de conexões

MySQL: 50max/10min/2s de timeout | Separação leitura/escrita: 30max/5min (2 réplicas de leitura, sticky=true) | Redis: 30max/5min



---

## 5. Internacionalização

- Interface: zh_CN, zh_HK, en, ja, ko
- Conteúdo: erik_product_translations com linhas independentes por locale
- Preço: erik_product_sku_prices com precificação independente por moeda
- Headers: Accept-Language + API-Version

## 6. Documentação da API

Gerada automaticamente por hg/apidoc a partir das anotações dos controladores, consulte [Documentação da API](api.md). Após iniciar, acesse `/apidoc/`.

## 7. Testes

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

Consulte: [Documento de design funcional](features.md) | [Documento de arquitetura completo](architecture-full.md) | [Documentação de implantação](deployment.md)
