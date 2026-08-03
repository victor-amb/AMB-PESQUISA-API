# 🔬 AMB-PESQUISAS — API Central

> O núcleo do ecossistema de Pesquisas Científicas da **Associação Médica Brasileira (AMB)**.

---

## 📌 O que é este projeto?

Este é a **API REST central** que sustenta toda a plataforma de pesquisas da AMB. É por aqui que toda a lógica de negócio acontece: desde a criação e publicação de pesquisas científicas, passando pelo gerenciamento de usuários administrativos, até a coleta e armazenamento das respostas dos médicos participantes.

Tanto o **Painel Administrativo** quanto o **App de Respondentes** se comunicam **exclusivamente** com esta API. Nenhum dos dois frontends acessa o banco de dados diretamente — tudo passa por aqui.

---

## 🧭 Arquitetura do Ecossistema

O sistema é composto por **três projetos** que trabalham em conjunto:

| Projeto | Repositório | Função |
|---|---|---|
| **AMB-PESQUISAS** *(este projeto)* | [AMB-PESQUISA-API](https://github.com/victor-amb/AMB-PESQUISA-API) | API REST (back-end) |
| **AMB-PESQUISAS-ADMIN** | [AMB-PESQUISA-ADMIN](https://github.com/victor-amb/AMB-PESQUISA-ADMIN) | Painel administrativo (Vue 3) |
| **amb-pesquisas-app** | [AMB-PESQUISA-RESPOSTAS](https://github.com/victor-amb/AMB-PESQUISA-RESPOSTAS) | App de respondentes (Vue 3) |

```
┌──────────────────────────┐        ┌─────────────────────────────────────┐       ┌──────────────────┐
│   AMB-PESQUISAS-ADMIN    │        │                                     │       │  MySQL Database  │
│        (Vue 3)           │──────▶ │   AMB-PESQUISAS — API (Laravel 13)  │◀────▶ │  amb_pesquisas   │
│   localhost:8011         │        │       http://amb-pesquisa.test       │       └──────────────────┘
├──────────────────────────┤ HTTP + │                                     │
│    amb-pesquisas-app     │ Bearer │   • Autenticação (Sanctum)          │       ┌──────────────────┐
│        (Vue 3)           │ Token  │   • Controllers, Models, Regras     │──────▶│   SMTP (Mailpit) │
│   localhost:8012         │        │   • Disparo de e-mails              │       └──────────────────┘
└──────────────────────────┘        └─────────────────────────────────────┘
```

---

## 🛠️ Tecnologias

| Tecnologia | Versão | Uso |
|---|---|---|
| PHP | `^8.3` | Runtime |
| Laravel | `^13.8` | Framework principal |
| Laravel Sanctum | `^4.0` | Autenticação via tokens Bearer |
| MySQL | `8.x` | Banco de dados relacional |
| Dedoc Scramble | `^0.13.29` | Documentação OpenAPI automática |
| Spatie Activity Log | `^4.12` | Registro de atividades |
| Spatie Permission | `^8.0` | Sistema de papéis e permissões |

---

## ✅ Pré-requisitos

Antes de rodar este projeto, certifique-se de ter instalado:

- **PHP** `>= 8.3` com as extensões: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- **Composer** `>= 2.x` → [getcomposer.org](https://getcomposer.org)
- **MySQL** `>= 8.x`
- **Node.js** `>= 18.x` + **npm** (necessário para compilar os assets do Laravel via Vite)
- **Git**

> **Recomendado para desenvolvimento local:**
> - [Laragon](https://laragon.org) para criar domínios locais automaticamente (ex: `amb-pesquisa.test`)
> - [Mailpit](https://mailpit.axllent.org) para interceptar os e-mails enviados pela API em ambiente local

---

## 🚀 Passo a Passo — Como Rodar

### 1. Clonar o repositório

```bash
git clone https://github.com/victor-amb/AMB-PESQUISA-API.git
cd AMB-PESQUISA-API
```

### 2. Instalar as dependências PHP

```bash
composer install
```

### 3. Configurar o arquivo de ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Abra o `.env` e ajuste as variáveis essenciais:

```env
# URL desta API (conforme seu ambiente local)
APP_URL=http://amb-pesquisa.test

# URLs dos frontends — usadas nos links dos e-mails enviados
ADMIN_URL=http://localhost:8011/
RESPONDER_URL=http://localhost:8012/

# Banco de dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amb_pesquisas
DB_USERNAME=root
DB_PASSWORD=

# E-mail (Mailpit local para desenvolvimento)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="pesquisas@amb.org.br"
MAIL_FROM_NAME="AMB Pesquisas"
```

### 4. Criar o banco de dados

Acesse seu MySQL e crie o banco:

```sql
CREATE DATABASE amb_pesquisas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Executar as migrations

```bash
php artisan migrate
```

Isso cria todas as tabelas: `specialties`, `users`, `user_specialties`, `responders`, `responder_specialties`, `searches`, `search_specialties`, `search_managers`, `system_invitations`, `search_invitations`, `search_answers`.

### 6. Popular o banco com dados iniciais (Seeders)

```bash
php artisan db:seed
```

O seeder cria automaticamente usuários de teste com **senha padrão `123`**:

| Tipo | E-mail | Senha |
|---|---|---|
| Master | `master@amb.org.br` | `123` |
| Master | `master2@amb.org.br` | `123` |
| Director (Pediatria) | `dir.pediatria@amb.org.br` | `123` |
| Director (Cardiologia) | `dir.cardio@amb.org.br` | `123` |
| Director (Multi) | `dir.multi@amb.org.br` | `123` |

> O seeder também popula **55 especialidades médicas** e cria respondentes de exemplo.

### 7. Configurar o link simbólico do storage

Necessário para que uploads de imagens (logos de especialidades) fiquem acessíveis via URL:

```bash
php artisan storage:link
```

### 8. Instalar dependências Node e compilar assets

```bash
npm install
npm run build
```

### 9. Iniciar o servidor

**Opção A — Servidor embutido do PHP (mais simples):**

```bash
php artisan serve
```

A API estará disponível em `http://localhost:8000`.

**Opção B — Modo completo de desenvolvimento (recomendado):**

Inicia simultaneamente o servidor, a fila de e-mails, os logs em tempo real e o Vite:

```bash
composer dev
```

**Opção C — Laragon (recomendado para ambiente local estável):**

Se usar Laragon, coloque o projeto na pasta `www` e ele ficará acessível automaticamente em `http://amb-pesquisa.test` sem necessidade de rodar `php artisan serve`.

---

## 🔐 Autenticação Polimórfica

A API usa **Laravel Sanctum** com dois modelos autenticáveis distintos — ambos acessam a mesma rota `/api/login`, mas o parâmetro `portal` define qual tabela será consultada:

```json
// Autenticação do painel administrativo (tabela: users)
POST /api/login
{ "email": "master@amb.org.br", "password": "123", "portal": "admin" }

// Autenticação do app de respondentes (tabela: responders)
POST /api/login
{ "email": "medico@email.com", "password": "senha", "portal": "app" }
```

Todas as rotas protegidas exigem o header:
```
Authorization: Bearer <token>
```

Há também uma rota pública de health check:
```
GET /api/  →  { "status": "ok", "service": "AMB Pesquisas API" }
```

---

## 📋 Principais Endpoints

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/` | Health check da API |
| `POST` | `/api/login` | Autenticação (admin ou app) |
| `GET` | `/api/me` | Dados do usuário autenticado |
| `POST` | `/api/logout` | Encerrar sessão e revogar token |
| `PUT` | `/api/profile` | Atualizar perfil próprio |
| `GET` | `/api/searches` | Listar pesquisas (com filtros) |
| `POST` | `/api/searches` | Criar pesquisa |
| `GET` | `/api/searches/{id}/stats` | Estatísticas da pesquisa |
| `GET` | `/api/searches/{id}/export-answers` | Exportar respostas |
| `POST` | `/api/answers` | Submeter/salvar resposta |
| `GET` | `/api/my-searches/pending` | Pesquisas pendentes (respondente) |
| `GET` | `/api/my-searches/answered` | Pesquisas concluídas (respondente) |
| `GET` | `/api/dashboard/stats` | Métricas do painel administrativo |

> A documentação completa e interativa de todos os endpoints é gerada automaticamente pelo **Scramble** em: `http://amb-pesquisa.test/docs/api`

---

## ⚡ Comandos Úteis

```bash
# Recriar o banco do zero e repopular (⚠️ apaga tudo!)
php artisan migrate:fresh --seed

# Limpar todos os caches da aplicação
php artisan optimize:clear

# Ver todas as rotas registradas
php artisan route:list

# Processar a fila de jobs (e-mails) manualmente
php artisan queue:work

# Rodar os testes automatizados
composer test

# Instalar, migrar e compilar em um único comando
composer setup
```

---

## 🗂️ Estrutura de Pastas

```
AMB-PESQUISAS/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/    ← AuthController, SearchController, UserController...
│   │   └── Requests/           ← Validações (StoreUserRequest, StoreSearchRequest...)
│   ├── Mail/                   ← PlatformInvitationMail, CustomInvitationMail
│   └── Models/                 ← User, Responder, Search, Specialty, SearchAnswer...
├── database/
│   ├── migrations/             ← 8 migrations (criação completa do schema)
│   ├── factories/
│   └── seeders/                ← SpecialtySeeder (55 especialidades) + UserSeeder
├── routes/
│   └── api.php                 ← Todas as rotas da API REST
└── .env.example                ← Modelo de configuração
```

---

## 🔗 Projetos Relacionados

- **Painel Administrativo:** [AMB-PESQUISA-ADMIN](https://github.com/victor-amb/AMB-PESQUISA-ADMIN)
- **App de Respondentes:** [AMB-PESQUISA-RESPOSTAS](https://github.com/victor-amb/AMB-PESQUISA-RESPOSTAS)

---

> **Associação Médica Brasileira — Plataforma de Pesquisas Científicas**