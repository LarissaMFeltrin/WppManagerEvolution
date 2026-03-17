# WPP Manager

Plataforma de gerenciamento de múltiplas contas WhatsApp com painel de atendimento em tempo real.

## Stack Tecnológica

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | AdminLTE + Vue.js + Tailwind CSS |
| WhatsApp | Evolution API v2.3.7 + Baileys Service (opcional) |
| Banco de Dados | MySQL/MariaDB |
| Tempo Real | Laravel Reverb (WebSockets) |
| Fila | Database (recomendado Redis em produção) |
| Build | Vite |

## Funcionalidades

- Painel de atendimento multi-chat (até 8 conversas simultâneas)
- Envio/recebimento de texto, imagens, vídeos, áudios, documentos e stickers
- Suporte a grupos WhatsApp
- Resposta com citação de mensagens
- Importação de histórico WhatsApp (TXT/ZIP com mídias)
- Sistema de fila de atendimento (aguardando / em atendimento / finalizada)
- Atualizações em tempo real via WebSocket (Laravel Reverb)
- Gerenciamento de contatos e grupos
- Conexão via QR Code ou Pairing Code
- Download e armazenamento local de mídias
- Resolução automática de LID (identificador interno do WhatsApp)

## Pré-requisitos

- PHP 8.2+ com extensões: bcmath, curl, mbstring, xml, zip, pdo_mysql, gd
- Composer 2.x
- Node.js 18+ / NPM 9+
- MySQL 8.0+ ou MariaDB 10.6+
- Docker e Docker Compose (para Evolution API)

## Instalação

### 1. Clonar e instalar dependências

```bash
git clone https://github.com/LarissaMFeltrin/WppManagerEvolution.git
cd WppManagerEvolution

composer install
npm install
npm run build
```

### 2. Configurar ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Edite o `.env` com suas configurações:

```env
# Banco de Dados
DB_DATABASE=wpp_manager
DB_USERNAME=root
DB_PASSWORD=sua_senha

# Evolution API
EVOLUTION_API_URL=http://127.0.0.1:8085
EVOLUTION_API_KEY=sua_chave_api

# WebSockets
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=562224
REVERB_APP_KEY=sua_key
REVERB_APP_SECRET=seu_secret
REVERB_HOST=localhost
REVERB_PORT=6001
REVERB_SCHEME=http

# Baileys Service (opcional)
BAILEYS_SERVICE_URL=http://localhost:3001
BAILEYS_SERVICE_ENABLED=false
```

> **Nota:** Use `127.0.0.1` ao invés de `localhost` na `EVOLUTION_API_URL` para evitar problemas com IPv6.

### 3. Banco de dados

```bash
php artisan migrate
```

### 4. Subir Evolution API (Docker)

```bash
docker compose up -d
```

Isso inicia 3 serviços:
- **evolution-api** (porta 8085) — API WhatsApp
- **evolution-postgres** — Banco PostgreSQL para Evolution
- **evolution-redis** — Cache Redis para Evolution

### 5. Iniciar os serviços

```bash
# Terminal 1 — Servidor PHP (desenvolvimento)
export PHP_CLI_SERVER_WORKERS=4
php -c php-server.ini -S 0.0.0.0:8000 -t public
# Ou use: ./serve.sh (suporta uploads de até 500MB)

# Terminal 2 — Queue Worker
php artisan queue:listen --timeout=120

# Terminal 3 — WebSocket (Reverb)
php artisan reverb:start --host=0.0.0.0 --port=6001
```

Acesse: http://localhost:8000

## Comandos Úteis

```bash
# Limpar mídias órfãs do storage
php artisan media:clean-orphans
php artisan media:clean-orphans --dry-run  # Preview sem remover

# Corrigir nomes de grupos
php artisan groups:fix-names

# Sincronizar status das instâncias
php artisan instances:sync-status
```

## Baileys Service (Opcional)

Serviço Node.js complementar que adiciona funcionalidades extras: reagir, deletar, editar e encaminhar mensagens.

```bash
cd baileys-service
npm install
npm start
# Ou com PM2: pm2 start ecosystem.config.js
```

Configure no `.env`:
```env
BAILEYS_SERVICE_URL=http://localhost:3001
BAILEYS_SERVICE_ENABLED=true
```

## Deploy em Produção (aaPanel)

### 1. Requisitos no servidor
- PHP 8.2+ (extensões: mbstring, xml, curl, zip, pdo_mysql, gd, bcmath)
- MySQL/MariaDB
- Node.js 18+ (apenas para build dos assets)
- Docker + Docker Compose
- Composer
- Supervisor

### 2. Clonar e instalar

```bash
cd /www/wwwroot
git clone https://gitea.scordon.com.br/larissa/wppmanager.git
cd wppmanager

composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### 3. Configurar .env

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com.br

DB_DATABASE=wpp_manager
DB_USERNAME=wpp_user
DB_PASSWORD=senha_segura

EVOLUTION_API_URL=http://127.0.0.1:8085
EVOLUTION_API_KEY=sua_chave

BROADCAST_CONNECTION=reverb
REVERB_HOST=seu-dominio.com.br
REVERB_PORT=6001
REVERB_SCHEME=https
```

> Use `127.0.0.1` ao invés de `localhost` na `EVOLUTION_API_URL` para evitar problemas com IPv6.

### 4. Banco de dados e cache

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Docker (Evolution API)

```bash
docker compose up -d
```

Inicia: Evolution API (porta 8085) + PostgreSQL + Redis

### 6. Configurar no aaPanel

**Site:** apontar document root para `/www/wwwroot/wppmanager/public`

**Nginx** — adicionar no vhost:
```nginx
location /app {
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}

client_max_body_size 64M;
```

**Supervisor** — criar 2 processos:
```ini
# Queue Worker
command=php /www/wwwroot/wppmanager/artisan queue:work --timeout=120 --tries=3 --max-time=3600

# Reverb (WebSocket)
command=php /www/wwwroot/wppmanager/artisan reverb:start --host=0.0.0.0 --port=6001
```

**Cron:**
```
* * * * * cd /www/wwwroot/wppmanager && php artisan schedule:run >> /dev/null 2>&1
```

### 7. Permissões

```bash
chown -R www:www /www/wwwroot/wppmanager
chmod -R 775 storage bootstrap/cache
```

### 8. SSL

Configurar pelo aaPanel (Let's Encrypt)

### 9. Criar usuario admin

```bash
php artisan tinker
```
```php
User::create(['name'=>'Admin','email'=>'admin@empresa.com','password'=>bcrypt('senha'),'role'=>'admin']);
```

### 10. Checklist

- [ ] PHP 8.2+ com extensões
- [ ] Banco de dados criado e migrations rodadas
- [ ] Docker rodando (Evolution API)
- [ ] Site apontando para `/public`
- [ ] SSL ativo
- [ ] Supervisor: queue worker + reverb
- [ ] Cron configurado
- [ ] Permissões corretas
- [ ] Webhook da Evolution apontando para o sistema
- [ ] Teste de envio/recebimento de mensagens

> Guia detalhado em [docs/PRODUCAO.md](docs/PRODUCAO.md)

## Estrutura do Projeto

```
app/
├── Http/Controllers/
│   ├── Admin/          # Controllers do painel (Chat, Import, Contact, Monitor)
│   └── Api/            # Webhook da Evolution API
├── Models/             # Eloquent models (Message, Chat, Conversa, Contact, etc.)
├── Services/           # EvolutionApiService, HistorySyncService, BaileysService
├── Jobs/               # FetchGroupNameJob
├── Events/             # NewMessageReceived (broadcast)
└── Console/Commands/   # Comandos artisan customizados

resources/views/admin/  # Views Blade do painel
baileys-service/        # Serviço Node.js complementar
docs/                   # Documentação
```
