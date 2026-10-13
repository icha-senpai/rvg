mod claims;

use claims::{CitizenClaims, validate_claims};
use spacetimedb::{
    Identity, ReducerContext, SpacetimeType, Table, Timestamp, ViewContext, reducer, table, view,
};

#[derive(SpacetimeType, Clone, Copy, Debug, PartialEq, Eq)]
pub enum HorizonUserStatus {
    Pending,
}

#[derive(SpacetimeType, Clone, Copy, Debug, PartialEq, Eq)]
pub enum IdentityAuditKind {
    IdentityCreated,
    ProfileUpdated,
}

#[table(accessor = horizon_user, private)]
pub struct HorizonUser {
    #[primary_key]
    #[auto_inc]
    pub id: u64,
    pub status: HorizonUserStatus,
    pub created_at: Timestamp,
}

#[table(accessor = citizen_identity, private)]
pub struct CitizenIdentity {
    #[primary_key]
    pub auth_identity: Identity,
    #[unique]
    pub horizon_user_id: u64,
    pub issuer: String,
    pub subject: String,
    pub display_name: Option<String>,
    pub preferred_username: Option<String>,
    pub rsi_handle: Option<String>,
    pub rsi_citizen_id: Option<String>,
    pub rsi_spectrum_id: Option<String>,
    pub discord_account_id: Option<String>,
    pub rsi_verified: bool,
    pub last_seen_at: Timestamp,
    #[default(None)]
    pub rsi_primary_org: Option<String>,
    #[default(None)]
    pub rsi_public_orgs: Option<Vec<String>>,
}

#[table(accessor = identity_audit, private)]
pub struct IdentityAudit {
    #[primary_key]
    #[auto_inc]
    pub id: u64,
    pub user_id: u64,
    pub kind: IdentityAuditKind,
    pub at: Timestamp,
}

#[derive(SpacetimeType)]
pub struct CurrentUser {
    pub id: u64,
    pub status: HorizonUserStatus,
    pub display_name: Option<String>,
    pub preferred_username: Option<String>,
    pub rsi_handle: Option<String>,
    pub rsi_citizen_id: Option<String>,
    pub rsi_spectrum_id: Option<String>,
    pub discord_account_id: Option<String>,
    pub rsi_verified: bool,
    pub last_seen_at: Timestamp,
    pub rsi_primary_org: Option<String>,
    pub rsi_public_orgs: Vec<String>,
}

#[view(accessor = current_user, public)]
fn current_user(ctx: &ViewContext) -> Option<CurrentUser> {
    let identity = ctx
        .db
        .citizen_identity()
        .auth_identity()
        .find(ctx.sender())?;
    let user = ctx.db.horizon_user().id().find(identity.horizon_user_id)?;
    Some(CurrentUser {
        id: user.id,
        status: user.status,
        display_name: identity.display_name,
        preferred_username: identity.preferred_username,
        rsi_handle: identity.rsi_handle,
        rsi_citizen_id: identity.rsi_citizen_id,
        rsi_spectrum_id: identity.rsi_spectrum_id,
        discord_account_id: identity.discord_account_id,
        rsi_verified: identity.rsi_verified,
        last_seen_at: identity.last_seen_at,
        rsi_primary_org: identity.rsi_primary_org,
        rsi_public_orgs: identity.rsi_public_orgs.unwrap_or_default(),
    })
}

#[reducer(client_connected)]
fn client_connected(ctx: &ReducerContext) -> Result<(), String> {
    sync_citizen_identity_for_sender(ctx)
}

#[reducer]
pub fn sync_citizen_identity(ctx: &ReducerContext) -> Result<(), String> {
    sync_citizen_identity_for_sender(ctx)
}

fn sync_citizen_identity_for_sender(ctx: &ReducerContext) -> Result<(), String> {
    let jwt = ctx
        .sender_auth()
        .jwt()
        .ok_or_else(|| "authentication required".to_string())?;
    let now_seconds = ctx.timestamp.to_micros_since_unix_epoch() / 1_000_000;
    let claims = validate_claims(jwt.raw_payload(), now_seconds)?;
    if Identity::from_claims(&claims.issuer, &claims.subject) != ctx.sender() {
        return Err("authenticated identity mismatch".to_string());
    }
    upsert_identity(ctx, &claims);
    Ok(())
}

fn upsert_identity(ctx: &ReducerContext, claims: &CitizenClaims) {
    let now = ctx.timestamp;
    if let Some(existing) = ctx.db.citizen_identity().auth_identity().find(ctx.sender()) {
        let profile_changed = existing.issuer != claims.issuer
            || existing.subject != claims.subject
            || existing.display_name != claims.display_name
            || existing.preferred_username != claims.preferred_username
            || existing.rsi_handle != claims.rsi_handle
            || existing.rsi_citizen_id != claims.rsi_citizen_id
            || existing.rsi_spectrum_id != claims.rsi_spectrum_id
            || existing.discord_account_id != claims.discord_account_id
            || existing.rsi_verified != claims.rsi_verified
            || existing.rsi_primary_org != claims.rsi_primary_org
            || existing.rsi_public_orgs.as_deref().unwrap_or(&[])
                != claims.rsi_public_orgs.as_slice();
        ctx.db
            .citizen_identity()
            .auth_identity()
            .update(CitizenIdentity {
                issuer: claims.issuer.clone(),
                subject: claims.subject.clone(),
                display_name: claims.display_name.clone(),
                preferred_username: claims.preferred_username.clone(),
                rsi_handle: claims.rsi_handle.clone(),
                rsi_citizen_id: claims.rsi_citizen_id.clone(),
                rsi_spectrum_id: claims.rsi_spectrum_id.clone(),
                discord_account_id: claims.discord_account_id.clone(),
                rsi_verified: claims.rsi_verified,
                last_seen_at: now,
                rsi_primary_org: claims.rsi_primary_org.clone(),
                rsi_public_orgs: Some(claims.rsi_public_orgs.clone()),
                ..existing
            });
        if profile_changed {
            ctx.db.identity_audit().insert(IdentityAudit {
                id: 0,
                user_id: existing.horizon_user_id,
                kind: IdentityAuditKind::ProfileUpdated,
                at: now,
            });
        }
    } else {
        let user = ctx.db.horizon_user().insert(HorizonUser {
            id: 0,
            status: HorizonUserStatus::Pending,
            created_at: now,
        });
        ctx.db.citizen_identity().insert(CitizenIdentity {
            auth_identity: ctx.sender(),
            horizon_user_id: user.id,
            issuer: claims.issuer.clone(),
            subject: claims.subject.clone(),
            display_name: claims.display_name.clone(),
            preferred_username: claims.preferred_username.clone(),
            rsi_handle: claims.rsi_handle.clone(),
            rsi_citizen_id: claims.rsi_citizen_id.clone(),
            rsi_spectrum_id: claims.rsi_spectrum_id.clone(),
            discord_account_id: claims.discord_account_id.clone(),
            rsi_verified: claims.rsi_verified,
            last_seen_at: now,
            rsi_primary_org: claims.rsi_primary_org.clone(),
            rsi_public_orgs: Some(claims.rsi_public_orgs.clone()),
        });
        ctx.db.identity_audit().insert(IdentityAudit {
            id: 0,
            user_id: user.id,
            kind: IdentityAuditKind::IdentityCreated,
            at: now,
        });
    }
}
