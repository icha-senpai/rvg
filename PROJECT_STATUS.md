# Horizon Platform — Project Status Document

**Last Updated:** June 14, 2026  
**Stack:** Laravel 12 (PHP 8.4) · Vue 3 · Inertia.js · Tailwind CSS v4 · PostgreSQL · Discord.js Bot  
**Snapshot Scope:** This status reflects both committed repo state and the current worktree changes present on June 14, 2026.

---

## Snapshot Summary

Horizon is now a large hybrid Laravel/Inertia platform with the core systems in place:
- Discord auth plus RSI verification
- Role and permission-driven access control
- Member profiles, preferences, themes, and directory surfaces
- Operations planning, attendance, AAR, templates, and settlement flows
- Personal, squadron, and organization ledger systems
- Squadron management plus Discord-linked sync tooling
- UEX market sync and admin reference browsing
- Archive, media, and admin tooling

The biggest active work in the current worktree is the new **operation runtime** flow: a live run console for syncing lobby attendance, handling walk-ins, tracking no-shows/signed-off members, and orchestrating operation-specific Discord voice channels.

---

## Architecture Overview

| Layer | Technology | Location |
|-------|-----------|----------|
| Backend API + Web | Laravel 12 + Sanctum + Inertia | `backend/` |
| Frontend UI | Vue 3 + Inertia.js | `backend/resources/js/` |
| Styling | Tailwind CSS v4 | `backend/resources/css/app.css` |
| Discord Bot | Node.js + Discord.js | `bots/horizon-bot/` |
| Database | PostgreSQL | 72 migrations in repo |
| Auth | Discord OAuth + session auth + Sanctum tokens | Hybrid web/API |

The project uses a **hybrid Inertia + API architecture**:
- **Inertia routes** in `backend/routes/web.php` render first-party Vue pages and pass props directly.
- **API routes** in `backend/routes/api_v1.php` return JSON for external/programmatic consumers.
- The app root is the nested `backend/` Laravel app, not the repo root.

---

## Current Worktree Focus

### Active WIP — Operation Runtime + Discord Orchestration

| Feature | Status | Notes |
|---------|--------|-------|
| Live operation run console | 🟡 Active WIP | New `/operations/{operation}/run` surface exists in the current worktree |
| Lobby attendance sync | 🟡 Active WIP | Syncs Discord lobby presence into Horizon runtime participant state |
| Walk-in participant capture | 🟡 Active WIP | Managers can add live attendees who were not signed up ahead of time |
| Runtime status management | 🟡 Active WIP | Supports signed up, signed off before start, no-show, finished, and technical issue states |
| Operation Discord channel layout | 🟡 Active WIP | Stores per-operation voice channel layout plus participant assignments |
| Discord channel sync + cleanup | 🟡 Active WIP | Current worktree includes bot/webhook support and cleanup tests for complete/cancel flows |
| Supporting UI pass | 🟡 Active WIP | Mission show/dashboard, AAR, settlement, and admin panels are being adjusted alongside runtime work |

This work is already substantial enough to track in status, but it should still be treated as **active implementation**, not a fully settled shipped subsystem yet.

---

## Recently Shipped Since The Previous Status Snapshot

- Operation visibility now supports **`open`**, **`squadron`**, and **`private`** access modes with visibility-aware querying and access control.
- Discord operation announcements now track **targeted message addresses** in `discord_message_targets`, which supports update/edit behavior across multiple target channels.
- Member settings now persist **`site_theme`** and include Discord self-role sync/update flows.
- Ledger transfer flows now include **organization fund/inventory approval and rejection** endpoints across the shared surfaces.
- Ledger transfers now support **external destinations** and stricter **whole-number validation** for money and quantity inputs.
- The admin UEX surface now handles **stale/interrupted sync runs** more clearly instead of only showing clean success/failure cases.
- Shared Inertia data now includes more badge/count context, including pending transfer/application style surfaces.

---

## What's Built — Feature Inventory

### 1. Authentication & Identity

| Feature | Status | Notes |
|---------|--------|-------|
| Discord OAuth login | ✅ Done | Web redirect/callback flow is in place |
| Session-based web auth | ✅ Done | Inertia routes use the authenticated web guard |
| Sanctum API token auth | ✅ Done | API token issuance exists for verified users |
| RSI handle verification | ✅ Done | Verification code + RSI/org membership validation flow |
| Bot-assisted verification | ✅ Done | Discord `/verify` command flow exists |
| Verification code expiry | ✅ Done | Verification codes are time-limited and cleared on success |
| Auth audit logging | ✅ Done | Auth events and failed verification attempts are tracked |
| Discord auth enforcement | ✅ Done | `ForceDiscordAuth` and auth age middleware are wired |
| RSI verified gating | ✅ Done | `EnsureRsiVerified` protects verified-only surfaces |
| Admin unverify across Discord-linked accounts | ✅ Done | Admin unverify flow clears verification state for the full linked Discord account set |
| Clear remembered sessions | ✅ Done | Admin tooling can clear persisted web sessions/tokens |

### 2. Access Control System

| Feature | Status | Notes |
|---------|--------|-------|
| Role-based access (RBAC) | ✅ Done | Roles, permissions, and pivot tables are present |
| Role hierarchy | ✅ Done | `RoleHierarchy` supports command-level thresholds |
| Permission registry | ✅ Done | Known permission slugs are centralized |
| Centralized access service | ✅ Done | Access logic lives in the access-control domain |
| Director/tech director override | ✅ Done | Director-like bypass behavior exists |
| Policy coverage | ✅ Done | Operation, squadron, media, template, RSI, and admin policies exist |
| Admin panel gate | ✅ Done | Admin access is permission-gated |

### 3. User Profiles, Preferences, and Member Surfaces

| Feature | Status | Notes |
|---------|--------|-------|
| Extended user profile fields | ✅ Done | Callsign, timezone, region, ships, guns, bio, tags, availability, and more |
| Member directory | ✅ Done | Searchable, paginated member listing |
| User profile page | ✅ Done | Member profile surface is viewable in the web UI |
| `/me` API + web settings flow | ✅ Done | Users can update their own profile/preferences |
| Member preferences | ✅ Done | Separate preference storage exists |
| RSI handle change request flow | ✅ Done | Request, approve, reject workflow exists |
| Stats on profile | ✅ Done | Command record/member activity stats are surfaced |
| Site theme preference | ✅ Done | `site_theme` is stored on the user and reapplied in the shell |
| Discord self-role settings | ✅ Done | Member settings include Discord role sync/update flows |

### 4. Operations System

| Feature | Status | Notes |
|---------|--------|-------|
| Operation CRUD (web + API) | ✅ Done | Create, edit, publish, start, complete, cancel |
| Operation state machine | ✅ Done | `draft`, `published`, `in_progress`, `completed`, `canceled` |
| Operation visibility | ✅ Done | `open`, `squadron`, and `private` modes are supported |
| Operation types/branches/strictness | ✅ Done | Scheduling and classification fields are established |
| RSVP deadline + status lockouts | ✅ Done | Join/leave is gated by lifecycle state and scheduling rules |
| Slot and role system | ✅ Done | Roles, capacities, slot assignment, and synchronization exist |
| Participant system | ✅ Done | Join/leave plus participant assignment flows exist |
| Simplified participant roster for limited viewers | ✅ Done | Non-managers can see names/counts without privileged assignment detail |
| After Action Reports | ✅ Done | AAR editing, attendance capture, and no-show tracking exist |
| Operation settlement | ✅ Done | Completed operations can be settled with payouts and loot |
| UEX-backed settlement references | ✅ Done | Loot settlement pulls from synced UEX data |
| Settlement finalize/reopen safeguards | ✅ Done | Reopen is blocked when downstream ledger activity exists |
| Settlement CSV export | ✅ Done | Finalized settlements can export |
| Operation templates | ✅ Done | Personal, squadron, and global templates are supported |
| Calendar export | ✅ Done | `.ics` export is available |
| Discord publish/update announcements | ✅ Done | Operation events post to Discord |
| Discord announcement target tracking | ✅ Done | `discord_message_targets` stores per-target routing metadata |
| Member operations index | ✅ Done | Grid/list browsing exists for members |
| Management/dashboard surfaces | ✅ Done | Dashboard and detail/editor surfaces are established |
| Live operation runtime tooling | 🟡 Active WIP | Run console, runtime sync, walk-ins, and Discord channel orchestration are in the current worktree |

**Operations Domain Structure**
```
Domain/Operations/
├── Actions/
├── Events/
├── Listeners/
├── Services/
├── Presenters/
├── Queries/
└── States/
```

### 5. Assets, Funds, and Treasury System

| Feature | Status | Notes |
|---------|--------|-------|
| Personal ledger | ✅ Done | Personal funds, trades, inventory, ships, and reports |
| Squadron ledger | ✅ Done | Shared squadron books with scoped permissions |
| Horizon Treasury | ✅ Done | Organization-owned shared ledger surfaces |
| Wipe cycle system | ✅ Done | Current/archive cycle support exists |
| Live cycle editing | ✅ Done | Cycle naming/version/type/start timestamps are editable |
| UEX-assisted entry helpers | ✅ Done | Trades, inventory, and ship flows use synced UEX references |
| Fund transfer system | ✅ Done | Personal, squadron, org, and member-directed transfers exist |
| Inventory transfer system | ✅ Done | Inventory movement exists across the same ownership scopes |
| Approval/rejection workflows | ✅ Done | Personal, squadron, and organization flows support approval gating |
| External transfer destinations | ✅ Done | Transfer requests can target non-Horizon destinations |
| Transfer reversal safeguards | ✅ Done | Completed fund transfer reversal exists with safety checks |
| Provenance locking | ✅ Done | Transfer/settlement-derived records are protected from normal edits/deletes |
| Whole-number validation | ✅ Done | Money and quantity fields are validated as whole-number values |
| Search, pagination, and reporting | ✅ Done | Ledger surfaces support filters, summaries, charts, and richer browsing |
| Admin ledger analytics | ✅ Done | Leadership-facing analytics exist in admin |
| Operation-linked receipts | ✅ Done | Settlements write real ledger outputs into the books |

### 6. Squadrons System

| Feature | Status | Notes |
|---------|--------|-------|
| Squadron CRUD | ✅ Done | Admin and API-backed management exists |
| Squadron roster management | ✅ Done | Add, update, accept, reject, remove, and rank members |
| Membership states | ✅ Done | Pending/active style membership handling exists |
| Leader + lieutenant rules | ✅ Done | Leadership assignment and lieutenant cap rules are implemented |
| Squadron detail/listing pages | ✅ Done | Member-facing squadron surfaces exist |
| Viewer status + roster UI | ✅ Done | Squadron member status is surfaced in the UI |
| Identity fields + emblem upload | ✅ Done | Description, motto, emblem, branch/division, propaganda, and more |
| Discord-linked squadron tooling | ✅ Done | Admin create/sync/repair Discord flows exist for squadron integration |

### 7. UEX Sync & Market Reference System

| Feature | Status | Notes |
|---------|--------|-------|
| UEX sync tables | ✅ Done | Commodities, items, prices, terminals, vehicles, and related reference data |
| Admin UEX status panel | ✅ Done | Dashboard includes sync visibility and run summaries |
| Manual sync actions | ✅ Done | Admin can trigger sync scopes directly |
| Stale/interrupted run handling | ✅ Done | Broken/incomplete runs surface as failed state instead of silently looking current |
| UEX data browser | ✅ Done | Admin panel can browse synced resource groups/tables |
| Ledger valuation helpers | ✅ Done | Synced data powers trade and estimate helpers |
| Settlement loot sourcing | ✅ Done | Operation loot settlement uses synced UEX references |

### 8. Media, Archive, and Content Systems

| Feature | Status | Notes |
|---------|--------|-------|
| Media library | ✅ Done | Polymorphic media with attach/delete/upload flows |
| Media picker | ✅ Done | Shared picker modal exists for web surfaces |
| Archive module | ✅ Done | Categories, topics, entries, taxonomy, trash, and audit logging exist |
| Archive visibility rules | ✅ Done | Rank/visibility-aware archive surfaces are implemented |

### 9. Admin Panel

| Feature | Status | Notes |
|---------|--------|-------|
| Admin dashboard | ✅ Done | Director-like oversight surface exists |
| User management | ✅ Done | Admin can update users, roles, verification state, and remembered sessions |
| Squadron management | ✅ Done | CRUD plus roster/Discord tooling |
| Role management | ✅ Done | Role/permission administration exists |
| Archive administration | ✅ Done | Archive authoring and moderation tooling exists |
| Operations oversight | ✅ Done | Admin operations panel includes AAR/cancellation review support |
| Ledger administration | ✅ Done | Cycle controls, analytics, and shared-ledger oversight exist |
| UEX administration | ✅ Done | Sync controls, status reporting, and data browsing exist |

### 10. Discord Bot (`bots/horizon-bot/`)

| Feature | Status | Notes |
|---------|--------|-------|
| Bot framework | ✅ Done | Discord.js command/event structure exists |
| `/verify` command | ✅ Done | Verification flow is implemented |
| `/status` command | ✅ Done | Status command exists |
| Welcome + member lifecycle handling | ✅ Done | Join/update handling and welcome services exist |
| Nickname sync cron | ✅ Done | Background nickname sync tooling exists |
| Operation announcement webhook support | ✅ Done | Bot receives operation publish/update traffic |
| Operation runtime webhook support | 🟡 Active WIP | Current worktree includes `/operations/runtime/sync-lobby` and `/operations/runtime/sync-channels` bot endpoints |
| Logging/watcher utilities | ✅ Done | Runtime monitoring/logging helpers exist |

### 11. Shared UI Components and Shell

| Feature | Status | Notes |
|---------|--------|-------|
| App shell + side navigation | ✅ Done | Shared shell/navigation framework exists |
| Horizon form/control library | ✅ Done | Buttons, inputs, selects, date/time, sections, panels, modals, alerts |
| Rich text editor | ✅ Done | Shared editor exists and is used by operation/admin flows |
| Global error handling | ✅ Done | Error dialog and shared notification/error plumbing exists |
| Theming support | ✅ Done | Shell applies persisted user theme selection |

---

## Database Schema Summary

**72 migrations** currently exist in the repo.

Key tables and groups:

| Table / Group | Purpose |
|--------------|---------|
| `users` | Core identity, Discord info, RSI info, profile fields, theme, and member stats |
| `personal_access_tokens` | Sanctum token storage |
| `roles`, `permissions`, pivots | Role/permission model |
| `squadrons`, `squadron_members` | Squadron identity and roster state |
| `operations` | Scheduled operations, lifecycle state, visibility, AAR fields, settlement linkage, Discord message target tracking |
| `operation_participants` | Participant roster, assignment, attendance, and runtime fields |
| `operation_roles` | Role definitions per operation |
| `operation_templates` | Saved mission templates |
| `operation_settlements` | Settlement workspace for completed operations |
| `operation_sync_runs` | Runtime sync history for live attendance snapshots |
| `operation_discord_channels` | Per-operation Discord voice channel layout/state |
| ledger tables + `wipe_cycles` | Personal, squadron, and organization bookkeeping |
| `ledger_transfer_requests` | Approval-aware fund and inventory transfer workflow |
| `uex_*` tables | Synced UEX market/reference data |
| archive tables | Categories, entries, taxonomy, visibility, trash, and audit support |
| `media` | Polymorphic media storage |
| `member_preferences` | Per-user settings/preferences |
| `rsi_change_requests` | RSI handle change request queue |
| `failed_attempts`, `auth_audit_logs` | Verification and auth audit trails |
| `cache`, `jobs` | Laravel infrastructure tables |

---

## What Still Needs Work

### High Priority

- **Operation runtime hardening** — The live run tooling is actively being built in the current worktree and still needs its final integration/polish pass before it should be treated as fully settled.
- **Testing depth** — The suite now covers far more of the app than before, but breadth still outpaces test coverage.
- **Notifications system** — Discord posting exists, but there is still no true in-app notification center.

### Medium Priority

- **Authorization migration cleanup** — Some legacy gates still lean on rank-level style assumptions where role/permission-first thresholds would be cleaner.
- **Runtime/settlement UX refinement** — The newer operation runtime and settlement surfaces will likely want one more round of UX tightening after behavior settles.
- **Activity/audit browsing UI** — Auth/archive logging exists, but the browsing experience for some admin audit trails is still thin.

### Low Priority / Nice-to-Have

- **Advanced operation filtering** — The operations dashboard can still grow better branch/type/date filtering.
- **Bulk admin actions** — Users, squadrons, and operations do not yet have richer bulk tooling.
- **API documentation** — No checked-in OpenAPI/Postman style documentation set exists.
- **CI/CD pipeline** — There is still no visible deployment or CI pipeline configuration in the repo.
- **Deployment docs** — Setup/onboarding exists, but production deployment guidance remains thin.

---

## Code Footprint (Selected Counts)

| Area | Count |
|------|-------|
| Models | 31 |
| Web Controllers | 34 |
| API v1 Controllers | 13 |
| Admin Controllers | 12 |
| Middleware | 7 |
| Form Requests | 36 |
| Policies | 6 |
| Domain Actions | 14 |
| Domain Service Classes | 10 |
| App/Legacy Service Classes | 31 |
| Application Service Classes | 4 |
| Presenters | 9 |
| Vue Pages | 51 |
| Shared Vue Components | 37 |
| Database Migrations | 72 |
| Bot Commands | 2 |
| Bot Services | 13 |
| Bot Events | 5 |

---

*This document is now aligned with the June 14, 2026 repo snapshot and explicitly calls out active worktree runtime/Discord work so it does not get lost between status updates.*
