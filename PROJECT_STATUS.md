# Horizon Platform — Project Status Document

**Last Updated:** June 15, 2026  
**Stack:** Laravel 12 (PHP 8.4) · Vue 3 · Inertia.js · Tailwind CSS v4 · PostgreSQL · Discord.js Bot  
**Snapshot Scope:** This status reflects the current repo plus active worktree changes present on June 15, 2026.

---

## Snapshot Summary

Horizon is now a large hybrid Laravel/Inertia platform with the core systems in place:
- Discord auth plus RSI verification
- Role and permission-driven access control
- Member profiles, preferences, themes, and directory surfaces
- Operations planning, attendance, runtime, AAR, templates, and settlement flows
- Personal, squadron, and organization ledger systems
- Squadron management plus Discord-linked sync tooling
- UEX market sync and admin reference browsing
- Archive, media, and admin tooling

Compared with the previous snapshot, the biggest change is that the **operation runtime subsystem is now materially present in the current worktree**, not just conceptual. The repo now includes:
- a live `/operations/{operation}/run` surface
- runtime roster status handling
- signed-off-early and excused attendance handling
- funds prep wiring into settlement
- per-operation Discord voice layout/sync/cleanup
- broader controller, service, schema, and regression coverage around those flows

The other notable current-worktree theme is a **Horizon UI standardization pass**: shared native controls, drawer/modal cleanup, archive/admin surface alignment, and wider `7xl` page-shell normalization across more member-facing pages.

---

## Validation Snapshot

Current verification run on June 15, 2026:
- `npm run build` passing
- `php artisan test` passing
- test result: **358 passed**
- assertion count: **2633**

This is a significantly stronger snapshot than the prior status document implied. The repo is no longer just broad in surface area; it now has deeper coverage around operations, settlement safety, promotion/ledger schema assumptions, runtime Discord failure paths, and a few targeted frontend logic seams.

---

## Architecture Overview

| Layer | Technology | Location |
|-------|-----------|----------|
| Backend API + Web | Laravel 12 + Sanctum + Inertia | `backend/` |
| Frontend UI | Vue 3 + Inertia.js | `backend/resources/js/` |
| Styling | Tailwind CSS v4 | `backend/resources/css/app.css` |
| Discord Bot | Node.js + Discord.js | `bots/horizon-bot/` |
| Database | PostgreSQL | 77 migrations in repo |
| Auth | Discord OAuth + session auth + Sanctum tokens | Hybrid web/API |

The project uses a **hybrid Inertia + API architecture**:
- **Inertia routes** in `backend/routes/web.php` render first-party Vue pages and pass props directly.
- **API routes** in `backend/routes/api_v1.php` return JSON for external/programmatic consumers.
- The actual Laravel app root is the nested `backend/` directory, not the repo root.

---

## Current Worktree Focus

### 1. Operation Runtime Hardening + Reporting Pipeline

| Feature | Status | Notes |
|---------|--------|-------|
| Live operation run console | ✅ Current worktree | `/operations/{operation}/run` exists and renders through `OperationRuntimeController` |
| Lobby attendance sync | ✅ Current worktree | Sync history and participant runtime state are persisted |
| Walk-in participant capture | ✅ Current worktree | Managers can add live attendees who were not pre-signed |
| Runtime status management | ✅ Current worktree | Present, no-show, excused, signed off early, and related runtime states are supported |
| Funds prep inside runtime | ✅ Current worktree | Prep rows feed the settlement draft/finalization flow |
| Runtime Discord channel layout | ✅ Current worktree | Per-operation channel layout and assignments are stored |
| Discord channel sync + cleanup | ✅ Current worktree | Bot sync/cleanup flows exist with failure-path coverage |
| AAR/runtime/settlement linkage | ✅ Current worktree | Runtime statuses now drive AAR buckets and profile stats more explicitly |

This area is still the newest load-bearing subsystem in the repo, but it is no longer “light WIP.” It now has real route/controller/service/model coverage plus strong feature and unit tests behind it.

### 2. Horizon UI Standardization

| Feature | Status | Notes |
|---------|--------|-------|
| Shared Horizon badge/pill component | ✅ Current worktree | `HorizonBadge.vue` is now reused across archive, settlement, AAR, and admin surfaces |
| Shared Horizon checkbox/toggle | ✅ Current worktree | `HorizonCheckbox.vue` is now replacing plainer checkbox patterns |
| Shared Horizon file input | ✅ Current worktree | `HorizonFileField.vue` is wired into media/archive upload flows |
| Shared Horizon form block shell | ✅ Current worktree | `HorizonFormBlock.vue` is used to normalize compact panel blocks |
| Editor/modal/drawer visual alignment | ✅ Current worktree | More editor and overlay surfaces now follow the Horizon shell language |
| Wider page-shell normalization | ✅ Current worktree | More full pages now use `max-w-7xl` instead of mixed widths |

---

## Recently Shipped Since The Previous Snapshot

- The **operation runtime subsystem** now exists in the current worktree with live run, lobby sync, walk-ins, manual participant status changes, and Discord channel orchestration.
- Attendance flows now distinguish **present**, **no-show**, **excused**, and **signed off early**, and those states propagate through AAR, settlement behavior, and profile stats.
- Operation settlement now supports **funds prep rows** and stronger draft/finalize/reopen safeguards for mixed money + loot states.
- Operation role records now track **`sort_order`** and **`is_required`**, and the presenter/upsert flows know about both.
- New schema and assumption tests now protect newer operation and promotion/ledger seams from silent migration drift.
- Targeted frontend regression coverage now exists for **payout math**, **side nav expansion helpers**, **role color logic**, and **rich text helper seams**.
- Archive/admin/media/member surfaces have received another **Horizon-native UI pass**, including shared checkbox/file field/form block adoption.
- The authenticated `Welcome.vue` surface has been replaced with a more intentional Horizon landing layout instead of the old placeholder block.

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
| Promotion offer workflow | ✅ Done | Profile-driven promotion offer/cancel/accept flow exists |
| Demotion flow | ✅ Done | Profile demotion and pending-offer cancellation are wired |

### 4. Operations System

| Feature | Status | Notes |
|---------|--------|-------|
| Operation CRUD (web + API) | ✅ Done | Create, edit, publish, start, complete, cancel |
| Operation state machine | ✅ Done | `draft`, `published`, `in_progress`, `completed`, `canceled` |
| Operation visibility | ✅ Done | `open`, `squadron`, and `private` modes are supported |
| Operation types/branches/strictness | ✅ Done | Scheduling and classification fields are established |
| RSVP deadline + status lockouts | ✅ Done | Join/leave is gated by lifecycle state and scheduling rules |
| Slot and role system | ✅ Done | Roles, capacities, slot assignment, required flags, and sort order exist |
| Participant system | ✅ Done | Join/leave plus participant assignment flows exist |
| Simplified participant roster for limited viewers | ✅ Done | Non-managers can see names/counts without privileged assignment detail |
| Live operation runtime tooling | ✅ Done in current worktree | Run console, sync history, walk-ins, layout saves, and Discord sync exist |
| Runtime participant status pipeline | ✅ Done in current worktree | Present/no-show/excused/signed-off flows are wired through completion |
| After Action Reports | ✅ Done | AAR editing, attendance capture, and no-show tracking exist |
| Settlement draft, finalize, reopen | ✅ Done | Completed operations can be settled with strong downstream safety checks |
| Funds prep -> settlement integration | ✅ Done in current worktree | Prep rows can seed settlement draft behavior |
| UEX-backed settlement references | ✅ Done | Loot settlement pulls from synced UEX data |
| Settlement CSV export | ✅ Done | Finalized settlements can export |
| Operation templates | ✅ Done | Personal, squadron, and global templates are supported |
| Calendar export | ✅ Done | `.ics` export is available |
| Discord publish/update announcements | ✅ Done | Operation events post to Discord |
| Discord announcement target tracking | ✅ Done | `discord_message_targets` stores per-target routing metadata |
| Member operations index | ✅ Done | Grid/list browsing exists for members |
| Management/dashboard surfaces | ✅ Done | Dashboard, detail, editor, and runtime surfaces are established |

**Operations Domain Structure**
```text
Domain/Operations/
├── Actions/
├── Events/
├── Listeners/
├── Presenters/
├── Queries/
├── Services/
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
| Archive audit UI | ✅ Done | Admin audit surface exists alongside underlying logging |
| Shared Horizon upload controls | ✅ Done in current worktree | Media/archive upload flows now use more native Horizon controls |

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
| Operation runtime webhook support | ✅ Done in current worktree | Runtime sync and per-operation channel orchestration endpoints now exist |
| Logging/watcher utilities | ✅ Done | Runtime monitoring/logging helpers exist |

### 11. Shared UI Components and Shell

| Feature | Status | Notes |
|---------|--------|-------|
| App shell + side navigation | ✅ Done | Shared shell/navigation framework exists |
| Horizon form/control library | ✅ Done | Buttons, inputs, selects, date/time, sections, panels, modals, alerts |
| Expanded native Horizon control set | ✅ Done in current worktree | Badge, checkbox, file field, and form block components now exist |
| Rich text editor | ✅ Done | Shared editor exists and is used by operation/admin flows |
| Global error handling | ✅ Done | Error dialog and shared notification/error plumbing exists |
| Theming support | ✅ Done | Shell applies persisted user theme selection |
| Targeted frontend regression tests | ✅ Done in current worktree | JS tests now cover payout math, side nav state, role colors, and rich text helpers |

---

## Database Schema Summary

**77 migrations** currently exist in the repo.

Key tables and groups:

| Table / Group | Purpose |
|--------------|---------|
| `users` | Core identity, Discord info, RSI info, profile fields, theme, and member stats |
| `personal_access_tokens` | Sanctum token storage |
| `roles`, `permissions`, pivots | Role/permission model |
| `squadrons`, `squadron_members` | Squadron identity and roster state |
| `operations` | Scheduled operations, lifecycle state, visibility, AAR fields, settlement linkage, runtime/AAR attendance buckets, and Discord message target tracking |
| `operation_participants` | Participant roster, assignment, runtime status, sign-off, sync, prep, and attendance fields |
| `operation_roles` | Role definitions per operation, including sort order and required flags |
| `operation_templates` | Saved mission templates |
| `operation_settlements` | Settlement workspace for completed operations, including prep money rows/history |
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

- **In-app notifications** — Discord posting exists, but there is still no real notification center for Horizon itself.
- **Post-release runtime observation** — The new operation runtime and Discord voice orchestration flows are now broad enough that they will benefit from real user feedback after rollout, especially around manager ergonomics and edge-case cleanup behavior.
- **Deployment / CI discipline** — The repo still does not advertise a visible CI/CD pipeline or a checked-in release/deploy workflow.

### Medium Priority

- **Authorization cleanup** — Some older seams still carry more legacy rank-level assumptions than the newer role/permission-first paths.
- **Audit/activity browsing UX** — The archive audit surface exists, but broader admin-facing activity browsing is still thinner than the underlying logging.
- **Advanced operations filtering** — Operations browsing can still grow stronger branch/type/date filters and better saved views.

### Low Priority / Nice-to-Have

- **Bulk admin actions** — Users, squadrons, and operations do not yet have richer batch tooling.
- **API documentation** — There is still no checked-in OpenAPI/Postman style documentation set.
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
| Presenters | 8 |
| Vue Pages | 51 |
| Shared Vue Components | 43 |
| Database Migrations | 77 |
| Bot Commands | 2 |
| Bot Services | 13 |
| Bot Events | 5 |

---

*This document is aligned with the June 15, 2026 repo snapshot and the current active worktree. The major shift from the previous snapshot is that operation runtime, attendance-state hardening, settlement integration, and Horizon-native UI standardization are now concretely represented in both code and tests.*
