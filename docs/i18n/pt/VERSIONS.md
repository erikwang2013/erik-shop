# Erik Shop — Plataforma de Comércio Eletrônico Transfronteiriço
Plataforma full-stack de comércio eletrônico transfronteiriço construída sobre o ecossistema webman, cobrindo cenários B2C/B2B e integração de vendedores terceirizados.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Visão geral das versões

| | Simplificada (Lite) | Padrão (Standard) | Completa (Full) |
|---|:---:|:---:|:---:|
| **Posicionamento** | Desenvolvedores individuais / pequeno e-commerce | Comerciantes transfronteiriços em crescimento | Plataforma full-stack empresarial |
| **Licença** | MIT open source | Licença comercial | Licença comercial |
| **Como obter** | Download público no GitHub | Contato erik@erik.xyz | Contato erik@erik.xyz |
| **Branch** | `lite` | `standard` | `full` |
| **Atual** | — | — | ✅ |

---

## 2026-08-27 Disjuntor e degradação

- Novo disjuntor Redis `CircuitBreaker` (`service/app/common/CircuitBreaker.php`): chamadas externas de gateways de pagamento (Stripe/PayPal/Klarna/Adyen) e login social passam a ser protegidas por disjuntor unificado — 5 falhas consecutivas→disjuntor aberto por 30s, sondagem semiaberta após o TTL com recuperação automática
- Lista branca de exceções de negócio: cartão inválido/token inválido não contam como falha do disjuntor (evita que requisições maliciosas derrubem serviços dependentes)
- Falha do Redis libera a passagem automaticamente (fail-open); durante o disjuntor a interface retorna 503 "Serviço temporariamente indisponível"
- Parâmetros: `config/concurrency.php` → `circuit_breaker` (fail_threshold=5, open_seconds=30)

---

## 2026-08-29 Suporte a CDN

- Novo suporte a CDN nos dois projetos (service + admin), modelo de origem (origin-pull): os uploads continuam sendo salvos no disco local do admin (origem); o banco de dados guarda apenas caminhos relativos (migração zero); nas fronteiras de saída `Cdn::url()` reescreve para `https://{CDN_DOMAIN}{path}`; o domínio do CDN aponta por CNAME de volta para o domínio do admin
- Abstração unificada de provedores `CdnProviderInterface` (purge / purgeByTag / preload) implementada para Cloudflare, AWS CloudFront, Aliyun e Tencent Cloud (Fastly/Akamai reservados); matriz de capacidades: purge 4/4, preload 2/4 (Aliyun/Tencent), purgeByTag 1/4 (Cloudflare)
- Configuração pelo painel admin: página de gerenciamento de CDN (3 abas: Configuração/Depuração/Logs) — chaves de ativação por provedor, credenciais (JSON de configuração criptografado em repouso), teste de conectividade, depuração/pré-aquecimento manuais, logs de depuração (tabelas `wa_cdn_providers` / `wa_cdn_purge_logs`); a configuração no banco de dados sobrepõe o `.env`; o liga/desliga global propaga para o service via Redis compartilhado (prefixo `shop:`, TTL 60s)
- Depuração automática (fail-open): o CRUD de produtos e banners aciona a depuração automaticamente; falha do CDN nunca bloqueia o CRUD do admin
- Cache de borda: nginx `location /app/admin/upload/` com `expires 7d; Cache-Control public, max-age=604800, immutable`; os diretórios de upload persistem via volumes docker (`admin_uploads:/app/plugin/admin/public/upload`, `service_public:/app/public/documents`)
- Configuração: `config/cdn.php` (admin + service) + 13 variáveis de ambiente `CDN_*` (CDN_ENABLED / CDN_DEFAULT_PROVIDER / CDN_DOMAIN / credenciais por provedor)

---

## Registro de correções 2026-08-07

| # | Problema | Severidade | Correção |
|---|------|--------|------|
| 1 | Criptografia de resposta da API não integrada aos middlewares | Médio | Criado o EncryptionMiddleware (acionado pelo header X-Encrypt-Response), registrado como 10º nível do pipeline do service |
| 2 | Nome de classe Encryption / nome de arquivo EncryptionHelper.php incompatíveis | Médio | Renomeado para Encryption.php, corrigido o autoload PSR-4 |
| 3 | JWT_SECRET_KEY vazio | Baixo | Gerada chave de 32 bytes, definidos JWT_SECRET e JWT_SECRET_KEY |
| 4 | config/middleware.php como array indexado causava crash de todos os workers por "Bad middleware config" | Crítico | Alterado para a estrutura padrão `'' => [...]` (webman exige appName => lista) |
| 5 | Config do plugin security-php sem a chave enable, ignorada silenciosamente pelo Config::loadFromDir | Crítico | Adicionado `'enable' => true` ao app.php do plugin em service/admin |
| 6 | config/bootstrap.php referenciava support\bootstrap\Db/Redis inexistentes | Crítico | Removido; a inicialização do Eloquent passou a ser feita pelo support/bootstrap.php com require do Db.php do vendor/webman/database |
| 7 | Função global redis() inexistente (webman 2.x não tem essa função), limitação de taxa/risco falhava silenciosamente | Alto | Criada a facade support\Redis (illuminate/redis + phpredis), função auxiliar redis() registrada em app/functions.php |
| 8 | Parâmetros do construtor RedisManager ausentes (requer 3 parâmetros: contêiner app/driver/config) | Alto | Passado contêiner stdClass como placeholder + driver phpredis + configuração de conexão |
| 9 | Modelos referenciam trait Erik\Encryptable\Encryptable inexistente (o pacote contém CastsAttributes no namespace Maize\Encryptable) | Crítico | Criada camada de compatibilidade do trait clássico em service/Erik/Encryptable/Encryptable.php (reutiliza Encryption::php do pacote internamente) |
| 10 | Declaração duplicada de função de nível superior no Installer.php do plugin composer causava fatal | Médio | Guarda de idempotência function_exists (ambos os vendors de service/admin corrigidos) |
| 11 | getHeader() do HashidsEncode retornava string causando erro no implode | Alto | Cast (array) |
| 12 | docker-compose/.env.example com chaves reais de JWT/criptografia hardcoded | Crítico | Substituídas por placeholders change_me, assistente de instalação gera chaves aleatórias |
| 13 | Criação de pedido sem transação, decremento de estoque não atômico (sobrevenda em concorrência) | Crítico | Db::transaction + decremento atômico condicional |
| 14 | Emissão/uso excessivo de cupons em concorrência | Alto | Transação + lockForUpdate de linha + trava atômica received_qty |
| 15 | Campos de verificação de assinatura do PayPal Webhook sempre vazios (verify-webhook-signature falhava sempre) | Alto | Cinco campos de verificação repassados a partir do header da requisição |
| 16 | Injeção SQL no assistente de instalação (concatenação de nome de banco/senha) | Alto | quote + escape de crases + escrita de config com var_export |
| 17 | Degradação silenciosa quando chaves de criptografia/hash ausentes | Alto | Encryption/HashidsHelper lançam exceção em valores vazios ou com comprimento inválido |
| 18 | Exportação de pedidos com nome de arquivo fixo sobrescrita em concorrência | Médio | Nome de arquivo uniqid + limpeza no shutdown + try/catch |
| 19 | Decodificação Hashids não reescrita nos parâmetros da requisição (parâmetros de rota/GET/POST) | Alto | Escrita de volta via setParams/setGet/setPost |
| 20 | composer.lock no gitignore (build não reproduzível) | Médio | Removido da ignorância, incluído no controle de versão |
| 21 | Contêineres sem healthcheck, sem dependências de inicialização | Médio | healthcheck em todos os serviços + condition em depends_on |
| 22 | Dockerfile do admin não executável | Alto | Adicionados COPY + composer install + EXPOSE + CMD |
| 23 | Erros de compilação Flutter (conflito intl/construtores genéricos/parênteses extras) + teste com Timer pendente | Alto | intl ^0.20.2, factories estáticas, pump para avançar o relógio |
| 24 | 27 erros de compilação ArkTS no HarmonyOS impedindo o empacotamento | Alto | Interfaces explícitas, renomeação de palavras reservadas, build de raiz única, imports @kit, configuração hvigor |

---

## Comparação de funcionalidades

> Nota: ◐ = estrutura de tabelas criada, negócio a implementar (atualmente apenas tabelas de dados e modelos, sem código de API/negócio ou implementação parcial)

### Sistema de usuários

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Registro/login por e-mail (JWT) | ✅ | ✅ | ✅ |
| Login social (Google/Apple/Facebook) | — | ✅ | ✅ |
| Gerenciamento de endereços | ✅ | ✅ | ✅ |
| Níveis de membro + pontos | — | — | ◐ |
| Cartões-presente | — | — | ✅ |
| Verificação KYC | — | — | ✅ |

### Sistema de produtos

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gerenciamento de categorias (árvore) | ✅ | ✅ | ✅ |
| SKU + atributos | ✅ | ✅ | ✅ |
| Imagens de produtos | ✅ | ✅ | ✅ |
| Conteúdo multi-idioma | — | ✅ | ✅ |
| Precificação independente por moeda | — | ✅ | ✅ |
| Avaliações de produtos | ✅ | ✅ | ✅ |
| Rótulos de conformidade (FDA/CE/RoHS) | — | ✅ | ✅ |
| Busca multilíngue ES | — | ✅ | ✅ |
| Sincronização de Feed de produtos (Google/Meta) | — | — | ✅ |
| Tabela de tamanhos | — | — | ✅ |

### Sistema de transações

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Carrinho | ✅ | ✅ | ✅ |
| Gerenciamento de pedidos | ✅ | ✅ | ✅ |
| Pagamento (Stripe) | ✅ | ✅ | ✅ |
| Pagamento (PayPal) | ✅ | ✅ | ✅ |
| Pagamento (Klarna/Adyen) | — | placeholder | placeholder |
| BNPL compre agora pague depois | — | placeholder | placeholder |
| Reembolso | ✅ | ✅ | ✅ |
| Gerenciamento de devoluções | — | ✅ | ✅ |
| Fatura comercial/lista de embalagem | — | ✅ | ✅ |
| Seguro logístico | — | — | ◐ |

### Logística transfronteiriça

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gerenciamento de transportadoras internacionais | — | ✅ | ✅ |
| Zonas logísticas + tarifas escalonadas | — | ✅ | ✅ |
| Armazém no exterior (envio + devolução) | — | ✅ | ✅ |
| Declaração HS | — | Em planejamento | Em planejamento |
| Rastreamento logístico | — | ✅ | ✅ |
| Gerenciamento de estoque multi-armazém | — | — | ✅ |

### Alfândega e impostos

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Biblioteca de códigos HS | — | ✅ | ✅ |
| Configuração de regras tarifárias | — | ✅ | ✅ |
| Configurações VAT/IOSS | — | ✅ | ✅ |
| Restrições de conformidade por país | — | ✅ | ✅ |
| Exibição de preço em conformidade (com/sem impostos) | — | ✅ | ✅ |

### Ferramentas de marketing

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cupons | ✅ | ✅ | ✅ |
| Banners | ✅ | ✅ | ✅ |
| Vendas relâmpago | — | ✅ | ✅ |
| Compra em grupo | — | ✅ | ✅ |
| Distribuição (link + comissão + saque) | — | ✅ | ✅ |
| Promoções regionais | — | ✅ | ✅ |

### Cadeia de suprimentos

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gerenciamento de fornecedores | — | — | ✅ |
| Ordens de compra | — | — | ◐ |
| Inspeção de qualidade (portões de entrada/saída) | — | — | ◐ |
| Registro de inventário (livro-razão imutável) | — | — | ✅ |
| Transferência de estoque | — | — | ◐ |

### Expansão de plataforma

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gerenciamento de múltiplas lojas | — | — | ✅ |
| Integração de múltiplos vendedores (terceiros) | — | — | ✅ |
| Publicação Amazon/eBay/Shopee | — | — | ✅ |
| Agregação de pedidos multi-plataforma | — | — | ✅ |
| Atacado B2B (preços escalonados/consulta) | — | — | ✅ |

### Gestão de risco e conformidade

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Detecção básica de ataques (XSS/SQLi) | ✅ | ✅ | ✅ |
| Detecção estendida de ataques (XXE/SSRF etc.) | — | — | ✅ |
| Verificação humano PosterVerify | — | ✅ | ✅ |
| Motor de regras de risco | — | — | ✅ |
| Solicitações de dados GDPR/CCPA | — | — | ✅ |
| Gerenciamento de Cookie Consent | — | — | ✅ |
| Rastreamento de origem da plataforma | — | ✅ | ✅ |
| Rastreamento de origem da plataforma (8 plataformas) | — | ✅ | ✅ |

### Alta concorrência

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| Pool de conexões DB | ✅ | ✅ | ✅ |
| Limitação por token bucket | — | — | ✅ |
| Separação leitura/escrita do DB | — | — | ✅ |
| Tarefas agendadas Cron (11) | — | — | ✅ |
| CDN (Cloudflare/CloudFront/Aliyun/Tencent, origin-pull) | — | — | ✅ |

### Conteúdo e crescimento

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Notificações do sistema | ✅ | ✅ | ✅ |
| Modelos de e-mail | — | — | ✅ |
| Páginas CMS multi-idioma | — | — | ✅ |
| FAQ + base de conhecimento | — | — | ◐ |
| Compra por assinatura | — | — | ✅ |
| Testes AB | — | — | ◐ |
| Atendimento em tempo real (IM WebSocket) | — | — | ✅ |

### Clientes

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| Internacionalização (traduções de 5 idiomas) | ✅ | ✅ | ✅ |
| Documentação da API (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Dashboard ECharts do Admin | ✅ | ✅ | ✅ |
| Exportação Excel/PDF do Admin | ✅ | ✅ | ✅ |
| Interface multi-idioma (5 idiomas) | ✅ | ✅ | ✅ |

---

## Comparação de design

### Banco de dados

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Tabelas de dados | **23** | **62** | **110** |
| Relacionadas a usuários | 3 | 5 | 7 |
| Relacionadas a produtos | 6 | 15 | 19 |
| Relacionadas a transações | 6 | 9 | 9 |
| Relacionadas a logística | 0 | 7 | 9 |
| Relacionadas a alfândega | 0 | 5 | 5 |
| Relacionadas a marketing | 4 | 8 | 8 |
| Cadeia de suprimentos | 0 | 0 | 5 |
| Gestão de risco e conformidade | 0 | 0 | 5 |
| Multi-plataforma | 0 | 0 | 9 |
| Conteúdo e crescimento | 0 | 1 | 14 |
| Atendimento/AB/API | 0 | 0 | 5 |

### Pipeline de middlewares

```
Lite:      Cors → Security (4 tipos) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security (4 tipos) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security (31 tipos) → RateLimit (token bucket) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption (criptografia da interface)
```

### Escala do código

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Modelos do Service | 26 | 55 | 111 |
| Controladores do Service | 15 | 24 | 39 |
| Middlewares do Service | 7 | 9+2 | 12+2 |
| Classes utilitárias do Service | 5 | 5 | 15 |
| Modelos do Admin | 15 | 34 | 76 |
| Controladores do Admin | 15 | 27 | 82 |
| Páginas Flutter | 11 | 11 | 11 |
| HarmonyOS | — | — | 9 páginas |
| Testes PHPUnit | 22 | 22 | 54 |

### Stack tecnológico

| Componente | Lite | Standard | Full |
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

## Caminho de atualização

```
Lite (open source) ──→ Standard (comercial) ──→ Full (comercial)

Como atualizar:
  1. Contate erik@erik.xyz para obter o código da versão correspondente
  2. Importe o schema incremental (lite→standard adiciona ~40 tabelas, standard→Full adiciona ~48 tabelas)
  3. Copie controladores/modelos/middlewares da versão correspondente
  4. composer require dos novos pacotes de dependência
```

---

## Como obter

| Versão | Forma |
|------|------|
| **Simplificada (Lite)** | Open source no GitHub [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php), branch `lite` |
| **Padrão (Standard)** | Licença comercial — contato **erik@erik.xyz** |
| **Completa (Full)** | Licença comercial — contato **erik@erik.xyz** |

A licença comercial inclui: código-fonte completo / suporte de implantação / atualizações prioritárias / consultoria técnica
