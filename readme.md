# Horizon Platform — Backend + Frontend Monorepo

This repository contains the unified development environment for the **Horizon Interstellar Organization Platform**, built by Icha.

## 🚀 Overview

The platform is a hybrid Inertia + API architecture:

- **Backend (Laravel 12 + Sanctum)**
  - Discord OAuth authentication with session + API token support
  - RSI handle verification with org membership validation
  - Role-based access control (9-level hierarchy: member → director)
  - Full operations system (CRUD, state machine, templates, calendar export)
  - Squadron management with leader/lieutenant structure
  - Polymorphic media system (avatars, operation images, emblems)
  - Admin panel for user, squadron, and role management
  - REST API (`/api/v1`) for external consumers (bot, future mobile)

- **Frontend (Vue 3 + Inertia.js + Tailwind CSS v4)**
  - Operations dashboard and mission editor
  - Squadron listing, detail pages, and roster management
  - Member directory and extended user profiles
  - Admin dashboard with user/squadron/role management
  - Rich text editor, date/time picker, media picker, and 30+ shared components
  - Global error handling with popup dialog system

- **Discord Bot (Node.js + Discord.js)**
  - `/verify` and `/status` slash commands
  - Operation announcement webhooks
  - Nickname sync cron and welcome service

The platform is in **active feature development** — core infrastructure is stable and all major systems are functional.

---

## 📡 Current Features

### Authentication & Identity
- Discord OAuth login → Sanctum token issuance
- RSI handle verification (code generation → profile scrape → org check)
- Bot-assisted verification via Discord slash command
- Auth audit logging and failed attempt tracking
- Force Discord auth, max auth age, and RSI verified middleware

### Access Control
- Role-based access with 9-level hierarchy (`RoleHierarchy`)
- Centralized `AccessService` with DDD permission rules
- Policy classes for operations, squadrons, media, templates, RSI requests
- Director/Tech Director global override

### Operations
- Full CRUD with state machine (`draft → published → in_progress → completed/canceled`)
- Operation types, branches (industries/defence/frontiers/lifelines), visibility, strictness
- Slot system, role definitions, participant join/leave/slot assignment
- DB-backed templates (personal/squadron/global scope)
- Calendar export (.ics), Discord webhook announcements
- Rich editor with template load/save, media picker, extended description
- Completion outcome tracking (success/failure/partial)

### Squadrons
- Full CRUD with leader/lieutenant role structure (max 2 lieutenants)
- Member management (add, update status, remove, promote, demote)
- Identity fields (emblem, description, motto, branch, division, propaganda)
- Squadron listing, detail pages, and roster with profile links

### User Profiles
- Extended profile fields (bio, timezone, callsign, ships, guns, roles, tags, availability)
- Member directory with paginated search
- Operation stat counters (joined, completed, created, canceled, success, failed)
- RSI handle change request flow (request → approve/reject)
- Viewable profiles with role-gated stat visibility

### Media
- Polymorphic media system (avatars, operation images, squadron emblems)
- Upload, attach, delete actions with policy-controlled access
- Reusable media picker modal component

### Admin Panel
- Dashboard for director/tech_director roles
- User management (update profile, assign roles)
- Squadron management (CRUD, member management, rank promotions)
- Role management (create, update, delete)
- Media library browser

### Discord Bot
- `/verify` and `/status` slash commands
- Operation publish/update webhook announcements
- Nickname sync cron, welcome service, guild member event handling

---

## 🧑‍💻 Development Requirements

- PHP 8.4 (Laragon recommended)
- Laravel 12
- Vue 3 + Inertia.js
- Tailwind CSS v4
- Node 20+
- PostgreSQL
- Discord application (OAuth + bot token)

---

## 🏁 How to Start

```bash
cd backend
composer install
npm install
cp .env.example .env        # configure DB, Discord, and app keys
php artisan key:generate
php artisan migrate
npm run dev                  # Vite dev server
php artisan serve            # Laravel dev server
```

For the Discord bot:
```bash
cd bots/horizon-bot
npm install
# configure .env with bot token and API keys
node index.js
```

See `ONBOARDING.md` for detailed setup instructions.

---

## Coding Standards

### Anti-Spaghetti Rules

These rules keep the codebase clean, maintainable, and free of chaos-creature spaghetti.

## 📘 General Principles

1. **Readability beats cleverness.**
2. **Consistency over creativity in core logic.**

---

## 🐘 PHP / Laravel Standards

### ✔ DO

- PSR-12 formatting
- 4-space indentation
- Type-hint everything
- Use `Request` validation classes
- Use resources for API responses
- Write clean controller methods (10–50 lines max)
- Extract heavy logic into services

### ✘ DO NOT

- Put raw SQL in controllers
- Return arrays instead of structured responses
- Use facades randomly
- Dump-and-die in committed code (`dd()`, `dump()`)
- Leave commented-out code blocks
- Write 300-line controller methods

---

## 🧩 API Rules

- All JSON goes through a shared response helper (success/error)
- Status codes MUST be correct  
- Authentication cannot be bypassed  
- Rank middleware must stay at the edge of protected endpoints  

---

## ⚡ JavaScript

- Use 2-space indentation  
- Keep scripts modular  
- No jQuery unless absolutely necessary  
- Document function behavior  

---

## 🗄 Database Rules

- Each migration must be reversible  
- No destructive changes without a plan  
- Foreign keys on all relational tables  
- Keep naming consistent: `snake_case` everywhere  

---

## 🧪 Testing / Verification

- All endpoints must be Postman-tested  
- Include the token + rank in each test  
- Verify error paths, not just success  

---

## ☕ Developer Culture Expectations

- Communicate clearly  
- Ship small PRs  
- Respect architecture decisions  
- Ask questions early  
- Keep things professional (no drama)

---

## 🛡 Project Philosophy

This system is long-term infrastructure, designed to be:

- Modular
- Predictable
- Secure
- Easy to build on
- Easy to scale
- Easy to maintain
- Easy to understand
- Easy to debug
- Easy to test
- Easy to deploy
- Easy to update

---
