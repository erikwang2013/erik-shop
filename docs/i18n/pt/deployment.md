# Plataforma de Comércio Eletrônico Transfronteiriço — Documentação de Implantação

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Implantação Docker (recomendada)

### 1.1 Requisitos de ambiente

- Docker 24.0+ / Docker Compose v2
- Sistema: Linux (recomendado Ubuntu 22.04+)
- Memória: mínimo 4GB, recomendado 8GB+

### 1.2 Passos de implantação

```bash
# 1. Clonar o projeto
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Configurar variáveis de ambiente
cp .env.example .env
# Editar .env e alterar todas as senhas e chaves:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET etc.

# 3. Iniciar todos os serviços
docker compose up -d

# 4. Ver logs
docker compose logs -f service
docker compose logs -f admin

# 5. Acessar
# API: http://localhost/api
# Painel admin: http://admin.localhost
```

### 1.3 Lista de serviços

| Serviço | Porta | Descrição |
|------|------|------|
| nginx | 80, 443 | Proxy reverso |
| service | 8787 (interno) | API de negócio PHP |
| admin | 8788 (interno) | Painel administrativo |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Checklist de produção

- [ ] Todas as chaves no `.env` foram alteradas para valores aleatórios
- [ ] `STRIPE_MODE=live` (ambiente de produção)
- [ ] `APP_ENV=production`
- [ ] `debug` definido como `false` em `config/app.php`
- [ ] Certificado SSL configurado (nginx+Let's Encrypt)
- [ ] Banco de dados importado com o `install.sql` da raiz (117 tabelas, importado automaticamente pelo assistente web)
- [ ] Índice ES criado: `php start.php scout:import "app\model\Products"`
- [ ] Backup configurado para os volumes de dados MySQL/Redis/ES
- [ ] CDN configurado (se usado): `CDN_DOMAIN` com CNAME para o domínio do admin + credenciais do provedor (Cloudflare/CloudFront/Aliyun/Tencent) em `config/cdn.php` / painel admin; volumes `admin_uploads` e `service_public` persistidos

## 2. Implantação manual

### 2.1 Dependências de ambiente

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (opcional, necessário para a função de busca)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# Editar .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# Escuta em: http://0.0.0.0:8787
```

### 2.3 Painel administrativo Admin

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Escuta em: http://0.0.0.0:8787 (a outra porta requer distinção via proxy reverso Nginx)
```

### 2.4 Proxy reverso Nginx

```nginx
# Ver docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
# CDN origin-pull: cache de borda para uploads (imutável, 7 dias)
# location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }
```

## 3. Inicialização do banco de dados

```bash
# Criar banco de dados
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar estrutura das tabelas
mysql -u root -p erik_shop < install.sql

# Importar dados de seed (opcional)
php -r "
require 'vendor/autoload.php';
// Importar dados de seed de países/moedas/HS Code/zonas logísticas etc.
"
```

## 4. Referência de variáveis de ambiente

| Variável | Valor padrão | Descrição |
|------|--------|------|
| APP_ENV | production | Ambiente da aplicação |
| DB_HOST | 127.0.0.1 | Endereço do banco de dados |
| DB_PORT | 3306 | Porta do banco de dados |
| DB_NAME | erik_shop | Nome do banco de dados |
| DB_USER | erik | Usuário do banco de dados |
| DB_PASS | (obrigatório) | Senha do banco de dados |
| REDIS_HOST | 127.0.0.1 | Endereço do Redis |
| JWT_SECRET | (obrigatório) | Chave de assinatura JWT (256bit) |
| HASHIDS_SALT | (obrigatório) | Salt do Hashids |
| ENCRYPTION_KEY | (obrigatório) | Chave de criptografia AES |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Chave Stripe |
| STRIPE_WEBHOOK_SECRET | - | Verificação de assinatura do Webhook Stripe |
| CDN_ENABLED | false | Liga/desliga global do CDN (0/1) |
| CDN_DEFAULT_PROVIDER | cloudflare | Provedor padrão (cloudflare/cloudfront/aliyun/tencent) |
| CDN_DOMAIN | - | Domínio do CDN (ex.: cdn.erik.xyz, CNAME para o domínio do admin) |
| CF_API_TOKEN | - | Token de API do Cloudflare |
| CF_ZONE_ID | - | Zone ID do Cloudflare |
| AWS_ACCESS_KEY_ID | - | Access Key ID da AWS (CloudFront) |
| AWS_SECRET_ACCESS_KEY | - | Secret Access Key da AWS (CloudFront) |
| AWS_REGION | us-east-1 | Região da AWS |
| CLOUDFRONT_DISTRIBUTION_ID | - | Distribution ID do CloudFront |
| ALIYUN_ACCESS_KEY_ID | - | AccessKey ID da Aliyun CDN |
| ALIYUN_ACCESS_KEY_SECRET | - | AccessKey Secret da Aliyun CDN |
| TENCENT_SECRET_ID | - | SecretId da Tencent Cloud CDN |
| TENCENT_SECRET_KEY | - | SecretKey da Tencent Cloud CDN |

## 5. Comandos de operação

```bash
# Service API
cd service
php start.php status        # ver status
php start.php reload        # reinício suave
php start.php stop          # parar

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # ver status dos contêineres
docker compose logs -f      # ver logs
docker compose restart      # reiniciar tudo
docker compose down         # parar
```
