# Horizon Platform — Project Status Document

**Last Updated:** February 16, 2026  
**Stack:** Laravel 12 (PHP 8.4) · Vue 3 · Inertia.js · Tailwind CSS v4 · PostgreSQL · Discord.js Bot

---

## Architecture Overview

| Layer | Technology | Location |
|-------|-----------|----------|
| Backend API | Laravel 12 + Sanctum | `backend/` |
| Frontend SPA | Vue 3 + Inertia.js | `backend/resources/js/` |
| Styling | Tailwind CSS v4 | `backend/resources/css/app.css` |
| Discord Bot | Node.js + Discord.js | `bots/horizon-bot/` |
| Database | PostgreSQL | 47 migrations |
| Auth | Discord OAuth → Sanctum tokens | Hybrid (session + API tokens) |

The project uses a **hybrid Inertia + API architecture**:
- **Inertia routes** (`web.php`) serve Vue pages with server-side props
- **API routes** (`api_v1.php`) serve JSON for external/programmatic consumers (bot, future mobile, etc.)

---

## What's Built — Feature Inventory

### 1. Authentication & Identity

| Feature | Status | Notes |
|---------|--------|-------|
| Discord OAuth login | ✅ Done | `DiscordAuthController` redirect/callback flow |
| Sanctum API token auth | ✅ Done | Token issuance on verified login |
| Session-based web auth | ✅ Done | Inertia pages use session guard |
| RSI handle verification | ✅ Done | Code generation → RSI profile scrape → org membership check |
| Bot-assisted verification | ✅ Done | `/verify` slash command via Discord bot |
| Verification code system | ✅ Done | Time-limited codes stored on user |
| Auth audit logging | ✅ Done | `AuthAuditLog` model, failed attempts tracked |
| Force Discord auth middleware | ✅ Done | `ForceDiscordAuth` middleware |
| Max auth age enforcement | ✅ Done | `EnforceMaxAuthAge` middleware |
| RSI verified middleware | ✅ Done | `EnsureRsiVerified` middleware |
| Rank middleware | ✅ Done | `RankMiddleware` — chain-of-command gating |

**Key Files:**
- `App\Http\Controllers\Api\v1\AuthController`
- `App\Http\Controllers\Api\v1\RSIVerificationController`
- `App\Http\Controllers\Api\v1\DiscordAuthController`
- `App\Services\DiscordOAuthService`
- `App\Services\TokenService`

---

### 2. Access Control System

| Feature | Status | Notes |
|---------|--------|-------|
| Role-based access (RBAC) | ✅ Done | `roles` + `permissions` + pivot tables |
| Role hierarchy | ✅ Done | `RoleHierarchy` class (member→director, 9 levels) |
| Permission registry | ✅ Done | `PermissionRegistry` for known permission slugs |
| AccessService (DDD) | ✅ Done | Centralized authorization logic for operations + squadrons |
| UserContext wrapper | ✅ Done | Contextual role/permission checks per user |
| Director override | ✅ Done | Director + Tech Director bypass all checks |
| Policy classes | ✅ Done | `OperationPolicy`, `SquadronPolicy`, `MediaPolicy`, `OperationTemplatePolicy`, `RsiChangeRequestPolicy`, `AdminPolicy` |
| Admin panel gate | ✅ Done | `can:access-admin-panel` middleware |

**Role Hierarchy (highest → lowest):**
```
director (9) → tech_director (8) → grand_admiral (7) → admiral (6) →
wing_commander (5) → commander (4) → cit (3) → lieutenant (2) → member (1)
```

**Key Files:**
- `App\Domain\AccessControl\AccessService`
- `App\Domain\AccessControl\RoleHierarchy`
- `App\Domain\AccessControl\UserContext`

---

### 3. User Profiles & Preferences

| Feature | Status | Notes |
|---------|--------|-------|
| User model with extended fields | ✅ Done | Bio, timezone, callsign, ships, guns, roles, tags, availability |
| `/me` endpoint (API) | ✅ Done | View/update own profile |
| `/me/preferences` endpoint | ✅ Done | Member preferences (separate table) |
| User profile page (Inertia) | ✅ Done | `Member/userpage.vue` — viewable by other members |
| Member directory | ✅ Done | `Member/Index.vue` — paginated, searchable active users |
| RSI handle change requests | ✅ Done | Request → approve/reject flow |
| MeResource | ✅ Done | Normalized API output with role-gated stats |
| Operation stats on profile | ✅ Done | Joined/left early/completed/created/canceled/success/failed counters |
| Profile link from squadron roster | ✅ Done | Click member row → profile page |
| Stats visibility gating | ✅ Done | Joined/left early restricted to commander+ or director roles |

**Key Files:**
- `App\Http\Controllers\Api\v1\MeController`
- `App\Http\Controllers\Api\v1\MePreferenceController`
- `App\Http\Resources\MeResource`
- `App\Http\Controllers\Web\MemberDirectoryController`
- `resources/js/Pages/Member/userpage.vue`
- `resources/js/Pages/Member/Index.vue`

---

### 4. Operations System

This is the most feature-rich module. Operations are the core activity unit (missions, training, events, meetings, roleplay).

| Feature | Status | Notes |
|---------|--------|-------|
| Operation CRUD (API + Inertia) | ✅ Done | Create, read, update, delete |
| State machine transitions | ✅ Done | `draft → published → in_progress → completed/canceled` |
| Operation types | ✅ Done | `gameplay_type` field (renamed from `operation_kind`) |
| Operation branches | ✅ Done | `industries`, `defence`, `frontiers`, `lifelines` |
| Visibility system | ✅ Done | `open` / squadron-scoped |
| Operation strictness | ✅ Done | Configurable strictness level |
| Start/operation locations | ✅ Done | Separate location fields |
| Extended description | ✅ Done | Rich text `extended_description` (migrated from notes) |
| Completion outcome | ✅ Done | `success` / `failure` / `partial` on completion |
| Cancellation reason | ✅ Done | Stored when operation is canceled |
| Slot system | ✅ Done | JSON `slots` field for mission structure |
| Operation roles | ✅ Done | `OperationRole` model with CRUD actions |
| Participants system | ✅ Done | Join/leave/slot assignment |
| Participant stats | ✅ Done | Update stats per participant |
| Operation templates | ✅ Done | DB-backed templates (personal/squadron/global scope) |
| Template CRUD API | ✅ Done | Full REST with policy-based auth |
| Template UI in editor | ✅ Done | Load/apply/save templates from MissionEditorForm |
| Calendar export (.ics) | ✅ Done | `OperationCalendarController` |
| Discord announcement | ✅ Done | Events fire on publish/update → Discord webhook |
| Discord message ID tracking | ✅ Done | `discord_message_id` column for edit-in-place |
| Operation dashboard | ✅ Done | `OperationDashboard.vue` — restricted to lieutenant+ or director |
| Operations member index | ✅ Done | `OperationsIndex.vue` — grid view for all members |
| Operation detail view | ✅ Done | `MissionShow.vue` + `MissionShowPanel.vue` |
| Operation editor form | ✅ Done | `MissionEditorForm.vue` — full-featured editor |
| Inline refresh after actions | ✅ Done | Inertia reload after save/delete/transition |
| Create from template | ✅ Done | Dashboard passes `prefillTemplateId` |
| Operation accordion | ✅ Done | `OperationAccordion.vue` for list display |
| Operation drawer/modal | ✅ Done | `OperationDrawer.vue`, `OperationModal.vue` |
| DB indexes for performance | ✅ Done | Indexes on operations table |

**Domain Architecture:**
```
Domain/Operations/
├── Actions/        (11 action classes: Create, Update, Cancel, Join, Leave, etc.)
├── Events/         (OperationPublished, OperationUpdated)
├── Listeners/      (SendOperationPublishedToDiscord, SendOperationUpdatedToDiscord)
├── Presenters/     (OperationPresenter, ParticipantPresenter, RolePresenter, etc.)
├── Queries/        (OperationQuery)
├── Services/       (OperationService, CalendarService, RoleService, ParticipantService, ShowDataService)
└── States/         (Draft, Published, InProgress, Completed, Canceled + base OperationState)
```

---

### 5. Squadrons System

| Feature | Status | Notes |
|---------|--------|-------|
| Squadron CRUD (API + Admin) | ✅ Done | Create, read, update, delete |
| Squadron members management | ✅ Done | Add, update, remove members |
| Membership statuses | ✅ Done | Active, left, removed |
| Squadron roles | ✅ Done | Leader, lieutenant, member |
| Leader assignment | ✅ Done | `SquadronLeaderController` |
| Lieutenant promotion/demotion | ✅ Done | Max 2 lieutenants per squadron |
| Squadron identity fields | ✅ Done | Emblem, description, motto, etc. |
| Emblem upload | ✅ Done | `SquadronManageController@uploadEmblem` |
| Squadron settings update | ✅ Done | Leader/lieutenant can update settings |
| Branch & division fields | ✅ Done | Added to squadrons table |
| Recruitment propaganda | ✅ Done | Field on squadrons table |
| Squadron listing page | ✅ Done | `Squadrons/Index.vue` |
| Squadron detail page | ✅ Done | `Squadrons/Show.vue` with expanded panel |
| Squadron roster | ✅ Done | `SquadronRoster.vue` with member rows |
| Viewer status component | ✅ Done | `SquadronViewerStatus.vue` |
| Admin rank promote/demote | ✅ Done | `SquadronRankController` |

**Key Files:**
- `App\Domain\Squadrons\SquadronService`
- `App\Domain\Squadrons\MembershipService`
- `App\Http\Controllers\Web\SquadronManageController`
- `App\Http\Controllers\Api\v1\SquadronController`
- `App\Http\Controllers\Api\v1\SquadronMemberController`

---

### 6. Media System

| Feature | Status | Notes |
|---------|--------|-------|
| Polymorphic media model | ✅ Done | `Media` model with `mediable` morph |
| Media collections | ✅ Done | Avatar, operation image, squadron emblem, etc. |
| Upload action | ✅ Done | `UploadMedia` action with validation |
| Attach/delete actions | ✅ Done | `AttachMedia`, `DeleteMedia` |
| Media service | ✅ Done | `MediaService` for business logic |
| Media policy | ✅ Done | Collection-based permission rules |
| Web media routes | ✅ Done | Upload, list, show, update, delete, download |
| API media routes | ✅ Done | Index, show, upload, delete |
| Media picker modal | ✅ Done | `MediaPickerModal.vue` — reusable picker component |
| Admin media library | ✅ Done | `admin/media` route |

---

### 7. Admin Panel

| Feature | Status | Notes |
|---------|--------|-------|
| Admin dashboard | ✅ Done | `Admin/Dashboard.vue` — director/tech_director only |
| User management | ✅ Done | Update user, update user roles |
| Squadron management (admin) | ✅ Done | Full CRUD + member management |
| Role management | ✅ Done | Create, update, delete roles |
| Media library (admin) | ✅ Done | Browse/manage all media |
| Admin rank promotions | ✅ Done | Promote/demote squadron members |

**Key Files:**
- `App\Http\Controllers\Web\AdminController` (19KB — large controller)
- `resources/js/Pages/Admin/Dashboard.vue`

---

### 8. Discord Bot (`bots/horizon-bot/`)

| Feature | Status | Notes |
|---------|--------|-------|
| Bot framework | ✅ Done | Discord.js with event/command structure |
| `/verify` command | ✅ Done | RSI verification via bot |
| `/status` command | ✅ Done | Check platform status |
| Guild member add event | ✅ Done | Welcome new members |
| Guild member update event | ✅ Done | Track nickname/role changes |
| Nickname sync cron | ✅ Done | `nicknameCron.js` + `nicknameService.js` |
| Operation webhook service | ✅ Done | Post operation announcements to Discord |
| Log watcher | ✅ Done | `logWatcher.js` for monitoring |
| Welcome service | ✅ Done | `welcomeService.js` for onboarding |
| Bot secret verification | ✅ Done | `VerifyBotSecret` middleware on bot API routes |

---

### 9. Shared UI Components

| Component | Purpose |
|-----------|---------|
| `SideNav.vue` | Main navigation sidebar |
| `AppShell.vue` | Layout wrapper |
| `HorizonButton.vue` | Styled button |
| `HorizonCard.vue` | Card container |
| `HorizonInput.vue` | Form input |
| `HorizonSelect.vue` | Dropdown select |
| `HorizonDateTimePicker.vue` | Date/time picker |
| `HorizonRichTextEditor.vue` | Rich text editor (22KB) |
| `HorizonAlert.vue` | Alert banner |
| `HorizonErrorDialog.vue` | Global popup error dialog |
| `HorizonModal.vue` | Modal dialog |
| `HorizonSection.vue` | Section wrapper |
| `HorizonStat.vue` | Stat display |
| `HorizonContainer.vue` | Container wrapper |
| `HorizonPanel.vue` | Panel wrapper |
| `MediaPickerModal.vue` | Media selection modal |
| `MissionCard.vue` | Operation card for grids |
| `MissionGrid.vue` | Grid layout for operations |
| `ProgressPill.vue` | Progress indicator |
| `RoleSlotCard.vue` | Role/slot display |
| `SquadronBadge.vue` | Squadron badge |
| `DataTableLite.vue` | Lightweight data table |
| `roleColors.js` | Role color mapping utility |

---

### 10. Infrastructure & Cross-Cutting

| Feature | Status | Notes |
|---------|--------|-------|
| API response helper | ✅ Done | `ApiResponse` helper for consistent JSON |
| Discord logger service | ✅ Done | `DiscordLogger` for webhook logging |
| Domain event system | ✅ Done | `DomainEventServiceProvider` wiring events → listeners |
| Error page | ✅ Done | `Error.vue` — custom error display |
| Verify page | ✅ Done | `Verify.vue` — RSI verification flow UI |
| Welcome page | ✅ Done | `Welcome.vue` — landing/home |
| Fallback 404 route | ✅ Done | Catches unmatched routes |
| Ziggy route helper | ✅ Done | `ziggy.js` for named routes in Vue |
| Axios interceptor | ✅ Done | Auto-shows error dialog for API failures |
| Global error dialog | ✅ Done | `window.hzNotifyError()` dispatches `hz:error` event |

---

## Database Schema Summary

**47 migrations** covering:

| Table | Purpose |
|-------|---------|
| `users` | Core user table with Discord identity, RSI handle, rank, extended profile fields, operation stat counters |
| `personal_access_tokens` | Sanctum API tokens |
| `roles` | Named roles with slugs |
| `permissions` | Named permissions with slugs |
| `permission_role` | Pivot: which permissions belong to which roles |
| `role_user` | Pivot: which roles are assigned to which users |
| `squadrons` | Squadron identity, settings, leader, branch, division, emblem, propaganda |
| `squadron_members` | Membership records with status, role, join/leave dates |
| `operations` | Full operation data: title, description, schedule, type, branch, status, slots, locations, outcome |
| `operation_participants` | Who joined which operation, slot assignment |
| `operation_roles` | Custom roles defined per operation |
| `operation_templates` | Saved operation templates (personal/squadron/global) |
| `media` | Polymorphic media storage (avatars, operation images, emblems) |
| `member_preferences` | Per-user preferences |
| `rsi_change_requests` | RSI handle change request queue |
| `failed_attempts` | Verification failure tracking |
| `auth_audit_logs` | Authentication event logging |
| `cache` | Laravel cache table |
| `jobs` | Laravel queue jobs table |

---

## What Needs Work / Known Gaps

### High Priority

- **Notifications system** — No in-app notification system exists yet. Operations publish to Discord but there's no web notification center.
- **Testing** — No automated test suite. `tests/` directory exists but is essentially empty. Unit tests, feature tests, and integration tests are all needed.
- ~~**README is outdated**~~ — **Resolved.** Updated to reflect current architecture, features, stack, and setup instructions.

### Medium Priority

- ~~**AdminController is oversized**~~ — **Resolved.** Split into 4 focused controllers: `AdminController` (dashboard only), `AdminUserController`, `AdminSquadronController`, `AdminRoleController` under `Web/Admin/`.
- **Authorization migration** — There's an open intent to move from `rank_level`-based gating to role-based checks using `RoleHierarchy` thresholds (lieutenant+/commander+) consistently across all UI and backend gates.
- ~~**Operation update permissions for global ops**~~ — Fixed
`canUpdateOperation` currently returns `false` for operations without a `squadron_id` (global ops) unless user is director. The creator of a global op who is lieutenant+ cannot edit it.
- **UEX data ingestion** — Mentioned in the README as a future phase, not started.
- **Mobile/responsive UI audit** — No evidence of dedicated mobile optimization pass.

### Low Priority / Nice-to-Have

- **Activity feed / audit trail UI** — Auth audit logs exist in DB but no admin UI to browse them.
- **Search/filter on operations dashboard** — Basic listing exists but advanced filtering (by branch, type, date range, squadron) could be enhanced.
- **Bulk actions in admin** — No bulk user/squadron/operation management.
- **API documentation** — No Swagger/OpenAPI docs or Postman collection checked into the repo.
- **Rate limiting refinement** — Some API routes have throttle middleware, but coverage isn't comprehensive.
- **Soft deletes cleanup** — Operations had soft deletes added then removed; ensure no orphaned logic remains.
- **CI/CD pipeline** — No deployment automation or CI configuration in the repo.
- **Environment/deployment docs** — `ONBOARDING.md` exists but deployment to production isn't documented.

---

## File Counts Summary

| Area | Count |
|------|-------|
| Models | 14 |
| Controllers (Web) | 11 |
| Controllers (API v1) | 17 |
| Controllers (Admin) | 4 |
| Middleware | 6 |
| Form Requests | 19+ |
| Policies | 6 |
| Domain Actions | 14+ |
| Domain Services | 10+ |
| Domain Presenters | 8+ |
| Vue Pages | 12 |
| Vue Components | 32+ shared, 11+ page-specific |
| Database Migrations | 47 |
| Bot Commands | 2 |
| Bot Services | 7 |
| Bot Events | 4 |

---

*This document is a snapshot of the codebase as of February 16, 2026. Update as features are added or completed.*
