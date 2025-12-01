# Horizon Platform — Backend + Frontend Monorepo

This repository contains the unified development environment for the **Horizon Interstellar Organization Platform**, built by Icha.

## 🚀 Overview

The system is a two-part architecture:

- **Backend (Laravel 12 API)**
  - Sanctum API token authentication  
  - Discord ID–based login  
  - Verification code system  
  - RSI + org membership verification  
  - Rank middleware (1–6)  
  - Mission endpoints  
  - User profiles  
  - UEX data ingestion (future phase)  

- **Frontend (WordPress)**
  - UI for officers + members  
  - Custom bridge plugin for API syncing  
  - User dashboard  
  - Org management tools  
  - Integrated login flow to backend  

This is the early skeleton phase, building the foundations before scaling.

---

## 📡 Current Features Implemented

### Authentication & Security
- Discord → Backend token creation  
- Sanctum API guard wired correctly  
- Token issuance on verified login  
- Rank middleware for chain-of-command access  
- `/profile` endpoint for authenticated users  

### Verification System
- Generate verification code  
- Validate RSI handle  
- Validate org membership  
- Store verification attempts  
- Link Discord ID → User row in DB  

### Core Endpoints
- `/ping` health check  
- `/profile` (rank ≥ 1)  
- `/users` listing (rank ≥ 3)  
- Mission + event placeholders  

---

## 🧭 Roadmap (4-Month Skeleton)

### **Month 1 — Foundation & Expansion**
- Stable API
- Full user CRUD
- WordPress bridge plugin scaffolding
- Basic admin UI

### **Month 2 — Officer Tools**
- Mission system
- Event system
- User verification dashboard
- Role/rank sync with Discord

### **Month 3 — Data Systems**
- UEX integration  
- Ship loadouts  
- Trading dashboards  
- Fleet rosters

### **Month 4 — Organization Suite**
- Full officer panel
- Org-wide dashboards
- Clean UX pass
- Beta release for RVG

---

## 🧑‍💻 Development Requirements

- PHP 8.4 (Laragon recommended)
- Laravel 12+
- Node 20+
- WordPress 6.x
- MySQL/PostgreSQL
- Postman or Insomnia

---

## 👥 Contributors
- **Icha** — Lead architect & system designer  
- **Helper Devs** — Supporting devs assisting with CRUD, small features, WordPress integration, and documentation  

---

## 🏁 How to Start

cd backend
composer install
php artisan migrate
npm install



Follow CONTRIBUTING.md for branching and workflow.
===================================================

Coding Standards (Anti-Spaghetti Rules)
===================================================


# Coding Standards

These rules keep the codebase clean, maintainable, and free of chaos-creature spaghetti.

---

## 📘 General Principles

1. **Readability beats cleverness.**
2. **Consistency over creativity in core logic.**
3. **Follow the framework conventions whenever possible.**
4. **All work should be predictable for future contributors.**
5. **If a solution feels like a hack, it probably is. Ask first.**

---

## 🐘 PHP / Laravel Standards

### ✔ DO:
- PSR-12 formatting  
- 4-space indentation  
- Type-hint everything  
- Use `Request` validation classes  
- Use resources for API responses  
- Write clean controller methods (10–50 lines max)  
- Extract heavy logic into services  

### ✘ DO NOT:
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

## ⚡ JavaScript / WordPress Rules

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