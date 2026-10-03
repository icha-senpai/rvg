# Horizon 2 Foundation Design Specification

**Status:** Proposed foundation  
**Purpose:** Define the architectural, technical, and implementation rules for the Horizon 2 rebuild before feature development begins.  
**Primary audience:** Icha, Codex, and future contributors.  
**Relationship to Horizon 1:** Horizon 1 remains the behavioral and product reference. Horizon 2 is a clean-sheet implementation and must not be treated as an in-place refactor.

---

## 1. Executive Summary

Horizon 2 is a ground-up rebuild of the Horizon Interstellar organization platform.

The rebuild is not being undertaken because Horizon 1 cannot support its expected traffic. Horizon 1 is already operational and technically sufficient for its user count. Horizon 2 exists to create a cleaner long-term architecture, stronger real-time behavior, tighter Discord integration, a more deliberate UI system, and a foundation that can support web, installable PWA, and desktop experiences without repeating Horizon 1's rushed architectural decisions.

The central architectural decision is:

> **SpaceTimeDB is the authoritative application state and business-logic runtime.**

All business-critical mutations are implemented as Rust reducers in SpaceTimeDB. The website and Discord bot are clients of the same authoritative state rather than separate systems synchronized through ad-hoc APIs.

The proposed technology foundation is:

| Layer | Technology |
|---|---|
| Web UI | Svelte 5 + TypeScript |
| Application shell/routing | SvelteKit |
| Styling | Modern vanilla CSS |
| Mobile-first installable experience | Progressive Web App |
| Desktop | Tauri 2 using the same Svelte UI |
| Core backend | SpaceTimeDB |
| Core backend language | Rust |
| Discord bot | Rust + Twilight |
| Identity provider | Citizen iD |
| Media/object storage | Cloudflare R2 |
| Rich text | TipTap initially, behind a Horizon abstraction |
| End-to-end web testing | Playwright |
| Source design | Penpot |

Horizon 2 should deliberately contain as few independent sources of truth as possible.

---

# 2. Design Goals

## 2.1 Primary Goals

Horizon 2 must:

1. Make SpaceTimeDB the canonical source of application state.
2. Allow the web client and Discord bot to manipulate the same domain objects through the same reducers.
3. Provide real-time operation, roster, runtime, squadron, and ledger updates through subscriptions.
4. Support creating, joining, managing, starting, and running operations from both Horizon and Discord where appropriate.
5. Preserve strict server authority over permissions, state transitions, financial actions, and organizational data.
6. Provide a distinct Horizon visual identity based on Pheried's Penpot designs.
7. Support installable PWA behavior on desktop and mobile browsers.
8. Support a native desktop application through Tauri without creating a second UI codebase.
9. Maintain a clean separation between domain logic, presentation, platform integration, and third-party services.
10. Remain understandable to AI coding agents and future developers through explicit architecture and repository rules.

## 2.2 Secondary Goals

Horizon 2 should:

- Minimize manually maintained API DTOs.
- Generate client bindings from SpaceTimeDB schema definitions.
- Avoid duplicate business rules between the website, bot, and future clients.
- Keep third-party dependencies intentionally small.
- Make the visual system reusable rather than reproducing Penpot screens as giant SVGs.
- Make individual subsystems replaceable where practical.
- Preserve a path to future native mobile applications if the PWA becomes insufficient.
- Keep Horizon 1 available as a behavioral reference throughout development.

---

# 3. Explicit Non-Goals

The foundation must not attempt to solve every possible future requirement.

The following are intentionally out of scope for the initial foundation:

- Rebuilding Horizon 1 feature-for-feature before validating the new architecture.
- Building a generic page builder.
- Turning Penpot exports into runtime pages automatically.
- Creating a generic rich-text framework comparable to TipTap or ProseMirror.
- Native Android and iOS applications during the first implementation phase.
- Reimplementing Citizen iD.
- Storing large binary media directly in SpaceTimeDB.
- Duplicating SpaceTimeDB domain rules in the frontend or Discord bot.
- Designing for millions of users or hyperscale requirements.
- Rewriting working third-party functionality purely for language purity.
- Requiring every line in the entire system to be Rust.

---

# 4. Architectural Principles

## 4.1 One Authoritative State

SpaceTimeDB is authoritative for Horizon domain state.

Examples include:

- Horizon users
- roles
- permissions
- squadrons
- squadron memberships
- operations
- operation roles
- participants
- runtime attendance
- operation state transitions
- settlements
- ledgers
- transfers
- promotions
- audit records
- Discord integration jobs
- UEX-derived application data where appropriate

Neither Svelte nor the Discord bot may become a competing source of truth.

## 4.2 Reducers Are the Mutation Boundary

All meaningful domain writes must pass through SpaceTimeDB reducers.

Clients may request a state change. They may not decide whether the change is valid.

Example:

```text
Discord user clicks "Join Operation"
        |
        v
Rust Discord bot receives interaction
        |
        v
join_operation reducer
        |
        +-- resolve Horizon identity
        +-- verify operation is joinable
        +-- verify capacity
        +-- verify permissions
        +-- create participant once
        |
        v
commit
```

The Svelte client must use the same `join_operation` reducer.

There must not be separate `discord_join_operation` and `web_join_operation` business paths unless the domain behavior is genuinely different.

## 4.3 Clients Observe State Instead of Synchronizing Copies

The website and Discord bot should subscribe to the state they require.

The system should prefer:

```text
Discord ----\
             > SpaceTimeDB <---- Svelte
Desktop ----/
```

over:

```text
Discord -> HTTP API -> app server -> database -> websocket -> website
```

Discord and Horizon are not intended to "sync with each other." They are intended to participate in the same authoritative system.

## 4.4 Business Logic Lives in Rust

Authorization, operation transitions, transfer rules, settlement rules, capacity checks, promotion rules, and other domain constraints belong in SpaceTimeDB Rust code.

The frontend may reproduce validation for user experience, but frontend validation is advisory only.

## 4.5 Presentation Remains Replaceable

The Svelte frontend must depend on generated SpaceTimeDB bindings and stable Horizon client abstractions rather than backend implementation details.

If the frontend framework is ever replaced, the SpaceTimeDB domain should not require a rewrite.

---

# 5. High-Level System Architecture

```text
                           Citizen iD
                               |
                               v
                    +----------------------+
                    |   Horizon Web/PWA    |
                    | Svelte 5 + SvelteKit |
                    | TypeScript + CSS     |
                    +----------+-----------+
                               |
                      generated TS bindings
                      subscriptions/reducers
                               |
                               v
                    +----------------------+
                    |     SpaceTimeDB      |
                    |        Rust          |
                    |                      |
                    |  Horizon authority   |
                    +----------+-----------+
                               |
                     generated Rust bindings
                               |
                               v
                    +----------------------+
                    |   Horizon Discord    |
                    |   Rust + Twilight    |
                    +----------+-----------+
                               |
                               v
                            Discord

Optional supporting service:

                    +----------------------+
                    |    Horizon Edge      |
                    |     Rust + Axum      |
                    |                      |
                    | R2 signing / external|
                    | HTTP glue only       |
                    +----------------------+

Object storage:

                    +----------------------+
                    |   Cloudflare R2      |
                    +----------------------+

Desktop:

                    +----------------------+
                    |      Tauri 2         |
                    | same Svelte UI       |
                    | Rust native bridge   |
                    +----------------------+
```

---

# 6. Repository Structure

Horizon 2 must be developed beside Horizon 1 rather than by rewriting the existing application in place.

Recommended top-level structure:

```text
rvg/
├── backend/                  # Horizon 1 reference
├── bots/                     # Horizon 1 reference
├── ...
│
└── horizon-2/
    ├── AGENTS.md
    ├── README.md
    ├── docs/
    │   ├── architecture/
    │   ├── domains/
    │   ├── ui/
    │   └── decisions/
    │
    ├── apps/
    │   ├── web/
    │   │   └── Svelte 5 / SvelteKit
    │   │
    │   └── desktop/
    │       └── Tauri shell/configuration
    │
    ├── services/
    │   ├── spacetime/
    │   │   └── Rust SpaceTimeDB module
    │   │
    │   ├── discord/
    │   │   └── Rust + Twilight
    │   │
    │   └── edge/
    │       └── optional Rust + Axum service
    │
    ├── packages/
    │   ├── design/
    │   └── generated/
    │
    └── tooling/
```

The existing Horizon 1 code must remain available for Codex to inspect when reproducing behavior.

Horizon 1 architecture should not be copied mechanically.

---

# 7. SpaceTimeDB Foundation

## 7.1 Module Organization

The SpaceTimeDB module should be organized by domain rather than by generic technical categories.

Recommended structure:

```text
services/spacetime/src/
├── lib.rs
├── access/
├── users/
├── squadrons/
├── operations/
│   ├── runtime/
│   └── settlement/
├── ledger/
├── promotions/
├── archive/
├── uex/
├── discord/
└── audit/
```

Each domain may contain:

- tables
- reducers
- views
- authorization helpers
- presentation/read-model helpers
- tests

Avoid a giant `reducers.rs` or `tables.rs` containing the entire platform.

## 7.2 Tables

Tables should model domain state, not mirror Horizon 1 migrations one-for-one.

Existing Horizon 1 schema is reference material, not the target schema.

New schema design should prefer explicit domain relationships and stable identifiers.

## 7.3 Reducers

Reducers must:

1. Resolve caller identity.
2. Authorize the action.
3. Validate domain state.
4. Perform all related writes atomically.
5. Produce audit records where appropriate.
6. Produce Discord jobs where external Discord effects are required.

Reducers should be idempotent where repeated client delivery is plausible.

For example, joining an operation should not create duplicate participant records when the same interaction is retried.

## 7.4 Views / Read Models

Sensitive tables should not be made broadly public for convenience.

Views should expose exactly the data a client is allowed to observe.

Examples:

- member-visible operation summary
- operation-manager runtime view
- personal ledger view
- squadron ledger view
- organization treasury view
- member directory view
- Discord bot operational view

Authorization must not depend on clients hiding fields.

## 7.5 Generated Bindings

SpaceTimeDB-generated bindings are part of the contract between the server and clients.

Generate:

- TypeScript bindings for Svelte
- Rust bindings for the Discord bot

Generated files must not be manually edited.

CI should fail if committed/generated bindings are stale relative to the module schema.

---

# 8. Identity and Authorization

## 8.1 Citizen iD

Citizen iD is the external identity provider.

Horizon must map a stable Citizen iD/OIDC subject to a local Horizon user.

Citizen iD answers:

> Who is this person and what verified external identity do they control?

Horizon answers:

> What can this person do inside Horizon?

These responsibilities must remain separate.

## 8.2 Local Horizon User

A Horizon user should contain Horizon-specific state such as:

- Horizon user ID
- Citizen iD subject
- RSI handle snapshot/reference
- Discord identity mapping where available
- callsign
- profile data
- rank
- roles
- squadron relationships
- Horizon preferences
- Horizon audit metadata

The Citizen iD subject should be treated as an external identity identifier, not the primary domain key used everywhere internally.

## 8.3 Authorization

Authorization occurs in SpaceTimeDB.

Examples:

```text
can_create_operation
can_manage_operation
can_manage_squadron
can_view_ledger
can_transfer_funds
can_finalize_settlement
can_manage_promotions
```

Frontend visibility rules are user-experience rules only.

A hidden button is not authorization.

## 8.4 Discord Identity

The bot must resolve a Discord account to the corresponding Horizon user before invoking user-level reducers.

Discord IDs must not implicitly grant Horizon authority.

---

# 9. Discord Architecture

## 9.1 Technology

The Horizon 2 Discord bot will be:

```text
Rust + Twilight
```

The bot is a long-running Discord Gateway client and an authenticated SpaceTimeDB client.

## 9.2 Bot Responsibility

The bot is an I/O adapter.

It may:

- receive Discord interactions
- observe Discord guild events
- observe voice state
- create/edit/delete messages
- create/manage Discord channels
- assign/remove Discord roles
- send DMs
- translate Discord events into reducer calls
- execute durable Discord jobs emitted by SpaceTimeDB

The bot must not become the owner of Horizon domain rules.

## 9.3 Discord -> Horizon

Incoming Discord actions call the same reducers used by the web application.

Examples:

```text
Join button
-> join_operation

Leave button
-> leave_operation

Start Operation
-> start_operation

Complete Operation
-> complete_operation

Voice lobby sync
-> sync_operation_presence
```

## 9.4 Horizon -> Discord

Durable external effects should use a Discord outbox/job table.

Example conceptual record:

```text
DiscordJob
├── id
├── kind
├── payload
├── status
├── attempts
├── created_at
├── started_at
├── completed_at
└── error
```

Typical states:

```text
pending
processing
completed
failed
```

Example:

```text
publish_operation reducer
        |
        +-- operation.status = published
        +-- insert DiscordJob::PublishOperation
        |
        v
atomic commit

Rust bot subscription
        |
        v
send Discord announcement
        |
        v
acknowledge_discord_job
```

This design allows retries and auditing when Discord is unavailable.

## 9.5 Discord Operation Interface

The target Discord experience may include:

```text
HORIZON OPERATION

Jumptown Security Patrol
Saturday - 20:00

Commander: Icha
18 / 24 joined

[ Join Operation ]
[ Leave ]
[ View Roster ]
[ Open Horizon ]
```

Authorized operation managers may receive additional controls such as:

```text
[ Start Operation ]
[ Sync Lobby ]
[ Sync Channels ]
[ Add Walk-In ]
[ Complete Operation ]
```

Buttons should invoke reducers rather than bot-local business logic.

---

# 10. Frontend Foundation

## 10.1 Technology

The Horizon web client will use:

```text
Svelte 5
SvelteKit
TypeScript
Modern vanilla CSS
```

Tailwind is intentionally excluded from the initial Horizon 2 foundation.

The decision may be revisited later, but the default styling model is component-oriented CSS.

## 10.2 SvelteKit Role

SvelteKit provides:

- routing
- page composition
- application shell
- build tooling
- PWA integration foundation
- static/client application structure

Business logic must not drift into SvelteKit server routes.

The preferred model is a client application speaking directly to SpaceTimeDB through generated bindings.

## 10.3 Frontend State

SpaceTimeDB is the canonical remote state.

Do not create a second client-side application store that duplicates SpaceTimeDB state without a concrete reason.

Local UI state is appropriate for:

- open/closed drawers
- active tabs
- temporary forms
- transient editor state
- unsaved drafts
- local preferences not yet committed

Canonical domain state should remain subscription-driven.

## 10.4 Reducer Calls

Frontend actions should call generated reducers.

Example:

```text
Svelte button
-> generated create_operation reducer binding
-> SpaceTimeDB
-> subscription update
-> UI reacts
```

Avoid hand-maintained REST APIs for internal Horizon domain actions.

---

# 11. Styling and Design System

## 11.1 Penpot Is the Visual Specification

Pheried's Penpot designs are the visual source of truth.

They should not be converted into giant runtime SVG pages.

The implementation model is:

```text
Penpot
  |
  +-- visual layout reference
  +-- SVG artwork
  +-- icons
  +-- logos
  +-- measurements
  +-- colors
  +-- typography
  |
  v
Horizon design system
  |
  v
real responsive HTML/CSS components
```

## 11.2 CSS Responsibilities

Modern CSS should handle:

- CSS Grid
- Flexbox
- responsive layout
- container queries
- media queries
- custom properties
- gradients
- shadows
- clip paths
- masks
- pseudo-elements
- transitions
- animations
- chamfers
- HUD framing
- responsive component composition

## 11.3 SVG Responsibilities

Use SVG for:

- Horizon logo
- rank insignia
- emblems
- icons where appropriate
- complex decorative artwork
- geometry that is materially cleaner and more accurate as SVG

Do not use whole-page SVG exports as application layout.

## 11.4 Visual Geometry

Horizon components may use layered CSS clipping rather than normal borders when angled geometry is required.

Example responsibilities:

```text
outer wrapper
-> drop shadow

outer clipped layer
-> visual edge/border

inner clipped layer
-> fill

real HTML content
-> text, buttons, controls
```

## 11.5 Design Tokens

Create a central token file.

Recommended categories:

```css
:root {
    /* backgrounds */
    --color-bg-0: ...;
    --color-bg-1: ...;

    /* Horizon accents */
    --color-accent-blue: ...;
    --color-accent-yellow: ...;
    --color-accent-red: ...;

    /* typography */
    --color-text-primary: ...;
    --color-text-muted: ...;

    /* geometry */
    --radius-sm: ...;
    --radius-md: ...;
    --radius-lg: ...;

    --cut-sm: ...;
    --cut-md: ...;
    --cut-lg: ...;

    /* spacing */
    --space-1: ...;
    --space-2: ...;
    --space-3: ...;
    --space-4: ...;

    /* effects */
    --shadow-card: ...;
    --glow-blue: ...;
}
```

Penpot values should be normalized into these tokens rather than copied randomly into components.

## 11.6 Component Primitives

Build reusable Horizon primitives early.

Initial set:

```text
HorizonShell
SideNav
NavItem
Panel
Card
ShipCard
Notice
Button
IconButton
Input
Select
Checkbox
Modal
Drawer
Accordion
Tabs
Popover
Tooltip
Toast
CommandPalette
Badge
Divider
EmptyState
LoadingState
```

Feature pages should compose these primitives rather than inventing new geometry repeatedly.

## 11.7 Responsive Strategy

Pheried's desktop designs are the high-fidelity reference.

The implementation must remain responsive.

Suggested initial design ranges:

```text
Wide desktop: >= 1600px
Desktop:      1200-1599px
Tablet:       768-1199px
Mobile:       < 768px
```

Prefer container queries for reusable components when behavior depends on container width rather than viewport width.

Absolute positioning is allowed for deliberate HUD overlays and decorative elements.

Primary page structure should prefer Grid/Flex rather than hardcoded pixel coordinates.

---

# 12. Rich Text

TipTap should be used initially through a Horizon-owned wrapper component.

Example:

```text
RichTextEditor
```

The rest of the application must not depend directly on TipTap-specific implementation details wherever practical.

Canonical content should use a structured representation suitable for long-term storage rather than relying solely on rendered HTML.

A future Rust-native Horizon editor may be explored independently, but it is not a foundation requirement.

Initial editor scope should prioritize Horizon needs:

- paragraphs
- headings
- bold
- italic
- underline
- strike
- links
- lists
- quotes
- images/media
- optional tables
- Horizon references/mentions later

Horizon is an English-language product. Complex-script and RTL editor behavior are not initial product requirements.

---

# 13. PWA Strategy

The web client should be installable as a Progressive Web App.

Initial PWA goals:

- installable app manifest
- Horizon application icons
- standalone display mode
- cached application shell
- graceful startup without network
- explicit SpaceTimeDB connection status
- reconnection after network loss
- web push notifications where supported

The PWA should be the initial mobile experience.

Native Android/iOS applications should not be built until a concrete PWA limitation justifies them.

Offline mode should not pretend writes succeeded.

Example:

```text
No network
-> Horizon shell still opens
-> cached/reference UI may render
-> status shows Disconnected
-> write actions are unavailable or pending explicitly
-> reconnect
-> resubscribe
-> authoritative state wins
```

---

# 14. Desktop Strategy

Tauri 2 is the preferred desktop shell.

The desktop application should reuse the Svelte frontend rather than maintain a second UI.

Rust-native Tauri commands may provide capabilities such as:

- native notifications
- system tray
- startup integration
- filesystem access
- secure token storage
- local log inspection
- future Star Citizen integrations
- deep links
- update handling

Platform-specific native features should be isolated behind explicit adapters.

Do not scatter platform checks throughout feature components.

---

# 15. Media and R2

Large binary assets must not be stored directly in SpaceTimeDB.

Cloudflare R2 is the preferred object store.

SpaceTimeDB stores metadata such as:

```text
media_id
owner_id
collection
object_key
public/private status
mime_type
width
height
size
created_at
```

The client must never receive permanent R2 credentials.

If signed upload URLs or other trusted HTTP operations are required, create a narrow Rust edge service.

---

# 16. Horizon Edge Service

A separate edge service is optional and should exist only where SpaceTimeDB is not the right execution environment.

Preferred implementation:

```text
Rust + Axum
```

Possible responsibilities:

- signed R2 upload/download URLs
- third-party HTTP integrations
- OAuth glue if required by final Citizen iD integration
- webhook endpoints
- integration tasks unsuitable for SpaceTimeDB reducers

It must not contain duplicated Horizon business logic.

The edge service should remain small enough that deleting or replacing it is straightforward.

---

# 17. UEX Integration

UEX synchronization must not be copied blindly from Horizon 1.

Initial design options:

1. Rust edge worker fetches UEX and calls SpaceTimeDB reducers.
2. SpaceTimeDB procedures perform the external fetch if the relevant API is stable enough at implementation time.

The first implementation should prefer reliability and observability over architectural purity.

UEX data should be treated as imported reference data, not the primary authority for Horizon domain state.

---

# 18. Reconnect and Failure Behavior

Clients must assume networks fail.

Required connection states:

```text
disconnected
connecting
authenticating
subscribing
ready
error
```

The UI must make connection status observable.

The application must be tested against:

- Wi-Fi loss
- browser sleep
- desktop sleep
- PWA suspension
- SpaceTimeDB restart
- token expiration
- reconnect
- duplicate interaction delivery
- Discord API outage

Reducers that may receive retries should be designed to avoid duplicate state changes.

---

# 19. Auditability

Important state changes should generate audit records.

Examples:

- operation created
- operation published
- operation started
- operation completed
- participant added/removed
- manual attendance changes
- settlement finalized/reopened
- fund transfer
- transfer reversal
- promotion offer
- permission/rank changes
- Discord external job failure

Audit records should identify:

- actor
- action
- target
- timestamp
- source where useful (`web`, `discord`, `admin`, etc.)
- relevant correlation/job identifier

`source` is audit metadata, not a different domain implementation.

An operation created from Discord and one created from the web are both simply Horizon operations.

---

# 20. Testing Strategy

## 20.1 Rust / SpaceTimeDB

Every important reducer should have tests for:

- authorized success
- unauthorized caller
- invalid state transition
- duplicate request/idempotency where relevant
- atomicity of related writes
- financial invariants
- permission boundaries

Required baseline checks:

```text
cargo fmt --check
cargo clippy
cargo test
```

## 20.2 Svelte

Frontend testing should cover:

- component behavior
- form validation
- permission-driven presentation
- subscription-driven updates
- connection state behavior

## 20.3 End-to-End

Playwright should test major web workflows.

Initial critical E2E flow:

```text
login
create operation
publish operation
join operation
leave operation
runtime view
complete operation
```

## 20.4 Discord Integration

Test:

- Discord interaction -> reducer
- SpaceTimeDB Discord job -> bot action
- job acknowledgement
- job retry/failure
- duplicate interaction handling

## 20.5 Visual Regression

Use screenshot comparisons for high-value Penpot-derived pages.

The goal is not pixel perfection at every viewport.

The goal is to detect accidental drift from the Horizon design language.

---

# 21. Codex / Agent Development Rules

Create `horizon-2/AGENTS.md`.

It should remain concise and point to detailed docs.

Core rules:

```text
- Read relevant architecture/domain docs before modifying a subsystem.
- Do not modify Horizon 1 unless explicitly asked.
- SpaceTimeDB reducers own domain mutations.
- Do not duplicate authorization in clients as a security boundary.
- Reuse generated bindings; never hand-edit generated files.
- Reuse existing Horizon UI primitives before creating new visual patterns.
- Vanilla CSS is the default styling system.
- Do not introduce Tailwind without an architecture decision.
- Do not add dependencies without explaining why existing capabilities are insufficient.
- Keep bot business logic thin; domain rules belong in SpaceTimeDB.
- Keep edge-service business logic thin.
- Run formatter, linter, tests, and relevant build before considering work complete.
- Preserve accessibility and keyboard behavior for interactive components.
```

Codex should receive tasks phrased around outcomes and existing architecture rather than implementation guesses.

Example:

> Implement the operation roster page from `docs/ui/operation-roster.md`. Reuse HorizonPanel, HorizonCard, SideNav, and design tokens. Subscribe through generated SpaceTimeDB bindings. Do not add dependencies. Match the Penpot desktop reference while providing tablet/mobile compositions. Run all relevant checks.

---

# 22. Dependency Policy

Horizon 2 should favor a small dependency surface.

Before adding a dependency, determine:

1. Is the capability already available in the platform/runtime?
2. Is the capability small enough to implement safely in Horizon?
3. Is the dependency mature and actively maintained?
4. Does it materially reduce complexity?
5. Does it work on every required target?
6. Does it introduce a second competing state or styling system?

Avoid dependencies solely to save a few lines of straightforward code.

Do not recreate highly complex mature infrastructure merely to eliminate a dependency.

---

# 23. Initial Vertical Slice

Do not begin by porting Horizon 1 feature-by-feature.

Build one deliberately demanding vertical slice that proves the architecture.

## Required Prototype

### Identity

- Citizen iD login
- map external subject to Horizon user
- authenticated SpaceTimeDB connection

### Web/PWA

- Svelte application shell
- Horizon design tokens
- SideNav
- one Penpot-derived high-fidelity page
- installable PWA

### Operation Domain

- create operation
- publish operation
- operation list
- operation detail
- join
- leave
- participant count
- operation status transition

### Discord

- Rust/Twilight bot connects to SpaceTimeDB
- publish operation announcement
- Discord `Join Operation` button
- Discord `Leave` button
- participant count updated from SpaceTimeDB
- operation edited on Horizon updates Discord message
- operation created from Discord appears on Horizon

### Runtime

- start operation
- runtime roster
- basic voice-presence sync proof
- live updates in Svelte

### Reliability

- disconnect browser network
- reconnect
- subscriptions recover
- no duplicate participant records
- Discord job retry works

### Media

- upload one operation image to R2
- metadata recorded in SpaceTimeDB

## Prototype Success Criteria

The architecture passes the initial gate if:

1. An operation created on the website appears in Discord.
2. An operation created from Discord appears on the website.
3. Joining from Discord updates the website without refresh.
4. Joining from the website updates Discord.
5. Permissions are enforced by SpaceTimeDB rather than the client.
6. Browser reconnect restores authoritative state correctly.
7. The Penpot-derived layout can be recreated faithfully with CSS/SVG without a page builder.
8. The PWA installs and behaves acceptably on desktop, Android, and iPhone.
9. Codex can implement and modify the foundation reliably without repeatedly fighting the stack.
10. The resulting system feels simpler to reason about than Horizon 1.

Only after this vertical slice succeeds should major Horizon domains be migrated.

---

# 24. Suggested Domain Migration Order

After the architecture is proven:

```text
1. Users / identity / permissions
2. Operations
3. Runtime / attendance / Discord orchestration
4. Squadrons
5. Promotions
6. Media
7. Ledger foundation
8. Transfers / approvals / reversals
9. Operation settlements
10. UEX reference data
11. Archive
12. Admin and analytics surfaces
```

Ledger and settlement should come after operations because they are high-value but contain some of Horizon 1's most complex transactional behavior.

---

# 25. Foundation Definition of Done

The Horizon 2 foundation is considered complete when:

- repository structure is established
- architecture documents exist
- AGENTS.md exists
- Rust SpaceTimeDB module builds and tests
- Svelte client builds
- generated TypeScript bindings work
- Rust Discord bot builds
- generated Rust bindings work
- Citizen iD authentication proof works
- PWA installs successfully
- Tauri shell can load the Horizon client or has a documented deferred milestone
- design tokens and core UI primitives exist
- one Penpot reference page is reproduced successfully
- operation vertical slice works in both directions with Discord
- reconnect behavior has been tested
- media upload path is proven
- CI validates formatting, linting, tests, generated bindings, and builds
- Horizon 1 remains untouched and available as reference

---

# 26. Final Architectural Rule

When choosing between a clever implementation and a clear implementation, prefer the one that preserves this model:

```text
Svelte / Discord / future clients
             |
             v
       SpaceTimeDB reducers
             |
             v
      authoritative state
             |
             v
         subscriptions
             |
             v
          clients
```

Horizon 2 should feel like one system with several interfaces, not several applications held together by synchronization code.

That is the foundation.
