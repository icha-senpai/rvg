# Horizon Platform — Developer Onboarding

This repo is a monorepo for the **Horizon Interstellar Organization Platform**.

## Repo layout

- **`backend/`**
  - Laravel 12 app
  - REST API under `/api/v1`
  - Inertia + Vue 3 frontend (served by Laravel)
- **`bots/horizon-bot/`**
  - Discord bot (discord.js) + Express webhook server

Note: `contributing.md` references a `/frontend` folder, but the current frontend lives inside `backend/resources/js` via Inertia.

---

## Tech stack (what you’re actually running)

- **Backend:** Laravel 12 (PHP)
- **Frontend:** Vue 3 + Inertia.js + Ziggy
- **Styling:** Tailwind CSS v4 + Horizon UI tokens/components in `backend/resources/css/app.css`
- **Auth:**
  - Discord OAuth (Socialite)
  - Laravel session for web/Inertia routes
  - Sanctum bearer tokens for `/api/v1/*`
- **Local defaults:**
  - DB: PostgreSQL (via `DB_CONNECTION=pgsql`)
  - Queue: database (`QUEUE_CONNECTION=database`)
  - Sessions: database (`SESSION_DRIVER=database`)
  - Cache: database (`CACHE_STORE=database`)

---

## First-time setup (local)

### Prereqs

- PHP (project README mentions PHP 8.4; `backend/composer.json` currently requires `php: ^8.2`)
- Node 20+
- Composer
- Laragon is recommended on Windows

### Backend (Laravel + Inertia/Vue)

From repo root:

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

Run the dev stack (recommended):

```bash
composer run dev
```

That runs, in parallel:

- `php artisan serve` (Laravel)
- `php artisan queue:listen --tries=1` (queues)
- `php artisan pail --timeout=0` (logs)
- `npm run dev` (Vite)

Optional one-shot bootstrap:

```bash
composer run setup
```

### Tests

From `backend/`:

```bash
composer run test
```

---

## Key environment variables

### Discord OAuth + guild gate (backend)

Configured in `backend/config/services.php`.

- `DISCORD_CLIENT_ID`
- `DISCORD_CLIENT_SECRET`
- `DISCORD_REDIRECT_URI`
- `DISCORD_GUILD_ID`
- `DISCORD_BOT_TOKEN`
- `DISCORD_GUILD_CHECK=true|false`

If `DISCORD_GUILD_CHECK=true` and the guild settings are missing/invalid, login and refresh flows can reject users.

### Bot-to-API secret (backend)

Bot endpoints require header `X-Bot-Secret`.

- `DISCORD_BOT_SECRET`

### RSI verification (backend)

- `RSI_REQUIRED_ORG` (default `SRN`)
- `RSI_VERIFICATION_TIMEOUT` (default `10`)

---

## Where things live (entry points)

### Routes

- **Web (Inertia pages):** `backend/routes/web.php`
  - `/verify` → Inertia `Pages/Verify.vue`
  - `/auth/discord` + `/auth/discord/callback` (Discord OAuth)
  - `/operations/*`, `/squadrons/*`, `/admin/*`

- **API:** `backend/routes/api.php` mounts:
  - `backend/routes/api_v1.php` → `/api/v1/*`
  - `backend/routes/bot.php` → `/api/v1/bot/*`

### Frontend

- Inertia entry: `backend/resources/js/app.js`
  - global component registration
  - axios bearer-token attachment
  - refresh-token retry (`/api/v1/auth/refresh`)
- Pages: `backend/resources/js/Pages/*`

### Backend domain logic

- Models: `backend/app/Models/*`
- Controllers:
  - API: `backend/app/Http/Controllers/Api/v1/*`
  - Web/Inertia: `backend/app/Http/Controllers/Web/*`
- Form Requests: `backend/app/Http/Requests/*`
- Domain modules:
  - `backend/app/Domain/Operations/*`
  - `backend/app/Domain/AccessControl/*`
  - `backend/app/Domain/Squadrons/*`

---

## Auth & verification flow (end-to-end)

### 1) Discord login (web)

- User visits `/verify` and clicks “Verify with Discord”
- OAuth starts at `/auth/discord`
- Callback hits `/auth/discord/callback` (`DiscordAuthController`)
  - checks Discord guild membership (`DiscordOAuthService::checkGuildMembership`)
  - syncs/creates user by `discord_id` (`DiscordOAuthService::syncBasicUser`)
  - logs user into Laravel session (web auth)
  - issues Sanctum tokens via `TokenService`
  - redirects back to `/verify?token=...&refresh_token=...`

### 2) Verify page token handling (frontend)

`Pages/Verify.vue`:

- stores `token` + `refresh_token` into `localStorage`
- calls `GET /api/v1/me` to check verification state
- if RSI already verified → redirects to `/`
- else runs RSI verification flow (`/api/v1/generate-code`, `/api/v1/verify-rsi`)

### 3) API auth

- API routes are protected with `auth:sanctum`
- The frontend uses Axios interceptors in `resources/js/app.js` to:
  - attach `Authorization: Bearer <access_token>`
  - refresh access tokens via `POST /api/v1/auth/refresh` using `refresh_token`

---

## Authorization model

### Rank middleware (API)

- Implemented in `backend/app/Http/Middleware/RankMiddleware.php`
- Applied in `routes/api_v1.php` as `rank:1..6`
- Role bypass: users with role `director` or `tech_director` bypass rank checks

### RSI verified gate (web + some API responses)

- Implemented in `backend/app/Http/Middleware/EnsureRsiVerified.php`
- Middleware key: `rsi.verified`
- Web routes like `/operations/member` and `/squadrons/*` require auth + RSI verification

### Bot secret gate

- `backend/app/Http/Middleware/VerifyBotSecret.php`
- Requires header `X-Bot-Secret` matching `config('services.discord.bot_secret')`

---

## Main product areas

### Operations

- Web routes: `/operations/*` in `routes/web.php`
- Inertia pages: `resources/js/Pages/Operations/*`
- API: `OperationController` under `/api/v1/operations`
- Domain: `app/Domain/Operations/*`
  - `OperationService` orchestrates Actions
  - `OperationPresenter` defines normalized payload shapes:
    - `summary()` (lists)
    - `full()` (detail view)
    - `form()` (editor hydration)

### Squadrons

- API: `SquadronController`, `SquadronMemberController`
- Web pages: `resources/js/Pages/Squadrons/*`

### Admin

- Web routes: `/admin/*` (guarded by `can:access-admin-panel`)
- Controller: `app/Http/Controllers/Web/AdminController.php`
- Pages: `resources/js/Pages/Admin/*`

---

## Bot (Discord) overview

Location: `bots/horizon-bot/`

- `index.js` starts:
  - Express webhook server (`/bot/*`)
  - Discord client
  - slash command registration

The bot calls the Laravel API at `/api/v1/bot/*` using `X-Bot-Secret`.

### Bot setup (only if you’re working on bot features)

```bash
cd bots/horizon-bot
npm install
node index.js
```

You’ll need a `.env` (the repo includes `test.env` but it’s not enough). Common variables:

- `DISCORD_TOKEN`
- `DISCORD_CLIENT_ID`
- `DISCORD_GUILD_ID`
- `API_BASE_URL=http://localhost:8000/api/v1`
- `API_SECRET=...` (must match Laravel `DISCORD_BOT_SECRET`)
- `PORT=3001` (optional)

---

## Common “where do I change X?” map

- Discord OAuth login flow
  - `backend/routes/web.php`
  - `backend/app/Http/Controllers/Api/v1/DiscordAuthController.php`
  - `backend/app/Services/DiscordOAuthService.php`

- Token refresh
  - backend: `backend/app/Http/Controllers/Api/v1/TokenController.php` + `backend/app/Services/TokenService.php`
  - frontend: `backend/resources/js/app.js` (Axios interceptors)

- Operations UI
  - `backend/resources/js/Pages/Operations/MissionsIndex.vue`

- Operations business logic
  - `backend/app/Domain/Operations/Actions/*`
  - `backend/app/Domain/Operations/Services/OperationService.php`
  - `backend/app/Domain/Operations/Presenters/OperationPresenter.php`

- Rank gating
  - `backend/app/Http/Middleware/RankMiddleware.php`
  - `backend/routes/api_v1.php`

---

## Debugging notes

- Logs (live): `php artisan pail` (already included in `composer run dev`)
- Log file: `backend/storage/logs/laravel.log`
- Routes: `php artisan route:list`
- DB (local default): PostgreSQL (`DB_CONNECTION=pgsql`)

If you get blocked during login/refresh in local dev and you’re not actively testing Discord guild membership rules, consider setting:

- `DISCORD_GUILD_CHECK=false`

(Otherwise you’ll need valid `DISCORD_GUILD_ID` + `DISCORD_BOT_TOKEN`.)
