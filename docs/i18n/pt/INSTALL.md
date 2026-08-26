# Plataforma de Comércio Eletrônico Transfronteiriço — Guia de Instalação

> Cross-border E-Commerce Platform Installation Guide
>
> [README em português](../../../README.md) | [English README](../../README-EN.md) | [Relatório de auditoria](../../AUDIT-REPORT.md)

---

## Requisitos de ambiente / Requirements

| Componente | Versão mínima | Versão recomendada |
|----------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (opcional/optional) |

### Extensões PHP

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Métodos de instalação / Installation Methods

### Método 1 (recomendado): Assistente de instalação web com um clique

Acesse a página de instalação pelo navegador, preencha as informações do banco de dados e a conta de administrador — **criação de tabelas, configuração e criação do administrador totalmente automáticas**.

```bash
# 1. Instalar dependências
cd admin/
composer install

# 2. Iniciar o painel administrativo
php start.php start

# 3. Acessar pelo navegador (na primeira vez redireciona automaticamente para a página de instalação)
# http://127.0.0.1:8788/app/admin/install/step1
```

O assistente de instalação conclui **automaticamente**:
- Cria o banco de dados MySQL (se não existir)
- Importa todas as 117 tabelas do `install.sql` (7 `wa_` + 110 `erik_`)
- Importa o menu do painel administrativo
- Gera `plugin/admin/config/database.php` e `thinkorm.php`
- Gera `service/.env` (com chaves JWT/Hashids/criptografia geradas aleatoriamente)
- Cria a conta de super administrador
- Envia o sinal SIGUSR1 para acionar a recarga dos serviços

> Após a instalação, ainda é necessário iniciar o serviço API de service/ (ver passo 5 abaixo).

---

### Método 2: Instalação manual / Manual Installation

<details>
<summary>Adequado para implantação por linha de comando ou ambiente com banco de dados existente</summary>

### 1. Criar o banco de dados

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Importar o banco de dados

```bash
mysql -u root -p shop_db < install.sql
```

> O `install.sql` contém **117 tabelas** e dados de seed padrão.

### 3. Configurar service/.env

```bash
cd service/
cp .env.example .env
# Editar o .env com os parâmetros reais de banco/Redis/JWT etc.
```

**Itens de configuração principais:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<chave aleatória de 32 bytes>
HASHIDS_SALT=<sal aleatório>
ENCRYPTION_KEY=<chave aleatória de 32 bytes>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. Configurar admin/

```bash
cd admin/
cp .env.example .env
# Editar o .env com as mesmas informações de banco do service
```

### 5. Criar a conta de administrador

```sql
-- A senha precisa ser gerada com bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Super Administrador', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Método 3: Implantação Docker / Docker Deployment

```bash
# 1. Configurar variáveis de ambiente
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Iniciar todos os serviços
docker-compose up -d

# 3. Executar o assistente de instalação web
# http://localhost/app/admin/install/step1
```

Serviços Docker: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Iniciar serviços / Start Services

```bash
# Instalar dependências (necessário nos dois projetos)
cd service/ && composer install
cd admin/ && composer install

# Iniciar o serviço de API
cd service/
php start.php start -d

# Iniciar o painel administrativo
cd admin/
php start.php start -d
```

| Serviço | Porta padrão | Como verificar |
|----------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Painel administrativo | 8788 | Acessar `http://127.0.0.1:8788/app/admin` no navegador |

### Importar dados de seed (opcional) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # países/regiões
php start.php seed:currencies    # moedas
php start.php seed:hs_codes      # códigos HS Code
php start.php seed:compliance    # categorias de conformidade
```

---

## Estrutura de diretórios / Directory Structure

```
shop-php/
├── install.sql              # SQL de instalação completo consolidado
├── admin/                   # Painel administrativo (webman-admin + LayUI)
│   ├── config/database.php  # Configuração do banco de dados
│   ├── plugin/admin/        # Plugin webman-admin
│   └── start.php
├── service/                 # Serviço de API (webman RESTful)
│   ├── config/              # Arquivos de configuração
│   ├── database/schema.sql  # SQL das tabelas de negócio originais (substituído por install.sql)
│   ├── database/seeders/    # Dados de seed
│   └── start.php
```

---

## Visão geral do esquema do banco de dados / Database Schema Overview

| Módulo | Prefixo de tabela | Nº de tabelas | Descrição |
|------|--------|--------|------|
| Sistema do painel administrativo | `wa_` | 7 | Administradores/papéis/permissões/configurações/anexos |
| Usuários e contas | `erik_users_*` | 7 | Usuários/endereços/social/KYC/favoritos/membros |
| Produtos e categorias | `erik_product_*` | 16 | Produtos/SKU/multi-idioma/multi-moeda/avaliações/conformidade/HS |
| Carrinho e pedidos | `erik_order_*` | 9 | Carrinho/pedidos/pagamento/reembolso/devolução/desembaraço aduaneiro |
| Países/moedas/logística | `erik_shipping_*` | 11 | Países/moedas/câmbio/logística/zonas/armazéns/estoque |
| Alfândega e impostos | `erik_hs_*` | 5 | Códigos HS/tarifas/VAT/restrições de conformidade |
| Pagamento e fundos | `erik_payment_*` | 6 | Gateways de pagamento/repartição da plataforma/liquidação de fornecedores/ganhos e perdas cambiais |
| Marketing | `erik_coupon_*` | 9 | Cupons/vendas relâmpago/compra em grupo/distribuição |
| Cadeia de suprimentos | `erik_supplier_*` | 7 | Fornecedores/compras/inspeção de qualidade |
| Gestão de risco e conformidade | `erik_risk_*` | 6 | Regras de risco/GDPR/Cookies/privacidade |
| Multi-plataforma | `erik_platform_*` | 8 | Multi-lojas/contas de plataforma/listagens/vendedores |
| Conteúdo e experiência | `erik_*` | 12 | CMS/Feed/tamanhos/notificações/e-mail/busca/logs de operação |
| Assinaturas/pontos etc. | `erik_*` | 7 | Assinaturas/pontos/cartões-presente/B2B |
| Testes AB/API/configurações | `erik_*` | 7 | Testes AB/rate limit/documentação de API/configurações do sistema |

---

## Problemas comuns / Troubleshooting

### Erro do MySQL "Specified key was too long"

```sql
-- Garantir o uso de utf8mb4 + InnoDB com innodb_large_prefix habilitado
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Conflito de porta / Port Conflict

Altere `APP_PORT` em `admin/.env` ou `service/.env`.

### Falha na conexão Redis

Verifique se a extensão Redis está instalada e o serviço Redis iniciado:
```bash
redis-cli ping  # deve retornar PONG
```

### Conflito de IDs Snowflake

Se vários servidores instanciarem ao mesmo tempo, garanta que o `SNOWFLAKE_WORKER_ID` de cada servidor seja diferente (0-31).

---

## Referência rápida de comandos de desenvolvimento / Development Commands

```bash
# service/ (API)
php start.php start          # iniciar
php start.php start -d       # daemon
php start.php reload         # recarga a quente
php start.php stop           # parar
php start.php status         # status

# admin/ (painel administrativo)
php start.php start
php start.php start -d
php start.php reload
```
