# Erik Shop — Plataforma de Comércio Eletrônico Transfronteiriço Versão Completa (Full)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Versões

> Versão simplificada (open source MIT): `lite` | Versão padrão (comercial): `standard` | Versão completa (comercial): `full`
>
> Contato para licença comercial: **erik@erik.xyz** | Comparação de versões: [docs/VERSIONS.md]

## Idioma / Languages

| Idioma | Link |
|------|------|
| 中文 | [README.md](README.md) |
| English | [docs/i18n/en/README.md](../en/README.md) |
| 한국어 | [docs/i18n/ko/README.md](../ko/README.md) |
| Русский | [docs/i18n/ru/README.md](../ru/README.md) |
| Deutsch | [docs/i18n/de/README.md](../de/README.md) |
| Français | [docs/i18n/fr/README.md](../fr/README.md) |
| Español | [docs/i18n/es/README.md](../es/README.md) |
| Português | [docs/i18n/pt/README.md](../pt/README.md) |
| हिन्दी | [docs/i18n/hi/README.md](../hi/README.md) |
| العربية | [docs/i18n/ar/README.md](../ar/README.md) |
| বাংলা | [docs/i18n/bn/README.md](../bn/README.md) |
| Bahasa Indonesia | [docs/i18n/id/README.md](../id/README.md) |
| 日本語 | [docs/i18n/ja/README.md](../ja/README.md) |

> Este documento é uma tradução automática da documentação original em chinês. Original: [中文原版](../../../README.md).

## Introdução ao Projeto

Plataforma de comércio eletrônico transfronteiriço full-stack construída sobre o ecossistema webman, cobrindo cenários B2C/B2B e integração de vendedores terceirizados.

### Arquitetura Técnica

| Camada | Tecnologia | Diretório |
|------|------|------|
| API de negócio | webman + illuminate/database + erikwang2013/* | `service/` |
| Painel administrativo | webman-admin + LayUI + ECharts | `admin/` |
| Cliente | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| Cliente HarmonyOS | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### Stack Tecnológico

**Servidor:** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**Pacotes principais:** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**Pagamento:** Stripe, PayPal (completos); Klarna, Adyen (placeholder, `PaymentGateway::make` não implementado, ver docs/PLAN.md)
**Clientes:** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## Conjunto de Diagramas de Arquitetura

> Conjunto completo de diagramas e visualização em tamanho grande: [docs/diagrams.md](../../diagrams.md)

### Diagrama de Arquitetura do Sistema

![Diagrama de arquitetura do sistema](./diagrams/01-system-architecture.svg)

### Diagrama de Fluxo de Processamento de Requisições

![Diagrama de fluxo de processamento de requisições](./diagrams/02-request-processing-flow.svg)

### Mapa Geral de Módulos Funcionais

![Mapa geral de módulos funcionais](./diagrams/03-feature-module-map.svg)

### Diagrama de Ciclo de Vida da Requisição

![Diagrama de ciclo de vida da requisição](./diagrams/04-request-lifecycle.svg)

> Mais detalhes em [Conjunto completo de diagramas de arquitetura](../../diagrams.md) (inclui ciclo de vida do pedido, arquitetura de implantação, arquitetura de segurança, liquidação multi-moeda etc., 8 diagramas no total)

### Diagrama de Arquitetura de Segurança

![Diagrama de arquitetura de segurança](./diagrams/07-security-architecture.svg)

### Diagrama de Fluxo de Liquidação Multi-Moeda

![Diagrama de fluxo de liquidação multi-moeda](./diagrams/08-multi-currency-settlement.svg)

### Explicação da Liquidação Multi-Moeda

**Precificação multi-moeda:** os SKUs de produtos são precificados por moeda conforme `currency_code`; ao fazer o pedido, a moeda de recebimento é fixada (USD / EUR / GBP / CNY etc.).

**Serviço de câmbio:** a tabela de câmbio `erik_exchange_rates` suporta manutenção manual e busca automática via exchangerate-api, versionada pelo horário de vigência `effective_at`; na liquidação é usado o snapshot da taxa de câmbio do momento do pagamento.

**Débito na moeda original:** Stripe / PayPal debitam na moeda original do pedido (Klarna/Adyen são placeholders, não integrados); após a confirmação de recebimento via verificação de assinatura do Webhook, os status de pagamento e pedido são atualizados.

**Liquidação por repartição:** após o pagamento bem-sucedido, são gerados automaticamente os repartições de plataforma `PlatformSettlements` (total do pedido + comissão da plataforma + taxa do gateway de pagamento, contabilizados na moeda do pedido); liquidação do vendedor `MerchantSettlements` (valor do pedido → taxa de comissão → valor liquidado), liquidação do fornecedor `SupplierSettlements`, saque de comissões de afiliados `AffiliatePayouts` — quatro linhas independentes de liquidação, status 0 pendente de liquidação / 1 liquidado.

**Ganhos/perdas cambiais:** `CurrencyExchangeGainsLosses` acompanha a diferença entre a moeda de recebimento e a moeda de liquidação, comparando a taxa de câmbio do pagamento com a da liquidação; positivo = ganho cambial, negativo = perda cambial, dando suporte à conciliação e auditoria multi-moeda do comércio transfronteiriço.

## Início Rápido

### Método 1: Instalação Web com um clique (recomendado)

```bash
# 1. Instalar dependências do admin
cd admin && composer install

# 2. Iniciar o painel administrativo
php start.php start -d

# 3. Abrir o assistente de instalação no navegador
# http://127.0.0.1:8788/app/admin/install/step1
# Preencher as informações do banco de dados → definir conta de administrador → concluir

# 4. Instalar dependências e iniciar a API
cd ../service && composer install && php start.php start -d
```

> O assistente de instalação conclui automaticamente: criar banco → importar 117 tabelas → gerar service/.env e admin/.env (com chaves aleatórias) → criar administrador → recarregar serviços

### Método 2: Instalação manual por linha de comando

Consulte [INSTALL.md](../../INSTALL.md)

### Implantação Docker

```bash
# Configurar variáveis de ambiente
cp .env.example .env  # ou definir variáveis como DB_PASS / JWT_SECRET

# Iniciar todos os serviços com um comando
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

Consulte [Documentação de implantação](../../deployment.md)

## Estrutura do Projeto

```
shop-php/
  install.sql       # SQL de instalação com um clique (117 tabelas), importado automaticamente pelo assistente web
  service/          API de negócio PHP (webman)        — 39 controladores + 111 modelos + 14 middlewares
  admin/            Painel administrativo (webman-admin)      — 82 controladores + 76 modelos + dashboard ECharts + assistente de instalação web
  apps/flutter/     Cliente Flutter              — 11 páginas + 5 idiomas + adaptação para PC
  apps/harmonyos/   Cliente HarmonyOS                  — 9 páginas + ArkTS
  docker/           Implantação Docker                  — Nginx + PHP + MySQL + Redis + ES
  docs/             Documentação de design
```

## Cobertura de Funcionalidades

| Dimensão | Conteúdo coberto |
|------|---------|
| **Varejo B2C** | Produtos multi-idioma, precificação por moeda, SKU, carrinho, pedidos, pagamento, reembolso, devolução |
| **Atacado B2B** | Preços escalonados (MOQ), certificação empresarial (nº de contribuinte/licença comercial), consulta de preços |
| **Integração de múltiplos vendedores** | Revisão de vendedores, revisão de produtos, repartição de comissões |
| **Conformidade transfronteiriça** | Biblioteca de códigos HS, regras tarifárias, VAT/IOSS, rótulos de conformidade por país (FDA/CE/RoHS) |
| **Logística internacional** | Frete por zona logística, armazém no exterior (armazém de envio + armazém de devolução), fatura comercial/lista de embalagem, declaração HS (em planejamento) |
| **Pagamento** | Stripe/PayPal (completos), Klarna/Adyen (placeholder), BNPL compre agora pague depois (placeholder), verificação 3DS |
| **Marketing** | Cupons (por zona + cliente novo/antigo), banners (visibilidade por região), vendas relâmpago, compra em grupo, distribuição (link + comissão + saque) |
| **Multi-plataforma** | Listagem de produtos e agregação de pedidos Amazon/eBay/Shopee/Lazada/Temu |
| **Cadeia de suprimentos** | Avaliação de fornecedores, compra→inspeção de qualidade→entrada em estoque, registro de inventário (livro-razão imutável), transferência |
| **Gestão de risco e conformidade** | Motor de regras (pontuação paralela), KYC, solicitações de dados GDPR/CCPA, consentimento de cookies |
| **Proteção de segurança** | Detecção de 31 tipos de ataques (XSS/Injeção SQL/XXE/SSRF/CRLF/Path Traversal/upload de arquivos/força bruta/métodos HTTP/Host/CORS etc.) |
| **Alta concorrência** | Rate limiting com token bucket, separação leitura/escrita do DB, otimização de pool de conexões |
| **Crescimento de membros** | Regras de pontos, benefícios por nível de membro, cartões-presente, alerta de queda de preço, compra por assinatura, testes AB |
| **Gestão de conteúdo** | Páginas CMS multi-idioma, FAQ, base de conhecimento, tabela de tamanhos, modelos de e-mail, sincronização de feeds de produtos |
| **Atendimento ao cliente** | IM em tempo real via WebSocket, base de conhecimento (estrutura de tabelas criada) |
| **Infraestrutura** | ID distribuído Snowflake, ofuscação de interface Hashids, autenticação JWT, criptografia AES, identificação regional GeoIP |
| **Cobertura multi-dispositivo** | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) + HarmonyOS (ArkTS) + Web Admin |
| **Rastreamento de plataforma** | Identificação de origem em 8 plataformas (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + registro em DB |
| **Testes** | 22 testes / 45 assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## Design Principal

- **Chave primária Snowflake**: todas as 117 tabelas usam IDs bigint gerados por `erikwang2013/snowflake-php`
- **Interface Hashids**: o middleware codifica/decodifica automaticamente, os controladores não percebem
- **Criptografia Encryptable**: campos sensíveis como email/mobile/address criptografados no nível do banco de dados
- **Autenticação JWT**: HS256 + refresh automático de token duplo access/refresh
- **Versão da API**: roteamento via header `API-Version`, não na URL
- **Verificação Poster**: verificação humana aleatória em operações sensíveis (registro/pedido/pagamento)

## Documentação

| Documento | Descrição |
|------|------|
| [README-EN.md](../../README-EN.md) | English documentation |
| [INSTALL.md](../../INSTALL.md) | Guia de instalação (instalação web com um clique + instalação manual) |
| [AUDIT-REPORT.md](../../AUDIT-REPORT.md) | Relatório de auditoria do sistema de instalação |
| [Planejamento do projeto](../../PLAN.md) | Planejamento de projeto em fases produzido pela equipe (roadmap de 4 fases + riscos-chave + Quick Wins) |
| [Detalhes da pesquisa da equipe](../../PLAN-RESEARCH.md) | Pesquisa de status em 7 áreas: implementado / lacunas / riscos / recomendações |
| [Documento de design funcional](../../features.md) | Matriz funcional completa, fluxos de negócio, máquina de estados |
| [Conjunto de diagramas de arquitetura](../../diagrams.md) | Diagramas de arquitetura, fluxos, funcionalidades, ciclo de vida, implantação, liquidação multi-moeda (8 diagramas Mermaid) |
| [Documento de design de arquitetura](../../architecture-full.md) | Diagrama de arquitetura do sistema, pipeline de middlewares, arquitetura de dados, arquitetura de segurança, arquitetura de pagamento |
| [Documento de design](../../design.md) | Design de tabelas do banco de dados, especificação de API, solução de segurança, internacionalização |
| [Documento de arquitetura](../../architecture.md) | Estrutura de diretórios, cadeia de herança de modelos, pacotes-chave |
| [Documentação da API](../../api.md) | 71 endpoints de API (documentação estática) |
| [Documentação de interface hg/apidoc](http://localhost:8787/apidoc/) | Gerado automaticamente pelo hg/apidoc (6 grupos: autenticação/produtos/transações/logística e alfândega/marketing de usuários/operações) |
| [Documentação de implantação](../../deployment.md) | Implantação Docker/manual, variáveis de ambiente, comandos de operação |


## Open source não é fácil, agradecemos o apoio

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png "WeChat") | ![Alipay](../../alipay.png "Alipay") |

### Transferência bancária global (ZA Bank)

**Informações do beneficiário**

- Nome do beneficiário: WANG KEXUN
- Número da conta do beneficiário: 881015918251

**Banco do beneficiário**

- Código SWIFT: AABLHKHHXXX
- Nome do banco: ZA Bank Limited
- Número do banco: 387
- Endereço do banco: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banco intermediário para remessas internacionais (se necessário)**

> Estas são as informações do banco intermediário (correspondente) para remessas internacionais, não as do banco do beneficiário. Consulte o banco remetente para saber se é necessário fornecê-las.

- **Para HKD, CNY e USD** (banco intermediário Citibank):
  - Nome do banco: Citibank N.A. Hong Kong
  - Código SWIFT: CITIHKHXXXX
  - Número do banco: 006
  - Nome da agência: Hong Kong Branch
  - Número da agência: 391
  - Endereço do banco: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Para outras moedas** (banco intermediário BNY Mellon):
  - Nome do banco: THE BANK OF NEW YORK MELLON
  - Código SWIFT: IRVTUS3NXXX
  - Endereço do banco: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---


## Testes

```bash
make test             # método recomendado
cd service && php vendor/bin/phpunit tests/   # comando nativo
# 22 tests, 45 assertions — ALL PASS

# Auditoria de segurança de dependências (1 CVE de baixa gravidade conhecido: CVE-2025-45769 firebase/php-jwt <7.0.0,
# bloqueado pela restrição jwt-webman ^6.0, o uso de assinatura simétrica HS256 não é afetado)
composer audit
```

## Ferramentas de Desenvolvimento

```bash
make help             # ver todos os comandos
make lint             # verificação de sintaxe PHP
make check            # análise estática phpstan
make fix              # formatação de código php-cs-fixer
```

CI/CD: `.github/workflows/ci.yml` — testes em matriz PHP 8.3/8.4

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
