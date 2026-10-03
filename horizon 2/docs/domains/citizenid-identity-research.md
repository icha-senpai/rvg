# Citizen iD Identity Research

## Scope and Evidence

Reviewed on 2026-10-02 from public Citizen iD user, integrator, OAuth/OIDC, API, Discord, and legal documentation.

This document is research for Horizon 2 identity design. It is not an implementation plan approval, does not include credentials, and does not claim any live staging or production integration has been tested. No Citizen iD staff, Discord server, production database, or private developer dashboard was contacted.

Primary sources:

- [Citizen iD user guide](https://docs.citizenid.space/user-guide/)
- [Citizen iD integrator guide](https://docs.citizenid.space/integrator-guide/)
- [Integrator registration](https://docs.citizenid.space/integrator-guide/registration.html)
- [OAuth2 getting started](https://docs.citizenid.space/integrator-guide/oauth2/)
- [OIDC guide](https://docs.citizenid.space/integrator-guide/oauth2/oidc.html)
- [Scopes and claims](https://docs.citizenid.space/integrator-guide/oauth2/scopes-claims.html)
- [OAuth2 flows and grants](https://docs.citizenid.space/integrator-guide/oauth2/flows-grants.html)
- [Token reference](https://docs.citizenid.space/integrator-guide/oauth2/tokens.html)
- [Citizen iD API guide](https://docs.citizenid.space/integrator-guide/api/)
- [API authentication page](https://docs.citizenid.space/integrator-guide/api/auth.html)
- [Discord integration guide](https://docs.citizenid.space/integrator-guide/discord/)
- [Discord linked roles guide](https://docs.citizenid.space/integrator-guide/discord/linked-roles.html)
- [Citizen iD terms of service](https://citizenid.space/legal/terms-of-service)

## What Citizen iD Is

Citizen iD is an unofficial Star Citizen community identity platform. It provides OAuth2/OIDC sign-in, RSI account verification, scoped identity claims, Discord profile claims, Discord linked roles, and some community/server integration tooling.

For Horizon 2, Citizen iD should answer only:

> Who is this external person, and what Star Citizen/Discord identities have they consented to share?

Horizon must still answer:

> What can this person do inside Horizon?

That matches the foundation spec's existing separation: Citizen iD is an external identity provider; Horizon user IDs, permissions, squadron authority, operation access, audit, and reducer authorization remain Horizon-owned.

## Access and Environments

Citizen iD integrator access is not fully self-serve from the public docs.

Observed onboarding flow:

1. Create a Citizen iD account.
2. Verify/link Discord.
3. Contact Citizen iD staff through their official Discord support channel.
4. Receive developer portal access, initially for staging.
5. Request production approval after staging review.

Environments:

| Purpose    | Base URL                  | Notes                                        |
| ---------- | ------------------------- | -------------------------------------------- |
| Staging    | `https://citizenid.dev`   | For development and test credentials         |
| Production | `https://citizenid.space` | Live user data; requires production approval |

OIDC production discovery is documented as:

```text
https://citizenid.space/.well-known/openid-configuration
```

The staging equivalent should use the staging domain, but credentials and redirect URIs must match the environment where the client is registered.

## OAuth/OIDC Shape

Citizen iD supports standard OAuth2 and OIDC. The public docs recommend using existing OIDC libraries rather than hand-rolling flows.

Documented OIDC values:

| Setting   | Production value                                                       |
| --------- | ---------------------------------------------------------------------- |
| Issuer    | `https://citizenid.space`                                              |
| Authority | `https://citizenid.space`                                              |
| Discovery | `/.well-known/openid-configuration`                                    |
| Audience  | Horizon client ID and/or Citizen iD authority, depending on token type |

Supported client types:

- Confidential clients for server-side applications that can protect a client secret.
- Public clients for SPAs, desktop, mobile, and other clients that cannot protect a secret.

Flow implications for Horizon:

- Web/PWA and Tauri should use Authorization Code with PKCE as public clients unless a trusted auth bridge owns the exchange.
- A trusted server or small auth broker may still be needed if Horizon must keep refresh tokens, exchange credentials, or mint short-lived Horizon/SpaceTimeDB auth proofs.
- Do not place Citizen iD client secrets in Svelte, Tauri frontend code, Discord command definitions, or committed environment examples.

## Useful Scopes and Claims

Minimum sign-in:

| Scope     | Use in Horizon                                           |
| --------- | -------------------------------------------------------- |
| `openid`  | Required OIDC sign-in and stable `sub`                   |
| `profile` | Display identity such as `name` and `preferred_username` |

Likely Horizon profile/onboarding scopes:

| Scope              | Relevant claims                                                                |
| ------------------ | ------------------------------------------------------------------------------ |
| `rsi.profile`      | RSI username, display name, enlisted date, avatar URL, citizen ID, Spectrum ID |
| `rsi.orgs.primary` | Primary RSI organization                                                       |
| `rsi.orgs.public`  | Public RSI organizations                                                       |
| `discord.profile`  | Discord account ID, username, avatar URL, granted Discord scopes               |
| `roles`            | Citizen iD and community role strings                                          |
| `email`            | Email when present; docs warn not every user has one                           |
| `offline_access`   | Refresh tokens for long-lived access; only if Horizon truly needs it           |

Important claim behavior:

- `sub` is the stable OIDC subject Horizon should map to a local user.
- RSI and Discord claims are scoped and consented; Horizon must handle missing claims.
- RSI verification/account data is a game-account ownership signal, not legal identity.
- Token contents are signed but not encrypted; never store raw tokens unless there is a specific operational need.

## Proposed Horizon Data Model

Keep Citizen iD identity as an external identity binding, not the user primary key.

Recommended internal shape:

| Table/concept             | Purpose                                                                                 |
| ------------------------- | --------------------------------------------------------------------------------------- |
| `HorizonUser`             | Stable internal user ID, status, callsign, preferences, audit metadata                  |
| `ExternalIdentity`        | Provider identity binding: provider=`citizenid`, subject=`sub`, issuer, first/last seen |
| `ExternalProfileSnapshot` | Last consented RSI/Discord/profile claims, normalized for display and search            |
| `DiscordAccountLink`      | Discord snowflake to Horizon user mapping when `discord.profile` is granted             |
| `AuthSession` or proof    | Short-lived Horizon auth proof for SpaceTimeDB connection if required                   |
| `IdentityAuditEvent`      | Login, link, unlink, claim refresh, conflict, revocation, and admin resolution history  |

Store as durable Horizon facts:

- Horizon user ID.
- Citizen iD issuer and subject.
- RSI handle/display snapshot.
- RSI citizen ID and Spectrum ID when granted.
- Discord account ID when granted.
- Claim snapshot timestamps and granted scope list.

Avoid storing as primary authority:

- Citizen iD role strings as Horizon permission grants.
- Discord roles as Horizon permission grants.
- RSI organization claims as live Horizon squadron membership without local review/confirmation.
- Raw access/refresh tokens unless a concrete refresh/API requirement exists.

## Discord Mapping

Citizen iD can expose Discord profile claims when `discord.profile` is granted and also supports Discord linked roles.

For Horizon 2:

- The Horizon Discord bot should resolve Discord ID to a Horizon user through a stored, consented mapping.
- A Discord interaction from an unmapped account should return an onboarding/login prompt, not perform a user-level reducer.
- Citizen iD linked roles can be helpful for Discord server hygiene, but they should not be treated as Horizon authorization.
- Horizon should not infer operation, ledger, or squadron authority from Discord roles alone.

This matches the foundation spec: Discord IDs do not implicitly grant Horizon authority.

## SpaceTimeDB Identity Proof

The key unresolved implementation design is how a Citizen iD-authenticated person becomes an authenticated SpaceTimeDB caller.

Conservative design direction:

1. User signs in with Citizen iD using Authorization Code with PKCE.
2. Horizon validates the ID token through OIDC discovery/JWKS.
3. Horizon upserts the local user and external identity binding.
4. Horizon creates a short-lived local auth proof/session for the SpaceTimeDB connection.
5. SpaceTimeDB reducers resolve the caller to `HorizonUser`, then perform Horizon authorization.

This keeps SpaceTimeDB reducers authoritative for Horizon permissions while allowing Citizen iD to remain the external login source.

Open technical question: whether the SpaceTimeDB client path should directly present Citizen iD JWTs, Horizon-issued proofs, or a small trusted auth adapter. The answer depends on the exact SpaceTimeDB auth extension point we choose for the first identity proof.

## Privacy and Reliability Constraints

The Citizen iD terms and docs matter architecturally:

- Citizen iD is unofficial and unaffiliated with CIG/RSI.
- It has no general uptime/service-level guarantee.
- RSI, Discord, OAuth/OIDC, role sync, nickname sync, verification, and profile data can be unavailable or inaccurate.
- RSI verification is not legal/government identity.
- Features may be preview, restricted, role-gated, or changed.

Horizon should therefore:

- Degrade cleanly if Citizen iD is down after a user already has a valid Horizon session.
- Avoid making every read path depend on live Citizen iD calls.
- Snapshot claims at login/refresh and record when they were observed.
- Provide admin-safe conflict handling for changed RSI handles, revoked Discord links, duplicate claims, or Citizen iD subject changes.
- Keep manual Horizon authorization and membership decisions auditable.

## Recommended First Proof

First Citizen iD proof should be intentionally narrow:

1. Obtain staging integrator access.
2. Register a staging public client for web/Tauri with redirect URIs.
3. Implement sign-in with `openid profile rsi.profile discord.profile`.
4. Validate ID token issuer, audience, expiry, nonce, and signature.
5. Upsert a local Horizon user by Citizen iD `sub`.
6. Store RSI and Discord snapshots when present.
7. Connect to SpaceTimeDB as the mapped Horizon user through the chosen proof mechanism.
8. Add one protected reducer that returns the current authenticated Horizon identity.

Do not start with:

- Refresh tokens.
- Citizen iD API writes.
- Discord role sync.
- Auto-granting Horizon ranks from Citizen iD roles.
- Production credentials.
- Operation permissions.

## Open Decisions

| ID  | Question                                                                   | Recommended direction                                                                                                                   |
| --- | -------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| C01 | Should Horizon use a public client directly or a confidential auth broker? | Use public PKCE for the first login proof; introduce a broker only if SpaceTimeDB auth or refresh-token handling requires it.           |
| C02 | Which scopes are required at first login?                                  | Start with `openid profile rsi.profile discord.profile`; add `rsi.orgs.*`, `roles`, or `offline_access` only when a feature needs them. |
| C03 | Is Citizen iD `roles` trusted for Horizon permissions?                     | No. Treat roles as external context, not Horizon authority.                                                                             |
| C04 | Is RSI primary org trusted for squadron membership?                        | No automatic grants. Use as onboarding evidence or a review hint until Horizon defines membership policy.                               |
| C05 | What happens if Discord ID is absent?                                      | User can still have a Horizon account; Discord bot actions require a linked Discord claim or separate verified mapping.                 |
| C06 | What happens if Citizen iD is unavailable?                                 | Existing Horizon sessions should degrade gracefully; fresh login/claim refresh can fail visibly.                                        |
| C07 | Should Horizon store refresh tokens?                                       | Avoid for the first proof. Add only with a concrete background refresh or API need.                                                     |
| C08 | How are duplicate identities resolved?                                     | Enforce unique Citizen iD subject and unique Discord ID; route conflicts to an auditable admin resolution path.                         |
| C09 | Can Horizon go straight to production?                                     | No. Use staging first, then request production review after a working proof.                                                            |

## Next Implementation Inputs Needed

- Citizen iD staging integrator approval.
- Staging client ID and, if using a confidential broker, client secret.
- Approved redirect URIs for local web, local desktop, and eventual production.
- SpaceTimeDB auth proof choice.
- Decision on the first-login scope set.
- Decision on whether Horizon wants Discord mapping during first login or as a separate link step.
