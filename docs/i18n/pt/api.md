# Plataforma de Comércio Eletrônico Transfronteiriço — Documentação da API

> Documentação dinâmica: após iniciar o Service, acesse http://localhost:8787/apidoc/ (gerado automaticamente pelo hg/apidoc)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Convenções gerais

### Formato de requisição

| Item | Descrição |
|------|------|
| Base URL | `http://localhost:8787/api` |
| Versionamento | header `API-Version: 2026-05-20` (não na URL) |
| Autenticação | header `Authorization: Bearer <token>` |
| Idioma | header `Accept-Language: zh_CN|zh_HK|en|ja|ko` |
| Plataforma | header `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` |
| Content-Type | `application/json` (POST/PUT) |
| Verificação humana | header `X-Poster-Token: <token>` (operações sensíveis) |

### Formato de resposta

```json
// Sucesso
{"code": 0, "msg": "ok", "data": {}}

// Falha
{"code": 1, "msg": "mensagem de erro", "data": null}

// Paginação
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// Códigos de erro
// 40001 Ataque XSS  40002 Injeção SQL  40003 Injeção CRLF  40004 Path Traversal
// 40005 Corpo da requisição grande demais  40006 Content-Type incorreto  40008 Força bruta
// 40009 Upload de arquivo violado  40010 Injeção XXE  40011 Ataque SSRF
// 40012 Método HTTP incorreto  40013 Header Host incorreto
// 401 Não autenticado  403 Acesso proibido  422 Falha de validação de parâmetros  429 Requisições frequentes demais  503 Serviço temporariamente indisponível (disjuntor/degradação)
```

### Sobre IDs

Todos os campos de ID nas interfaces são strings codificadas em hashids (ex.: `Ab3xK9pq`), codificadas/decodificadas automaticamente pelo middleware. O frontend não precisa tratá-las manualmente.

### URLs de recursos (CDN)

Os URLs de recursos (imagens de produtos, banners, documentos) são armazenados no banco como caminhos relativos e, nas fronteiras de saída, reescritos por `Cdn::url()` para `https://{CDN_DOMAIN}{path}` quando o CDN está habilitado (`CDN_ENABLED=true`); desabilitado, retornam o caminho relativo original. O domínio do CDN aponta por CNAME de volta para o domínio do admin (modelo origin-pull) e os arquivos são servidos com cache imutável (7 dias) em `/app/admin/upload/`.

---

## 1. Interfaces de autenticação

### 1.1 Registro `POST /api/auth/register`

> Requer verificação humana `X-Poster-Token`

**Requisição:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**Resposta:**
```json
{
  "code": 0, "msg": "registro bem-sucedido",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 Login `POST /api/auth/login`

**Requisição:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Resposta:**
```json
{
  "code": 0, "msg": "login bem-sucedido",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "level": 1,
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.3 Atualizar Token `POST /api/auth/refresh`

**Requisição:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**Resposta:**
```json
{
  "code": 0, "msg": "Token atualizado",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 Login social `POST /api/auth/social`

**Requisição:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**Resposta:**
```json
{
  "code": 0, "msg": "login bem-sucedido",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. Interfaces de produtos

### 2.1 Lista de produtos `GET /api/products`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| page | int | Não | Número da página (padrão 1) |
| per_page | int | Não | Itens por página (padrão 20, máximo 100) |
| category_id | string | Não | ID da categoria (hashid, inclui subcategorias) |
| keyword | string | Não | Palavra-chave de busca |
| sort | string | Não | Ordenação: default/price_asc/price_desc/sales/newest |
| min_price | number | Não | Preço mínimo |
| max_price | number | Não | Preço máximo |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Ab3xK9pq",
        "title": "Product Title",
        "subtitle": "Subtitle",
        "main_image": "https://img.example.com/p1.jpg",
        "brand": "BrandName",
        "min_price": 29.99,
        "max_price": 49.99,
        "status": 2,
        "is_hot": true,
        "is_new": false,
        "sales_count": 1000
      }
    ],
    "total": 100, "page": 1, "per_page": 20
  }
}
```

### 2.2 Detalhes do produto `GET /api/products/{id}`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| currency | string | Não | Código da moeda (padrão USD) |
| dest_country | string | Não | País de destino ISO2 (padrão US) |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (correspondência multi-idioma)",
    "subtitle": "Subtitle",
    "description": "Full description...",
    "brand": "BrandName",
    "main_image": "https://img.example.com/p1.jpg",
    "min_price": 29.99,
    "max_price": 49.99,
    "weight": 500,
    "unit": "piece",
    "status": 2,
    "is_hot": true,
    "is_new": false,
    "sales_count": 1000,
    "view_count": 5000,
    "skus": [
      {
        "id": "Cd4yL8rq",
        "sku_code": "SKU-RED-M",
        "attrs": {"color": "Red", "size": "M"},
        "default_price": 29.99,
        "stock": 100,
        "image": "https://img.example.com/sku1.jpg",
        "display_price": {
          "tax_exclusive": 29.99,
          "tax_inclusive": 35.99,
          "vat_amount": 6.00,
          "vat_rate": 20,
          "currency": "USD",
          "display_mode": "tax_exclusive"
        }
      }
    ],
    "images": [
      {"id": "Ef5zM9ns", "url": "https://img.example.com/p1.jpg", "is_main": true}
    ],
    "compliance_info": [
      {"category": "marcação CE", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 Avaliações de produto `GET /api/reviews/{productId}`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| page | int | Não | Número da página |
| per_page | int | Não | Por página (padrão 10) |
| rating | int | Não | Filtro por nota (1-5) |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Re1v2W3x",
        "user_id": "Ab3xK9pq",
        "product_id": "Ab3xK9pq",
        "rating": 5,
        "content": "Great product!",
        "images": ["https://img.example.com/review1.jpg"],
        "is_anonymous": false,
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 50, "page": 1, "per_page": 10
  }
}
```

---

## 3. Interfaces de categorias

### 3.1 Lista de categorias `GET /api/categories`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| parent_id | int | Não | ID da categoria pai (0=raiz) |

### 3.2 Árvore de categorias `GET /api/categories/tree`

Retorna a árvore de categorias totalmente aninhada.

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ct1g2H3i",
      "parent_id": 0,
      "name": "Clothing",
      "slug": "clothing",
      "icon": "icon-url",
      "level": 1,
      "is_hot": true,
      "children": [
        {
          "id": "Ct4j5K6l",
          "parent_id": "Ct1g2H3i",
          "name": "Dresses", "slug": "dresses",
          "level": 2, "is_hot": false,
          "children": []
        }
      ]
    }
  ]
}
```

---

## 4. Interfaces de carrinho `[JWT]`

### 4.1 Lista do carrinho `GET /api/cart`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| currency | string | Não | Moeda (padrão USD) |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ca1r2T3s",
      "sku_id": "Cd4yL8rq",
      "product_id": "Ab3xK9pq",
      "title": "Product Title",
      "image": "https://img.example.com/sku1.jpg",
      "attrs": {"color":"Red","size":"M"},
      "price": 29.99,
      "currency": "USD",
      "quantity": 2,
      "selected": true,
      "stock": 100
    }
  ]
}
```

### 4.2 Adicionar ao carrinho `POST /api/cart`

**Requisição:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 Atualizar quantidade `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 remove automaticamente

### 4.4 Excluir `DELETE /api/cart/{id}`

---

## 5. Interfaces de pedidos `[JWT]`

### 5.1 Lista de pedidos `GET /api/orders`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| status | int | Não | Filtro de status:0 aguardando pagamento/1 pago/2 enviado/3 recebido/4 concluído/5 cancelado/6 em reembolso/7 reembolsado/8 aguardando revisão |
| page | int | Não | Número da página (padrão 1) |
| per_page | int | Não | Por página (padrão 10) |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "pago",
        "total_amount": 59.98, "pay_amount": 59.98,
        "currency_code": "USD",
        "created_at": "2026-05-21 10:30:00",
        "paid_at": "2026-05-21 10:31:00"
      }
    ],
    "total": 10, "page": 1, "per_page": 10
  }
}
```

### 5.2 Detalhes do pedido `GET /api/orders/{id}`

Retorna informações completas do pedido, incluindo items/logs/documents.

### 5.3 Criar pedido `POST /api/orders` `[PosterVerify]`

**Requisição:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**Resposta:**
```json
{
  "code": 0, "msg": "pedido criado com sucesso",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 Cancelar pedido `POST /api/orders/{id}/cancel`

> Somente status=0 (aguardando pagamento) pode ser cancelado

### 5.5 Fatura comercial `GET /api/orders/{id}/documents/invoice`

Retorna o link de download do arquivo PDF.

### 5.6 Lista de embalagem `GET /api/orders/{id}/documents/packing-list`

---

## 6. Interfaces de pagamento `[JWT]`

### 6.1 Formas de pagamento disponíveis `GET /api/payment/methods`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| country | string | Não | ISO2 (padrão US) |
| currency | string | Não | Moeda (padrão USD) |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "Cartão de crédito/débito",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna compre agora pague depois",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 Criar pagamento `POST /api/payment/create` `[PosterVerify]`

**Requisição:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**Resposta:**
```json
{
  "code": 0, "msg": "pagamento criado com sucesso",
  "data": {
    "payment_id": "Pa1y2M3t",
    "order_no": "ORD20260521A1B2C3D4",
    "amount": 59.98,
    "currency": "USD",
    "gateway": "stripe",
    "method": "card",
    "client_secret": "pi_3Nxxxx_secret_xxxx",
    "txn_id": "pi_3Nxxxxxxxxxxxx"
  }
}
```

### 6.3 Status do pagamento `GET /api/payment/status/{id}`

### 6.4 Callback Webhook `POST /webhook/payment/{gateway}`

> Sem JWT. Chamado assincronamente pelo gateway de pagamento. Requer verificação de assinatura.

---

## 7. Interfaces de logística

### 7.1 Cálculo de frete `GET /api/shipping/calculate`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| dest_country_id | int | Sim | ID do país de destino (snowflake) |
| weight | int | Não | Peso (gramas) (padrão 500) |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "Zona América do Norte",
    "weight_kg": 0.5,
    "dest_country": "US",
    "options": [
      {
        "logistics_name": "DHL Express",
        "logistics_code": "DHL",
        "fee": 25.50,
        "estimated_days": "3-5",
        "tracking_url": "https://www.dhl.com/track?num="
      }
    ]
  }
}
```

---

## 8. Interfaces de tarifas

### 8.1 Estimativa de tarifas `GET /api/tariff/estimate`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| product_id | string | Sim | ID do produto (hashid) |
| dest_country_id | int | Sim | ID do país de destino |
| declared_value | number | Sim | Valor declarado |

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "Apenas referência; o valor real é determinado pela alfândega"
  }
}
```

---

## 9. Interfaces de devolução `[JWT]`

### 9.1 Lista de devoluções `GET /api/returns`

### 9.2 Solicitar devolução `POST /api/returns`

**Requisição:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 Etiqueta de devolução `GET /api/returns/{id}/label`

---

## 10. Interfaces de usuário `[JWT]`

### 10.1 Perfil pessoal `GET /api/user/profile`

### 10.2 Atualizar informações `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 Lista de endereços `GET /api/user/addresses`

### 10.4 Adicionar endereço `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "Casa"
}
```

### 10.5 Atualizar endereço `PUT /api/user/addresses/{id}`

### 10.6 Excluir endereço `DELETE /api/user/addresses/{id}`

### 10.7 Idioma e moeda `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. Interfaces de marketing

### 11.1 Banners `GET /api/banners?position=home`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| position | string | Não | Posição: home/category/product |

### 11.2 Cupons disponíveis `GET /api/coupons` `[JWT]`

### 11.3 Resgatar cupom `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 Lista de vendas relâmpago `GET /api/flash-sales`

### 11.5 Lista de compras em grupo `GET /api/group-buys`

### 11.6 Links de afiliado `GET /api/affiliate/links` `[JWT]`

### 11.7 Comissões de afiliado `GET /api/affiliate/commissions` `[JWT]`

---

## 12. Interfaces de membros `[JWT]`

### 12.1 Informações de membro `GET /api/membership`

**Resposta:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "current_level": {"id": "Lv1", "name": "Gold", "level": 2},
    "current_benefits": [{"benefit_type": "discount", "benefit_value": "5%"}],
    "all_levels": [],
    "current_score": 1500
  }
}
```

### 12.2 Histórico de pontos `GET /api/points`

---

## 13. Outras interfaces

### 13.1 Dados de países `GET /api/countries`

Retorna todos os países/moedas/câmbio/valores padrão disponíveis.

### 13.2 Configurações públicas `GET /api/settings?group=general`

### 13.3 Busca ES `GET /api/search?keyword=xxx`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| keyword | string | Sim | Termo de busca |
| category_id | string | Não | Filtro de categoria |
| page | int | Não | Número da página |

### 13.4 Comparação de produtos `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE requer o id do registro de comparação: `DELETE /api/comparisons/{id}` (`{id}` é o ID do registro de comparação, obrigatório)

### 13.5 Recomendações personalizadas `GET /api/recommendations` `[JWT]`

### 13.6 Alertas de queda de preço `GET/POST /api/price-alerts` `[JWT]`

### 13.7 Lista de desejos `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 Notificações `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 Páginas CMS `GET /api/cms/{slug}`

### 13.11 Tabela de tamanhos `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 Verificação de conformidade `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 Detecção GeoIP `GET /api/geoip/detect`

### 13.14 Publicar avaliação `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 Saldo do cartão-presente `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 Resgatar cartão-presente `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 Solicitações GDPR `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 Exportar pedidos `GET /api/export/orders` `[JWT]`

| Parâmetro | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| date_from | string | Não | Data inicial (YYYY-MM-DD) |
| date_to | string | Não | Data final |

Retorna download de arquivo CSV.

### 13.19 Consulta de preços B2B `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 Health check `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## Apêndice: Tabela de códigos de status

### Status do pedido

| Valor | Descrição |
|----|------|
| 0 | Aguardando pagamento |
| 1 | Pago |
| 2 | Enviado |
| 3 | Recebido |
| 4 | Concluído |
| 5 | Cancelado |
| 6 | Em reembolso |
| 7 | Reembolsado |
| 8 | Aguardando revisão (risco) |

### Status do produto

| Valor | Descrição |
|----|------|
| 0 | Rascunho |
| 1 | Aguardando revisão |
| 2 | Publicado |
| 3 | Despublicado |

### Status do pagamento

| Valor | Descrição |
|----|------|
| 0 | Aguardando pagamento |
| 1 | Pago |
| 2 | Reembolsado |
| 3 | Falha |

### Modo de exibição de preço por país

| Valor | Descrição |
|----|------|
| tax_inclusive | Preço com impostos (UE/Reino Unido) |
| tax_exclusive | Preço sem impostos (US/CA) |
| both | Exibição paralela (JP) |

---

## Apêndice: Pipeline de middlewares

```
Requisição → Cors → Security(31 tipos) → RateLimit(token bucket) → Platform(8 plataformas)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → Controller
```

Legenda: `[JWT]` requer autenticação | `[PosterVerify]` requer verificação humana | sem marcação = interface pública

---

## Apêndice: Visão geral das estatísticas de endpoints

### A.1 Interfaces públicas (23 endpoints)

| Método | Caminho | Descrição |
|------|------|------|
| POST | /api/auth/register | Registro (PosterVerify) |
| POST | /api/auth/login | Login |
| POST | /api/auth/refresh | Atualizar Token |
| POST | /api/auth/social | Login social |
| GET | /api/products | Lista de produtos (paginação+filtros+ordenação) |
| GET | /api/products/{id} | Detalhes do produto (multi-idioma+multi-moeda+conformidade+HS) |
| GET | /api/categories | Lista de categorias |
| GET | /api/categories/tree | Árvore de categorias |
| GET | /api/banners | Banners (por posição+região) |
| GET | /api/countries | Lista de países/moedas/câmbio |
| GET | /api/search | Busca multi-idioma ES |
| GET | /api/reviews/{productId} | Lista de avaliações do produto |
| GET | /api/flash-sales | Vendas relâmpago atuais |
| GET | /api/group-buys | Compras em grupo atuais |
| GET | /api/faq | FAQ (por idioma+categoria) |
| GET | /api/cms/{slug} | Páginas CMS |
| GET | /api/settings | Configurações públicas |
| GET | /api/size-charts | Tabela de tamanhos |
| GET | /api/tariff/estimate | Estimativa de tarifas |
| GET | /api/shipping/calculate | Cálculo de frete |
| GET | /api/payment/methods | Formas de pagamento disponíveis |
| GET | /api/geoip/detect | Detecção GeoIP |
| GET | /api/compliance/check | Verificação de conformidade |

### A.2 Interfaces autenticadas (47 endpoints)

| Método | Caminho | Descrição |
|------|------|------|
| GET/PUT | /api/user/profile | Perfil pessoal |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | CRUD de endereços |
| PUT | /api/user/locale | Atualizar idioma/moeda |
| GET/POST | /api/wishlist[/{id}] | Lista de desejos |
| GET/POST | /api/price-alerts | Alertas de queda de preço |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | Carrinho |
| GET/POST | /api/orders | Lista/criação de pedidos (PosterVerify) |
| GET | /api/orders/{id} | Detalhes do pedido |
| POST | /api/orders/{id}/cancel | Cancelar pedido |
| GET | /api/orders/{id}/documents/invoice | Fatura comercial |
| GET | /api/orders/{id}/documents/packing-list | Lista de embalagem |
| POST | /api/payment/create | Criar pagamento (PosterVerify) |
| GET | /api/payment/status/{id} | Status do pagamento |
| GET/POST | /api/returns[/{id}] | Devoluções |
| GET | /api/returns/{id}/label | Etiqueta de devolução |
| POST | /api/reviews | Publicar avaliação |
| GET/POST | /api/coupons[/{id}/claim] | Cupons |
| GET/PUT | /api/notifications[/{id}/read] | Notificações |
| GET/POST/DELETE | /api/comparisons[/{id}] | Comparação de produtos |
| GET | /api/recommendations | Recomendações personalizadas |
| GET | /api/affiliate/links | Links de afiliado |
| GET | /api/affiliate/commissions | Comissões de afiliado |
| GET | /api/membership | Nível de membro |
| GET | /api/points | Histórico de pontos |
| GET/POST | /api/gift-cards | Cartões-presente |
| GET/POST | /api/b2b/quotes | Consulta de preços B2B |
| GET/POST | /api/privacy/request | Solicitações GDPR |
| GET | /api/export/orders | Exportar pedidos |

### A.3 Webhook (1 endpoint)

| Método | Caminho | Descrição |
|------|------|------|
| POST | /webhook/payment/{gateway} | Notificação assíncrona de pagamento (verificação de assinatura) |

### A.4 Admin e health check (2 endpoints)

| Método | Caminho | Descrição |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | Execução de reembolso do painel |
| GET | /health | Health check |

---

## Apêndice: Especificação de design da API

### Versionamento

A versão é transmitida pelo header `API-Version: 2026-05-20`, não na URL. Mapeada pelo middleware VersionRoute.

### Pipeline de middlewares

```
Cors → Security(31 tipos) → RateLimit(janela deslizante) → Platform(8 plataformas) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### Estatísticas de endpoints

- Interfaces públicas: 23 (autenticação/produtos/categorias/conteúdo/busca/serviços)
- Interfaces autenticadas: 47 (usuário/carrinho/pedidos/pagamento/devoluções/avaliações/marketing)
- Webhook: 1 (callback de pagamento)
- Admin: 1 (execução de reembolso)
- Health: 1 (/health health check)

### Resposta unificada

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### Documentação dinâmica hg/apidoc

Gerada automaticamente pelo hg/apidoc com base nas anotações dos controladores. Acesse `/apidoc/` após iniciar.

Exemplo de anotação:
```php
/**
 * @Apidoc\Title("Login de usuário")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
