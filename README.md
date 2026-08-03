# 🔬 AMB-PESQUISAS — API Central

> Este é o núcleo do ecossistema de Pesquisas Científicas da **Associação Médica Brasileira (AMB)**.

---

## 📌 O que é este projeto?

Este projeto é a **API REST central** que sustenta toda a plataforma de pesquisas da AMB. É por aqui que toda a lógica de negócio acontece: desde a criação e publicação de pesquisas científicas, passando pelo gerenciamento de usuários administrativos, até a coleta e armazenamento das respostas dos médicos participantes.

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
┌─────────────────────┐         ┌────────────────────────────────────┐         ┌──────────────────┐
│  AMB-PESQUISAS-ADMIN │──HTTP──▶│                                    │──ORM───▶│  MySQL Database  │
│      (Vue 3)         │  Bearer │   AMB-PESQUISAS — API (Laravel 13) │         │                  │
│   localhost:5174     │  Token  │         localhost / amb.test        │◀──ORM──│  amb_pesquisas   │
├─────────────────────┤         │                                    │         └──────────────────┘
│  amb-pesquisas-app   │──HTTP──▶│   • Autenticação (Sanctum)         │
│      (Vue 3)         │  Bearer │   • Controllers & Models           │         ┌──────────────────┐
│   localhost:5173     │  Token  │   • Regras de negócio              │──SMTP──▶│   Servidor SMTP  │
└─────────────────────┘         │   • Disparo de e-mails             │         │  (Mailpit local) │
                                └────────────────────────────────────┘         └──────────────────┘
```

---

## 🛠️ Tecnologias

| Tecnologia | Versão | Uso |
|---|---|---|
| PHP | `^8.3` | Runtime |
| Laravel | `^13.8` | Framework principal |
| Laravel Sanctum | `^4.0` | Autenticação via tokens Bearer |
| MySQL | `8.x` | Banco de dados relacional |
| Dedoc Scramble | `^0.13` | Documentação OpenAPI automática |
| Spatie Activity Log | `^4.12` | Registro de atividades |
| Spatie Permission | `^8.0` | Sistema de papéis e permissões |

---

## ✅ Pré-requisitos

Antes de rodar este projeto, certifique-se de ter instalado em sua máquina:

- **PHP** `>= 8.3` com extensões: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- **Composer** `>= 2.x` → [getcomposer.org](https://getcomposer.org)
- **MySQL** `>= 8.x` (ou MariaDB compatível)
- **Node.js** `>= 18.x` + **npm** (necessário para os assets Vite do Laravel)
- **Git**

> **Opcional (recomendado para dev):**
> - [Laragon](https://laragon.org) ou [Laravel Herd](https://herd.laravel.com) para ambiente local
> - [Mailpit](https://mailpit.axllent.org) para testar envio de e-mails localmente

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

Abra o arquivo `.env` e configure as variáveis principais:

```env
# Identificação da aplicação
APP_NAME="AMB Pesquisas"
APP_URL=http://amb-pesquisas.test   # ou http://localhost:8000

# URLs dos frontends (usadas nos links de e-mail)
ADMIN_URL=http://localhost:5174/
RESPONDER_URL=http://localhost:5173/

# Banco de dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amb_pesquisas
DB_USERNAME=root
DB_PASSWORD=

# E-mail (Mailpit para desenvolvimento)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="pesquisas@amb.org.br"
MAIL_FROM_NAME="AMB Pesquisas"
```

### 4. Criar o banco de dados

Acesse seu MySQL e crie o banco de dados:

```sql
CREATE DATABASE amb_pesquisas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Executar as migrations

```bash
php artisan migrate
```

Isso criará todas as tabelas necessárias:
- `specialties` + `user_specialties` + `responder_specialties` + `search_specialties`
- `users` (operadores: master e director)
- `responders` (médicos participantes)
- `searches` + `search_managers`
- `system_invitations` + `search_invitations`
- `search_answers`

### 6. Executar os Seeders (dados iniciais)

```bash
php artisan db:seed
```

> Isso criará um usuário **master** inicial para você acessar o painel administrativo. Verifique as credenciais no seeder correspondente.

### 7. Configurar o link simbólico do storage (upload de imagens)

```bash
php artisan storage:link
```

### 8. Instalar dependências Node e compilar assets

```bash
npm install
npm run build
```

### 9. Iniciar o servidor

#### Opção A — Servidor embutido do PHP (mais simples)

```bash
php artisan serve
```

A API estará disponível em `http://localhost:8000`.

#### Opção B — Modo de desenvolvimento completo (recomendado)

Este comando inicia o servidor, a fila de processamento de jobs, os logs em tempo real e o Vite simultaneamente:

```bash
composer dev
```

> Este comando usa `concurrently` para rodar:
> - `php artisan serve` — servidor web
> - `php artisan queue:listen` — processador de fila (e-mails, jobs)
> - `php artisan pail` — logs em tempo real
> - `npm run dev` — Vite (assets)

#### Opção C — Laragon / Herd (recomendado para produção local)

Se estiver usando Laragon, basta colocar o projeto na pasta `www` e acessar pelo domínio automático `http://amb-pesquisas.test`. Não é necessário rodar `php artisan serve`.

---

## 🗂️ Estrutura de Pastas

```
AMB-PESQUISAS/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/   ← AuthController, SearchController, UserController...
│   │   └── Requests/          ← Validações (StoreUserRequest, StoreSearchRequest...)
│   ├── Mail/                  ← PlatformInvitationMail, CustomInvitationMail
│   └── Models/                ← User, Responder, Search, Specialty, SearchAnswer...
├── database/
│   ├── migrations/            ← 8 migrations
│   ├── factories/             ← Factories para testes
│   └── seeders/               ← Dados iniciais
├── routes/
│   └── api.php                ← Todas as rotas da API REST
└── .env.example               ← Modelo de configuração
```

---

## 🔐 Autenticação

A API usa **Laravel Sanctum** com autenticação polimórfica — dois tipos de conta acessam a mesma rota `/api/login` com comportamentos diferentes:

```
POST /api/login
{
  "email": "...",
  "password": "...",
  "portal": "admin"    ← autentica na tabela 'users' (master/director)
}

POST /api/login
{
  "email": "...",
  "password": "...",
  "portal": "app"      ← autentica na tabela 'responders' (médicos)
}
```

Todas as demais rotas exigem o header:
```
Authorization: Bearer <token_recebido_no_login>
```

---

## 📋 Principais Endpoints

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/login` | Autenticação |
| `GET` | `/api/me` | Dados do usuário logado |
| `POST` | `/api/logout` | Encerrar sessão |
| `GET` | `/api/searches` | Listar pesquisas |
| `POST` | `/api/searches` | Criar pesquisa |
| `GET` | `/api/searches/{id}/stats` | Estatísticas da pesquisa |
| `POST` | `/api/answers` | Submeter resposta |
| `GET` | `/api/my-searches/pending` | Pesquisas pendentes do respondente |
| `GET` | `/api/dashboard/stats` | Métricas do painel admin |

> Para a lista completa de endpoints, acesse a documentação automática gerada pelo Scramble em:
> `http://seu-dominio/docs/api`

---

## ⚡ Comandos Úteis

```bash
# Limpar todos os caches
php artisan optimize:clear

# Recriar o banco do zero (cuidado: apaga tudo!)
php artisan migrate:fresh --seed

# Rodar os testes automatizados
php artisan test
# ou
composer test

# Ver todas as rotas registradas
php artisan route:list

# Processar jobs da fila manualmente
php artisan queue:work
```

---

## 🔗 Projetos Relacionados

- **Painel Administrativo:** [AMB-PESQUISA-ADMIN](https://github.com/victor-amb/AMB-PESQUISA-ADMIN)
- **App de Respondentes:** [AMB-PESQUISA-RESPOSTAS](https://github.com/victor-amb/AMB-PESQUISA-RESPOSTAS)

---

> **Associação Médica Brasileira — Plataforma de Pesquisas Científicas**