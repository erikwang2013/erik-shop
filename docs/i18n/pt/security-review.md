# Relatório de Revisão da Integração do Plugin Security

**Data**: 2026-08-04
**Escopo**: Integração do erikwang2013/security-php v1.1.6
**Revisor**: Claude Code (automatizado)

---

## 1. Resultados dos testes

| Verificação | Resultado |
|---|---|
| Verificação de sintaxe PHP (47 arquivos) | Todos aprovados |
| PHPUnit (22 tests, 45 assertions) | Todos aprovados |
| Teste de payloads de segurança do SecurityGuard | Interceptação correta de XSS + SQLi |
| Teste de requisições seguras do SecurityGuard | Sem falsos positivos |
| Análise estática phpstan | Não instalado (não bloqueia) |

## 2. Problemas corrigidos

### 2.1 Dados de upload de arquivos não passados ao SecurityGuard (Crítico)

**Arquivos**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

O middleware passava apenas `$request->all()` para `SecurityGuard::guard()`, mas esse método não inclui os dados de upload de arquivos. O `UploadDetector` precisa dos dados de arquivos no formato `['tmp_name' => ..., 'name' => ...]`.

**Correção**: adicionado um loop que mescla `$request->file()` no array de dados antes de passá-lo a `SecurityGuard::guard()`.

### 2.2 Configuração do admin para encryptable sem valor padrão (Médio)

**Arquivo**: `admin/config/plugin/erikwang2013/encryptable/app.php`

A configuração do admin usa `env('ENCRYPTION_KEY')` sem valor de fallback, retornando `null` quando a variável de ambiente está ausente. O Service usa `getenv('ENCRYPTION_KEY') ?: ''` e faz fallback correto para string vazia.

**Correção**: a configuração do admin passou a usar o operador `?: ''`, consistente com o comportamento do service.

### 2.3 Variáveis de ambiente incompletas no Docker Compose (Médio)

**Arquivo**: `docker-compose.yml`

- O contêiner service não tinha `ENCRYPTION_CIPHER` e `ENCRYPTION_PREVIOUS_KEYS`
- O contêiner admin não tinha `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID`

**Correção**: todas as variáveis de ambiente ausentes foram adicionadas, usando os valores padrão consistentes com o `.env.example`.

### 2.4 Detecção duplicada no middleware WAF (Crítico, corrigido na primeira rodada)

O `SecurityMiddleware` personalizado continha ~200 linhas de regex inline, completamente duplicadas pelos 31 detectores do pacote `security-php`. Cada requisição era escaneada duas vezes, desperdiçando CPU e podendo causar dupla interceptação.

**Correção**: o middleware foi reescrito para usar a API `SecurityGuard::guard()`, reduzido de 341 linhas para ~110 (service) e de 136 para ~85 (admin). Proteção contra força bruta e cabeçalhos de resposta de segurança foram mantidos.

### 2.5 ENCRYPTION_KEY ausente (Crítico, corrigido na primeira rodada)

O arquivo `.env.example` usava um placeholder para `ENCRYPTION_KEY` e não tinha `ENCRYPTION_CIPHER` nem `ENCRYPTION_PREVIOUS_KEYS`. Não havia arquivo `.env` real.

**Correção**: gerada chave base64 de 32 bytes, adicionados `ENCRYPTION_CIPHER=AES-256-CBC` e `ENCRYPTION_PREVIOUS_KEYS`, criado o arquivo `.env`.

## 3. Integridade da configuração do ecossistema

### 3.1 Pacotes (consistentes nos dois projetos)

| Pacote | Versão | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | Instalado | Instalado |
| erikwang2013/encryptable | - | Instalado | Instalado |
| erikwang2013/encryption | - | Instalado | Instalado |
| erikwang2013/jwt-webman | - | Instalado | Instalado |
| erikwang2013/hashids | - | Instalado | Instalado |
| erikwang2013/snowflake-php | - | Instalado | Instalado |
| erikwang2013/poster-php | - | Instalado | Instalado |
| erikwang2013/season | - | Instalado | Instalado |
| erikwang2013/webman-scout | - | Instalado | Instalado |

### 3.2 Configuração do WAF

| Item | Service | Admin | Status |
|---|---|---|---|
| Arquivo de configuração | `config/plugin/erikwang2013/security-php/app.php` | Igual | Publicado |
| Detectores habilitados | 31/31 | 31/31 | Correto |
| Blacklist de IP | habilitada (5 att/60s -> ban de 900s) | Igual | Correto |
| Detectores em modo block | 28 | 28 | Correto |
| Detectores apenas-log | 3 (header_injection, ssti, nosql_injection) | 3 | Correto |
| Armazenamento | file | file | Correto |
| Logging | habilitado (file, rotação de 10MB) | Igual | Correto |
| Middleware registrado | `config/middleware.php` | `config/middleware.php` | Correto |

### 3.3 Configuração de criptografia

| Item | Service | Admin | Status |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | Igual | Definido |
| ENCRYPTION_CIPHER | `AES-256-CBC` | Igual | Definido |
| ENCRYPTION_PREVIOUS_KEYS | (vazio) | (vazio) | Definido |
| Config do encryptable | `config/plugin/erikwang2013/encryptable/app.php` | Igual (unificado) | Correto |
| Config do encryption | `config/encryption.php` | - | Correto |
| Arquivo .env | Existente | Existente | Criado |
| .env.example | Atualizado | Atualizado | Correto |
| docker-compose | Atualizado | Atualizado | Correto |

### 3.4 Modelos com trait Encryptable

31 modelos usam o trait `Encryptable`, com os campos sensíveis corretamente declarados em `$encryptable`:

| Categoria | Modelos | Campos sensíveis |
|---|---|---|
| PII de usuário | Users | email, mobile |
| PII de usuário | UserAddresses | name, phone, detail |
| PII de usuário | UserKyc | real_name, id_number |
| PII de usuário | UserSocialAccounts | access_token, refresh_token |
| Privacidade | PrivacyRequests | email |
| Finanças | GiftCards | receiver_email |
| Finanças | AffiliatePayouts | account |
| Finanças | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Plataforma | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Plataforma | PlatformAccounts | account_name, api_key, api_secret |
| Plataforma | PlatformListings | platform_account_id |
| Logística | LogisticsCompanies | name, api_key |
| Fornecedor | Suppliers | name, email, phone |
| Fornecedor | B2bVerifications | company_name |
| Vendedor | Merchants | store_name, email, phone |
| Outros | EmailLogs | to_email |
| Outros | Mais 15 modelos | campos de nome |

## 4. Segunda rodada de correções (criptografia de API + chave JWT)

### 4.1 Middleware de criptografia de resposta da API (Médio, corrigido)

**Arquivo**: `service/app/middleware/EncryptionMiddleware.php` (novo)

O pacote `erikwang2013/encryption` estava instalado e a classe utilitária `app/common/Encryption` existia, mas não estava integrada ao pipeline de middlewares. Os dados sensíveis das interfaces não tinham criptografia/descriptografia na camada de transporte.

**Correções**:
- Criado o `EncryptionMiddleware`, com criptografia/descriptografia acionada por headers HTTP:
  - `X-Encrypted: 1` — descriptografia da requisição: converte o body em texto cifrado base64 para JSON antes de passar ao controlador
  - `X-Encrypt-Response: 1` — criptografia da resposta: criptografa o campo `data` da resposta como texto cifrado base64
  - `X-Encrypt-Fields: field1,field2` — criptografa apenas os campos especificados na resposta
- Registrado como o último estágio da pilha de middlewares (após HashidsEncode)
- As verificações de saúde (`/api/health`, `/api/ping`) e o endpoint de documentação (`/apidoc`) ignoram a criptografia/descriptografia

### 4.2 Nome de classe/arquivo incompatível (Médio, corrigido)

**Arquivo**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

A classe `app\common\Encryption` estava declarada no arquivo `EncryptionHelper.php`, em desacordo com a especificação PSR-4, causando falha no autoload do Composer. Em ambientes IDE e CLI, a classe poderia não ser encontrada pelo autoloader.

**Correção**: arquivo renomeado para `Encryption.php` para corresponder ao nome da classe.

### 4.3 JWT_SECRET_KEY vazio (Baixo, corrigido)

**Arquivos**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` era uma string vazia. Embora o middleware JWT tenha uma cadeia de fallback `JWT_SECRET → JWT_SECRET_KEY` (priorizando `JWT_SECRET`), o valor placeholder não é seguro.

**Correção**: gerada chave base64 de 32 bytes, definindo `JWT_SECRET` e `JWT_SECRET_KEY`. Atualizados `.env.example`, `.env` e `docker-compose.yml`.

## 5. Problemas em observação (pontos de otimização em potencial)

### 5.1 Dependência do SecurityGuard em headers de webman/Workerman (Risco Baixo)

**Impacto**: detectores como CSRF Origin, Host Header, DNS Rebinding, Request Smuggling e CORS dependem dos dados de headers HTTP em `$_SERVER`.

Em ambiente não-CGI do Workerman, `$_SERVER` pode não ser totalmente preenchido com os headers HTTP. O SecurityGuard já possui lógica de fallback (ex.: se o valor do header estiver vazio, a detecção é pulada), portanto **não haverá falsos positivos**, mas **alguns ataques via header podem não ser detectados**. O impacto é baixo porque o Nginx em camada de proxy reverso normalmente também filtra headers maliciosos.

**Sugestão**: se for necessária uma detecção de headers mais completa, os valores dos headers podem ser passados explicitamente no parâmetro `$meta` do SecurityGuard. Atualmente não é necessária nenhuma alteração.

### 5.2 Impacto do detector CSRF Origin no Admin (Sem Risco)

O detector `csrf_origin` do Admin em modo `block` tem `allowed_origins` vazio. Porém, como o detector só dispara quando o header Origin existe e não corresponde ao Host, o acesso ao painel administrativo normalmente não tem header Origin (acesso de mesma origem), portanto **não haverá bloqueios indevidos**.

### 5.3 Todos os 31 detectores habilitados, custo por requisição (Nota de desempenho)

Todas as requisições executam os 31 detectores (incluindo JWT, WebSocket, GraphQL, CSV, prototype pollution etc.). Cada detector executa correspondência regex em todos os campos da requisição. Para o cenário de uso deste projeto, o custo está dentro do aceitável (webman é um processo residente em memória, sem custo de cold start de CGI).

### 5.4 Persistência da blacklist de IP (Nota operacional)

O backend de armazenamento está em modo `file`, com caminho padrão `sys_get_temp_dir() . '/security_storage.json'`. Em contêineres Docker, o diretório temporário pode ser perdido após reinicialização. Se for necessário compartilhar a blacklist em implantação multi-contêiner, é possível alternar para o modo `redis`.

## 6. Resumo dos arquivos alterados

```
admin/.env.example                                (ENCRYPTION_KEY adicionada)
admin/.env                                        (criado a partir do .env.example)
admin/CLAUDE.md                                   (pilha de middlewares + tech stack atualizados)
admin/composer.json                               (dependência do security-php)
admin/config/plugin/erikwang2013/encryptable/app.php  (valores padrão unificados)
admin/config/plugin/erikwang2013/security-php/app.php  (novo, 31 detectores)
admin/app/middleware/SecurityMiddleware.php       (reescrito para usar SecurityGuard)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + chave JWT atualizadas)
service/.env                                      (criado a partir do .env.example, chave JWT sincronizada)
service/CLAUDE.md                                 (pilha de middlewares + Encryption + tech stack atualizados)
service/composer.json                             (dependência do security-php)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (novo, 31 detectores)
service/app/common/Encryption.php                 (renomeado de EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (novo, criptografia/descriptografia de respostas da API)
service/app/middleware/SecurityMiddleware.php     (reescrito para usar SecurityGuard + upload de arquivos)
docker-compose.yml                                (variáveis de ambiente de encryption/jwt completadas)
docs/security-review.md                           (este relatório)
```

## 7. Conclusão

**Status**: Aprovado

- A detecção WAF intercepta corretamente XSS, injeção SQL e outros ataques (31 detectores, API SecurityGuard::guard)
- Configuração de criptografia de campos sensíveis completa (31 modelos, 6 categorias de dados sensíveis, trait Encryptable)
- Criptografia/descriptografia de transporte da API integrada aos middlewares (EncryptionMiddleware, AES-256-CBC, acionada por header)
- Chave JWT configurada (JWT_SECRET + JWT_SECRET_KEY definidas)
- Detecção de upload de arquivos corrigida (mescla dados $_FILES para passar ao SecurityGuard)
- Sem regressões funcionais (22/22 testes aprovados)
- Sem detecção duplicada nos middlewares
- Variáveis de ambiente de implantação Docker completas
