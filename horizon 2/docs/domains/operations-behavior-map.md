# Operations Behavior Map

## Scope and Evidence

Reviewed on 2026-10-02 against Horizon 1 source at commit `c6e01f5112cc0f556770053e9574cd541cc41f81`.

This is the first workflow map, not a complete Horizon 1 audit. It covers operation creation and editing, publication and lifecycle boundaries, audience and management permissions, joining/rejoining, leaving, role assignment, member-visible reads, and Discord announcements. Runtime, attendance, after-action reports, settlement, and templates are included only where they touch this workflow.

Observations describe current source behavior, including inconsistencies. They are not automatically Horizon 2 requirements. Existing tests were read, not executed; no production database, Discord service, or external identity provider was contacted. UI issues described below are source-level findings, not reproduced browser results.

The [foundation specification](../architecture/foundation-design-spec.md) remains the approved architectural direction. Citizen iD research and integration are intentionally deferred until after this mapping step.

## Source Index

Links point to the untouched Horizon 1 implementation in the parent repository.

| ID  | Sources                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Responsibility                                         |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| S01 | [Web routes](../../../backend/routes/web.php), [API routes](../../../backend/routes/api_v1.php)                                                                                                                                                                                                                                                                                                                                                           | Entry points and declared middleware                   |
| S02 | [Web page controller](../../../backend/app/Http/Controllers/Web/OperationPageController.php), [API operation controller](../../../backend/app/Http/Controllers/Api/v1/OperationController.php)                                                                                                                                                                                                                                                            | Lists, create/edit, detail, delete-as-cancel           |
| S03 | [Operation policy](../../../backend/app/Policies/OperationPolicy.php), [access service](../../../backend/app/Domain/AccessControl/OperationAccessService.php)                                                                                                                                                                                                                                                                                             | Audience and management authorization                  |
| S04 | [Create validation](../../../backend/app/Http/Requests/Operations/OperationStoreRequest.php), [update validation](../../../backend/app/Http/Requests/Operations/OperationUpdateRequest.php)                                                                                                                                                                                                                                                               | Accepted fields, aliases, date constraints             |
| S05 | [Operation service](../../../backend/app/Domain/Operations/Services/OperationService.php), [upsert service](../../../backend/app/Domain/Operations/Services/OperationUpsertService.php)                                                                                                                                                                                                                                                                   | Normalization, roles, media, lifecycle orchestration   |
| S06 | [Create action](../../../backend/app/Domain/Operations/Actions/CreateOperation.php), [update action](../../../backend/app/Domain/Operations/Actions/UpdateOperation.php)                                                                                                                                                                                                                                                                                  | Persistence, creator enrollment, announcement triggers |
| S07 | [State base](../../../backend/app/Domain/Operations/States/OperationState.php), [draft](../../../backend/app/Domain/Operations/States/Draft.php), [published](../../../backend/app/Domain/Operations/States/Published.php), [in progress](../../../backend/app/Domain/Operations/States/InProgress.php), [completed](../../../backend/app/Domain/Operations/States/Completed.php), [canceled](../../../backend/app/Domain/Operations/States/Canceled.php) | Transition graph                                       |
| S08 | [Transition action](../../../backend/app/Domain/Operations/Actions/TransitionOperation.php), [direct cancellation action](../../../backend/app/Domain/Operations/Actions/CancelOperation.php), [web transitions](../../../backend/app/Http/Controllers/Web/OperationTransitionController.php)                                                                                                                                                             | Events, cancellation, channel side effects             |
| S09 | [Participant service](../../../backend/app/Domain/Operations/Services/ParticipantService.php), [join action](../../../backend/app/Domain/Operations/Actions/JoinOperation.php), [leave action](../../../backend/app/Domain/Operations/Actions/LeaveOperation.php), [slot action](../../../backend/app/Domain/Operations/Actions/UpdateParticipantSlot.php)                                                                                                | Participation guards and mutations                     |
| S10 | [Web participation controller](../../../backend/app/Http/Controllers/Web/OperationParticipantController.php), [API participation controller](../../../backend/app/Http/Controllers/Api/v1/OperationParticipantController.php)                                                                                                                                                                                                                             | Authorization and response differences                 |
| S11 | [Operation model](../../../backend/app/Models/Operation.php), [participant model](../../../backend/app/Models/OperationParticipant.php), [role model](../../../backend/app/Models/OperationRole.php)                                                                                                                                                                                                                                                      | List visibility, runtime flags, capacity relationships |
| S12 | [Member query](../../../backend/app/Application/Operations/Queries/OperationQuery.php), [dashboard query](../../../backend/app/Application/Operations/Queries/OperationSummaryListQuery.php)                                                                                                                                                                                                                                                              | Sorting, filters, counts                               |
| S13 | [Detail builder](../../../backend/app/Application/Operations/OperationShowDataService.php), [participant payload](../../../backend/app/Application/Operations/OperationParticipantPayloadService.php), [presenter](../../../backend/app/Application/Operations/Presenters/OperationPresenter.php)                                                                                                                                                         | Read payloads and redaction                            |
| S14 | [Editor](../../../backend/resources/js/Pages/Operations/Components/MissionEditorForm.vue), [form helpers](../../../backend/resources/js/operationForm.js), [detail panel](../../../backend/resources/js/Pages/Operations/Components/MissionShowPanel.vue)                                                                                                                                                                                                 | User actions and client-derived state                  |
| S15 | [Announcement service](../../../backend/app/Domain/Operations/OperationDiscordAnnouncementService.php), [publish listener](../../../backend/app/Domain/Operations/Listeners/SendOperationPublishedToDiscord.php), [update listener](../../../backend/app/Domain/Operations/Listeners/SendOperationUpdatedToDiscord.php)                                                                                                                                   | HTTP delivery, targets, message tracking               |
| S16 | [Bot webhook](../../../bots/horizon-bot/services/webhookOperations.js), [bot operation service](../../../bots/horizon-bot/services/operationService.js), [interaction handler](../../../bots/horizon-bot/events/interactionCreate.js)                                                                                                                                                                                                                     | Discord embeds, sends, deletes, interaction handling   |

## Workflow

| Step                | Horizon 1 behavior                                                                                                                                                | References    |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| Find an operation   | Member list offers published/in-progress operations matching its audience query. Dashboard is separately gated by global rank and supports status/search filters. | S02, S11, S12 |
| Create              | Creator submits a global or squadron-owned operation, initially draft or published. Creator is automatically enrolled without the normal join guards.             | S04-S06       |
| Edit                | Authorized manager updates fields and role definitions. Draft editor can save and then separately publish.                                                        | S03-S06, S14  |
| Publish             | Transition route changes draft to published and dispatches an announcement event. Creation directly as published also dispatches that event.                      | S06-S08, S15  |
| Join                | Viewer selects an optional role/slot and signs up before both the RSVP deadline and start time. Capacity is checked if a role ID is supplied.                     | S09, S10, S14 |
| Change role         | Participant can change their own assignment; managers can assign others. Assignments remain mutable during an operation, but not after completion/cancellation.   | S03, S09, S10 |
| Leave               | Before RSVP cutoff, delete signup. From RSVP cutoff until start, retain a signed-off record. At/after start or while in progress, normal leaving is blocked.      | S09           |
| Rejoin              | A signed-off row can be reactivated, but normal signup deadlines still apply.                                                                                     | S09           |
| Start/finish/cancel | Dedicated transitions follow the state graph. Web transitions additionally attempt runtime-channel synchronization or cleanup.                                    | S07, S08      |
| Notify Discord      | Publish sends an embed; qualifying edits delete tracked announcements and send replacements. No operation join/leave buttons exist in the inspected old bot.      | S15, S16      |

Horizon 1's first-party web mutations use redirects/flash or their local JSON response shape, while API routes return JSON. Horizon 2 should preserve the business outcome, not reproduce Laravel/Inertia transport conventions.

## Operation Data

Observed create/update contract: S04-S06, S11, S14.

| Concept          | Existing representation/rules                                                                                          |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Identity         | Operation ID, authenticated creator, optional owning squadron from the creation route                                  |
| Audience         | `visibility`: open, squadron, private; selected squadron names stored separately from owning squadron                  |
| Content          | Title required, up to 255 characters; short description up to 510; extended description/notes up to 5,000              |
| Schedule         | Start required; optional end must follow start; optional RSVP deadline must precede start                              |
| Type             | operation, squadron_training, wing_training, roleplay, meeting, event                                                  |
| Branch           | Optional industries, defence, frontiers, lifelines                                                                     |
| Other metadata   | Gameplay type, start/operation locations, difficulty, strictness, icon, image URL, media reference                     |
| Role definitions | ID, name/display name, optional capacity, sort order, required flag; display names mirrored into legacy `slots`        |
| Capacity         | Per role only. Null means unlimited; zero permits no occupants. No whole-operation capacity guard in this signup path. |
| Participation    | Unique operation/user row; optional role ID, slot label, signup notes, attendance and separate runtime fields          |

Additional observed behavior:

- The editor suggests an RSVP cutoff 30 minutes before start; the backend does not impose that default.
- Aliases such as `operation_kind`, `type`, `notes`, and `squadron_only` support legacy input/schema compatibility. These are not proposed Horizon 2 field names.
- Supplying roles to an update replaces the definition set. Renames update linked participant slot labels; removed roles clear affected participants' role and slot.
- Omitting roles leaves the role set untouched. Blank/null capacity becomes unlimited; required flags are stored but are not checked by the inspected start transition.
- Creation increments the creator's created count and auto-enrollment increments their joined count. Publication can occur before the subsequent role/media synchronization finishes.
- Partial updates currently default an omitted visibility to open. This is an observed inconsistency, not a carry-forward rule.

## Permissions and Audience

Observed domain policy: S03. This maps operation authorization, not authentication-provider security. The existing login/session/middleware chain has not been audited here.

| Action                                                   | Existing policy                                                                                                                                                                                                      |
| -------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| View directly                                            | Director/tech-director or `operation.view` permission overrides audience restrictions; otherwise open is viewable, private is creator-only, squadron requires active membership in the owner or a selected squadron. |
| Create global                                            | Director-like user or highest global role lieutenant or above                                                                                                                                                        |
| Create squadron-owned                                    | Director-like user, or global lieutenant-or-above who is an active member of that squadron                                                                                                                           |
| Fully manage global                                      | Director-like user or creator                                                                                                                                                                                        |
| Fully manage squadron-owned                              | Director-like user, owning squadron's active leader/lieutenant, or its recorded leader ID                                                                                                                            |
| Edit/cancel/manage participants/view others' assignments | Full-management policy                                                                                                                                                                                               |
| Join/leave on web                                        | View policy plus participation guards                                                                                                                                                                                |
| Change own assignment                                    | Own participant row, subject to participation guards; does not require full-management permission                                                                                                                    |
| Manage after-action report                               | Completed operation plus full-management policy                                                                                                                                                                      |

Creating a squadron operation does not itself grant management permission. A globally senior creator without the owning squadron's command role may create an operation but be unable to edit it afterward. Existing access tests explicitly cover this separation.

Owner, selected audience, creator, global role, and local squadron command role are distinct concepts. Do not collapse them into one rank check in Horizon 2.

### Read-Surface Differences

Observed read contract: S02, S11-S14.

| Surface                  | Current behavior                                                                                                                                                                                                                    |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Member list              | Published/in-progress only; audience filter does not mirror the direct policy's director/permission override. Twelve rows per page, future operations earliest first, then past operations latest first.                            |
| Management dashboard     | Global lieutenant-or-above gate; inspected listing query does not apply per-operation audience filtering.                                                                                                                           |
| Web detail               | Excludes signed-off rows from visible roster/count and current participant. Ordinary viewers still receive participant identities, but others' role, slot, attendance, and signup notes are null. Own assignment remains available. |
| Web runtime fields       | Participant payload still includes runtime status/source, timestamps, balances, and runtime notes independently of assignment redaction.                                                                                            |
| API detail               | Raw full presenter includes participant assignments/notes rather than the web detail's redaction.                                                                                                                                   |
| Role aggregates          | Server role counts include signed-off rows. Member list counts and joined flags also include those rows, unlike web detail.                                                                                                         |
| Client role availability | Detail panel recalculates role counts from its roster and overwrites server aggregates. Redacted role IDs can therefore yield incorrect availability for ordinary members.                                                          |

These differences require an explicit Horizon 2 read-permission and field matrix. Hiding controls or fields in Svelte will not provide the server-side privacy required by the foundation spec.

## Lifecycle

Dedicated transition path: S07, S08.

| Current state | Permitted next states |
| ------------- | --------------------- |
| draft         | published, canceled   |
| published     | in_progress, canceled |
| in_progress   | completed, canceled   |
| completed     | None                  |
| canceled      | None                  |

- No self-transition, reopening, or unpublishing through this state graph.
- Cancellation requires a nonblank reason. Completion endpoints require a success/failed outcome; the lower-level state method accepts an optional outcome.
- The inspected graph does not enforce the scheduled start time or required role occupancy. Scheduled timestamps are not rewritten to actual transition times.
- Published, completed, and canceled transitions dispatch domain events. There is no corresponding start event in this action.
- Completion also invokes after-action defaults and settlement preparation. Those downstream rules are deferred for their own map.
- Web start/channel-sync failure produces a warning after the operation has started. Complete/cancel channel-cleanup failure likewise leaves the successful transition intact. API transition paths do not perform these same web-controller side effects.

Two mutation paths bypass this graph: ordinary updates accept all status values and assign them directly; DELETE uses a direct cancellation action that can cancel a completed operation. These must be resolved rather than silently duplicated in Horizon 2.

## Participation Rules

Observed guards/actions: S09, S10. Time comparisons use server time; reaching the boundary closes signup/leaving as described below.

| Condition                                 | Join/rejoin                         | Leave                     | Change assignment                      |
| ----------------------------------------- | ----------------------------------- | ------------------------- | -------------------------------------- |
| Draft or published, before RSVP and start | Allowed subject to view/role checks | Deletes existing signup   | Allowed for self or authorized manager |
| At/after RSVP, before start               | Blocked                             | Retains signed-off record | Allowed for self or authorized manager |
| At/after scheduled start, not terminal    | Blocked                             | Blocked                   | Allowed for self or authorized manager |
| In progress, even before scheduled start  | Blocked                             | Blocked                   | Allowed for self or authorized manager |
| Completed or canceled                     | Blocked                             | Blocked                   | Blocked                                |

When RSVP is absent, a pre-start leave deletes the signup. Draft joining is allowed by the service even though drafts are absent from the normal member list. Creator auto-enrollment is a separate direct action without these time/state guards.

### Signup and Role Assignment

- Null/empty slot is allowed. A nonnull slot must exactly match a defined label. A role ID must belong to the operation.
- Slot and role are validated independently, so a crafted request can submit inconsistent values or a slot without a capacity-checked role.
- Role capacity counts all participant rows attached to that role, including signed-off rows. Updating an assignment excludes the edited participant from the capacity count; joining/rejoining does not.
- A duplicate active signup returns an error rather than an idempotent success. Guard checks occur first, so a retry can instead fail with deadline/full-role errors.
- Role capacity uses count-then-write without a transaction/row lock in the inspected service/actions. Concurrent requests can exceed capacity even though the operation/user uniqueness constraint prevents duplicate signup rows.
- Slot updates change only supplied fields. A slot-only payload can change the label while retaining the existing role ID.
- Controllers protect cross-operation assignment IDs and restrict editing someone else's assignment to managers.

### Leave and Rejoin

- Early leave hard-deletes the participant row. The historical joined counter is not decremented.
- Late pre-start leave retains role/slot and records `signed_off_before_start` with a timestamp; it clears the runtime sync timestamp and increments the user's left-early counter.
- Repeating a late leave can increment that counter again because the action has no already-signed-off guard.
- Rejoining a signed-off row reuses its ID, resets role/slot/notes from the new request, sets attendance back to signed up, clears stats and signoff/sync timestamps, restores signed-up runtime status/source, and increments the joined counter again.
- Rejoin does not reset every runtime field: balance/channel/runtime-notes data are not cleared by this action.
- A normal late-leave occurs after RSVP, so rejoin is ordinarily blocked by the cutoff unless the schedule changes. The existing rejoin unit test uses a signed-off row with a future cutoff.

Web leave checks view permission and distinguishes deletion from signoff in its success message. API leave has no matching view-policy call, uses a generic success message for both outcomes, and masks validation failures as a generic not-in-operation response. Horizon 2 should define one domain result and let adapters present it appropriately.

## Discord Announcements

Observed path: S06, S15, S16.

1. Publish or a qualifying published-operation edit dispatches a Laravel event.
2. Synchronous listeners send HTTP requests to the Node bot using the configured shared secret. Exceptions are logged; the operation write remains successful.
3. The bot resolves announcement channels and sends an embed linking back to Horizon, with title, description, start timestamp/location, creator, strictness, type/squadron context, and operation ID.
4. Successful delivery returns channel/message targets for tracking. On edits, old targets are deleted before new announcements are posted.

| Topic                    | Existing behavior                                                                                                                                                                |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Global operation targets | Default announcement channel when there is no owning squadron                                                                                                                    |
| Squadron targets         | Linked channels of owning squadron plus selected squadron names; duplicate channels removed                                                                                      |
| Audience privacy         | Routing does not inspect visibility; private operations can still be sent to these announcement channels                                                                         |
| Pings                    | Configured announcement role can be pinged on the default-channel send; scoped sends suppress parsed mentions                                                                    |
| Update trigger           | Watched fields include title, description, starts_at, start_location, strictness, owner/audience squadron fields, operation type, and creator. Not every edit triggers a repost. |
| Roster changes           | Joining/leaving has no announcement event here; message does not show a live roster or count                                                                                     |
| Interaction controls     | No operation join/leave controls in this path; inspected interaction handler accepts slash commands only                                                                         |
| Reliability              | No durable outbox/retry boundary in these listeners; message deletion and replacement are separate effects                                                                       |

Delivery failures have additional ambiguity: bot service catches can return null while the webhook still returns HTTP 200 with no targets. A later-channel failure can lose tracking of earlier successful sends. Deletion followed by failed repost can leave no announcement. These are not acceptable retry semantics to copy into the new outbox.

Horizon 2's shared web/bot reducers, operation controls, authorized subscriptions, audit metadata, and durable Discord jobs are already requested by the foundation spec. They are new implementation work, not capabilities proven to exist in the old bot. Identity/account mapping will be researched separately.

## Carry-Forward Candidates

These are candidates grounded in existing behavior, pending the decisions below:

- Preserve operation metadata, explicit lifecycle states, optional role choice, and per-role capacity.
- Preserve the separation between ownership, audience, creator, and squadron command authority.
- Preserve server-authoritative time checks and the distinction between early withdrawal and late signoff.
- Preserve role sorting, rename/removal effects, and participant self-assignment where authorized.
- Preserve meaningful operation/participation history rather than treating runtime status and attendance as the same field.
- Preserve announcement links and operation context, with target selection reviewed for audience privacy.

For Horizon 2, apply the already-approved reducer authorization, atomic writes, stable internal IDs, idempotency, private read surfaces, audit records, and durable external jobs. Do not port Laravel controllers, comma-separated relationship names, bot-local business logic, or compatibility aliases as architecture.

## Decisions Before Implementation

These are open questions and recommended directions, not approved behavior changes. No decisions need to interrupt the planned Citizen iD research next.

| ID  | Issue                                                                                         | Decision needed                                                                                                                                                                  |
| --- | --------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D01 | Status updates and DELETE bypass the lifecycle graph                                          | Make lifecycle changes use one authoritative transition path; decide whether an explicit administrative terminal override is needed.                                             |
| D02 | Lists, detail/API payloads, runtime fields, and announcements expose different audiences/data | Define the permission/field matrix, including private announcements and dashboard visibility.                                                                                    |
| D03 | Signed-off rows retain capacity and joined-list flags but disappear from detail               | Decide whether signoff releases a role, and define active roster/count/joined semantics consistently.                                                                            |
| D04 | Repeated signup/signoff changes counters; ordinary retries return errors                      | Specify idempotent domain results and whether counters measure unique operations or historical attempts. Idempotent retry handling is already required by the spec.              |
| D05 | Signed-off reactivation exists but cutoff normally prevents it                                | Decide whether rejoin remains strictly subject to RSVP or gets a specific exception.                                                                                             |
| D06 | Drafts accept normal signup; creator auto-enrollment bypasses guards                          | Decide normal draft participation and creator enrollment/role behavior explicitly.                                                                                               |
| D07 | Role IDs and slot labels can disagree or bypass capacity                                      | Prefer one canonical role reference with derived labels; decide whether unstructured legacy slots remain necessary.                                                              |
| D08 | Required-role metadata is not enforced at start                                               | Decide whether required is informational or blocks starting with an unfilled role.                                                                                               |
| D09 | Partial edits reset omitted visibility to open                                                | Preserve unspecified values in new update commands; document clear-versus-omit behavior for optional fields.                                                                     |
| D10 | Client role counts overwrite authoritative aggregates from redacted data                      | Use authorized server aggregates rather than infer capacity from an incomplete member roster.                                                                                    |
| D11 | Creation/events/capacity writes lack one atomic boundary                                      | Commit operation/roles/participation/audit/job changes together where one command owns them; test concurrent capacity and rollback. This follows the approved reducer direction. |
| D12 | Discord delete/repost, partial sends, and null-success responses are ambiguous                | Define per-target idempotent job tracking, edit-versus-repost behavior, ping policy, deletion handling, and recovery. Durable retry support is already required.                 |
| D13 | API/web share services but disagree on authorization/results/side effects                     | Define shared reducer outcomes and job triggers so origin changes presentation/audit metadata, not domain rules.                                                                 |

## Test Evidence and Future Acceptance Cases

Existing tests below were inspected, not run. Their presence is evidence of intended behavior, not proof the current suite passes. Horizon 2 has no implemented operation reducers or acceptance tests yet.

| Existing test source                                                                                                       | Relevant assertions/intents                                                                                                                                |
| -------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Access tests](../../../backend/tests/Unit/Domain/AccessControl/OperationAccessServiceTest.php)                            | Audience membership, global creator/director access, owning squadron command, creator without command denied management                                    |
| [Participant service tests](../../../backend/tests/Unit/Domain/Operations/ParticipantServiceTest.php)                      | Signup guards, role capacity, retained signed-off reactivation                                                                                             |
| [Upsert tests](../../../backend/tests/Unit/Domain/Operations/OperationUpsertServiceTest.php)                               | Role normalization, ordering, required flags                                                                                                               |
| [Web flow tests](../../../backend/tests/Feature/OperationWebInertiaFlowTest.php)                                           | Member audience filtering, editor context, publication, cancellation reason, role capacity persistence, own assignment versus others' redacted assignments |
| [Detail builder tests](../../../backend/tests/Unit/Application/Operations/OperationShowDataServiceTest.php)                | Signed-off filtering, own assignment, manager roster grouping                                                                                              |
| [Participant payload tests](../../../backend/tests/Unit/Application/Operations/OperationParticipantPayloadServiceTest.php) | Assignment redaction and runtime fields                                                                                                                    |
| [Runtime web tests](../../../backend/tests/Feature/OperationRuntimeWebTest.php)                                            | Late leave preserves signed-off participant and timestamp                                                                                                  |
| [Transition tests](../../../backend/tests/Unit/Domain/Operations/TransitionOperationTest.php)                              | Invalid transitions, cancellation reason, completion/cancellation counter and downstream defaults                                                          |
| [Announcement tests](../../../backend/tests/Feature/OperationDiscordAnnouncementTest.php)                                  | Selected-squadron targets and deletion/repost using HTTP fakes, not live Discord                                                                           |

Future reducer/adapter acceptance checklist, after D01-D13 are resolved:

- Create global/scoped operations as allowed and denied users; verify creator enrollment and all-or-nothing writes.
- Verify owner and audience independently, including a creator without squadron command authority.
- Publish once and retry; commit one logical announcement job without duplicate counter/history changes.
- Exercise every allowed/disallowed lifecycle edge, ordinary edits, terminal override policy, and missing reasons/outcomes.
- Test signup at just before, exactly at, and just after RSVP/start boundaries, including absent RSVP and draft policy.
- Test duplicate delivery, simultaneous signup for the final role place, zero/unlimited capacity, foreign role IDs, and mismatched role/slot payloads.
- Change own/other assignment with correct authorization, across operations, at capacity, and during/after operation lifecycle.
- Test early deletion, late signoff, repeated leave, signed-off capacity/counts, rejoin cutoff, and row/history semantics.
- Verify each list/detail/subscription surface against the approved audience and field matrix; unauthorized clients must not receive hidden fields.
- Verify ordinary members receive correct role availability without receiving other members' hidden assignments.
- Test partial edits, role rename/removal, optional-field clearing, and required-role start policy.
- Verify web and Discord adapters produce identical authorized domain outcomes with different origin audit metadata.
- Test per-target Discord success, partial failure, retry after restart, unknown/deleted message, failed update, and private-operation routing.
- Verify Discord outages do not roll back valid operation state and are visible as recoverable jobs rather than untracked successes.

## Deferred Maps

The remaining Horizon 1 areas need separate source/test passes before their implementation:

- Runtime channels, walk-ins, presence synchronization, and attendance finalization.
- After-action reporting and profile/stat reconciliation.
- Funds preparation, settlement, ledger and inventory transactions.
- Operation templates, calendars, and media lifecycle.
- Squadrons, ranks/permissions beyond this operation boundary, and other platform domains.

Next agreed step: research Citizen iD and learn its actual identity/OIDC capabilities before designing the identity proof. This document does not assume provider claims, endpoints, token behavior, or Discord-linking support.
