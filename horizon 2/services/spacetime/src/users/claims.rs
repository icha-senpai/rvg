use serde_json::Value;

pub const CITIZEN_ID_ISSUER: &str = "https://citizenid.dev/";
pub const CITIZEN_ID_CLIENT_ID: &str = "6192d6ef-f599-4ec3-9d99-7429ef934a86";

const RSI_VERIFIED_ROLE: &str = "CitizenId/Status/Verified";

#[derive(Clone, Debug, PartialEq, Eq)]
pub struct CitizenClaims {
    pub issuer: String,
    pub subject: String,
    pub expiry: i64,
    pub display_name: Option<String>,
    pub preferred_username: Option<String>,
    pub rsi_handle: Option<String>,
    pub rsi_citizen_id: Option<String>,
    pub rsi_spectrum_id: Option<String>,
    pub discord_account_id: Option<String>,
    pub rsi_verified: bool,
    pub rsi_primary_org: Option<String>,
    pub rsi_public_orgs: Vec<String>,
}

pub fn validate_claims(raw_payload: &str, now_seconds: i64) -> Result<CitizenClaims, String> {
    let value: Value =
        serde_json::from_str(raw_payload).map_err(|_| "malformed token payload".to_string())?;
    let claims = value
        .as_object()
        .ok_or_else(|| "malformed token payload".to_string())?;

    let issuer = required_string(claims, "iss")?;
    if issuer != CITIZEN_ID_ISSUER {
        return Err("untrusted issuer".to_string());
    }

    let subject = required_string(claims, "sub")?;
    if subject.trim().is_empty() {
        return Err("missing subject".to_string());
    }

    let audiences = parse_audience(claims)?;
    if audiences.is_empty() || !audiences.iter().any(|aud| aud == CITIZEN_ID_CLIENT_ID) {
        return Err("untrusted audience".to_string());
    }

    match claims.get("azp") {
        None | Some(Value::Null) => {
            if audiences.len() > 1 {
                return Err("missing authorized party".to_string());
            }
        }
        Some(Value::String(azp)) => {
            if azp != CITIZEN_ID_CLIENT_ID {
                return Err("untrusted authorized party".to_string());
            }
        }
        Some(_) => return Err("malformed authorized party".to_string()),
    }

    let expiry = claims
        .get("exp")
        .and_then(Value::as_i64)
        .ok_or_else(|| "missing expiry".to_string())?;
    if expiry <= now_seconds {
        return Err("token expired".to_string());
    }

    if let Some(nbf) = claims.get("nbf") {
        match nbf {
            Value::Null => {}
            Value::Number(_) => {
                let not_before = nbf
                    .as_i64()
                    .ok_or_else(|| "invalid not-before".to_string())?;
                if not_before > now_seconds {
                    return Err("token not yet valid".to_string());
                }
            }
            _ => return Err("invalid not-before".to_string()),
        }
    }

    Ok(CitizenClaims {
        issuer,
        subject,
        expiry,
        display_name: optional_string(claims, "name")?,
        preferred_username: optional_string(claims, "preferred_username")?,
        rsi_handle: optional_string(claims, "urn:user:rsi:username")?,
        rsi_citizen_id: optional_string(claims, "urn:user:rsi:citizenId")?,
        rsi_spectrum_id: optional_string(claims, "urn:user:rsi:spectrumId")?,
        discord_account_id: optional_string(claims, "urn:user:discord:accountId")?,
        rsi_verified: has_verified_role(claims),
        rsi_primary_org: optional_string(claims, "urn:user:rsi:orgs:primary")?,
        rsi_public_orgs: optional_string_list(claims, "urn:user:rsi:orgs:public")?,
    })
}

fn required_string(claims: &serde_json::Map<String, Value>, key: &str) -> Result<String, String> {
    match claims.get(key) {
        Some(Value::String(value)) => Ok(value.clone()),
        _ => Err("missing required claim".to_string()),
    }
}

fn optional_string(
    claims: &serde_json::Map<String, Value>,
    key: &str,
) -> Result<Option<String>, String> {
    match claims.get(key) {
        None | Some(Value::Null) => Ok(None),
        Some(Value::String(value)) => Ok(Some(value.clone())),
        Some(_) => Err("malformed profile claim".to_string()),
    }
}

fn optional_string_list(
    claims: &serde_json::Map<String, Value>,
    key: &str,
) -> Result<Vec<String>, String> {
    match claims.get(key) {
        None | Some(Value::Null) => Ok(Vec::new()),
        Some(Value::String(value)) => Ok(vec![value.clone()]),
        Some(Value::Array(entries)) => entries
            .iter()
            .map(|entry| match entry {
                Value::String(value) => Ok(value.clone()),
                _ => Err("malformed profile claim".to_string()),
            })
            .collect(),
        Some(_) => Err("malformed profile claim".to_string()),
    }
}

fn parse_audience(claims: &serde_json::Map<String, Value>) -> Result<Vec<String>, String> {
    match claims.get("aud") {
        Some(Value::String(aud)) => Ok(vec![aud.clone()]),
        Some(Value::Array(entries)) => entries
            .iter()
            .map(|entry| {
                entry
                    .as_str()
                    .map(str::to_string)
                    .ok_or_else(|| "malformed audience".to_string())
            })
            .collect(),
        _ => Err("missing audience".to_string()),
    }
}

fn has_verified_role(claims: &serde_json::Map<String, Value>) -> bool {
    ["role", "roles"].iter().any(|key| match claims.get(*key) {
        Some(Value::String(role)) => role == RSI_VERIFIED_ROLE,
        Some(Value::Array(entries)) => entries
            .iter()
            .any(|entry| entry.as_str() == Some(RSI_VERIFIED_ROLE)),
        _ => false,
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    const NOW: i64 = 1_800_000_000;

    fn payload(overrides: serde_json::Value) -> String {
        let mut base = serde_json::json!({
            "iss": CITIZEN_ID_ISSUER,
            "sub": "cid-subject-1",
            "aud": CITIZEN_ID_CLIENT_ID,
            "exp": NOW + 3600
        });
        let base_obj = base.as_object_mut().unwrap();
        for (key, value) in overrides.as_object().unwrap() {
            if value.is_null() {
                base_obj.insert(key.clone(), Value::Null);
            } else {
                base_obj.insert(key.clone(), value.clone());
            }
        }
        base.to_string()
    }

    #[test]
    fn accepts_minimal_valid_claims() {
        let claims = validate_claims(&payload(serde_json::json!({})), NOW).unwrap();
        assert_eq!(claims.issuer, CITIZEN_ID_ISSUER);
        assert_eq!(claims.subject, "cid-subject-1");
        assert_eq!(claims.expiry, NOW + 3600);
        assert!(!claims.rsi_verified);
        assert_eq!(claims.display_name, None);
        assert_eq!(claims.rsi_handle, None);
        assert_eq!(claims.discord_account_id, None);
    }

    #[test]
    fn accepts_full_signed_profile() {
        let claims = validate_claims(
            &payload(serde_json::json!({
                "aud": [CITIZEN_ID_CLIENT_ID, "https://citizenid.dev"],
                "azp": CITIZEN_ID_CLIENT_ID,
                "nbf": NOW - 10,
                "name": "Horizon Pilot",
                "preferred_username": "pilot",
                "urn:user:rsi:username": "PILOT",
                "urn:user:rsi:citizenId": "12345",
                "urn:user:rsi:spectrumId": "6789",
                "urn:user:discord:accountId": "111222333",
                "urn:user:rsi:orgs:primary": "SRN",
                "urn:user:rsi:orgs:public": ["SRN", "ALLY"],
                "roles": ["CitizenId/Status/Verified", "CitizenId/User"]
            })),
            NOW,
        )
        .unwrap();
        assert_eq!(claims.rsi_primary_org.as_deref(), Some("SRN"));
        assert_eq!(claims.rsi_public_orgs, vec!["SRN", "ALLY"]);
        assert_eq!(claims.display_name.as_deref(), Some("Horizon Pilot"));
        assert_eq!(claims.preferred_username.as_deref(), Some("pilot"));
        assert_eq!(claims.rsi_handle.as_deref(), Some("PILOT"));
        assert_eq!(claims.rsi_citizen_id.as_deref(), Some("12345"));
        assert_eq!(claims.rsi_spectrum_id.as_deref(), Some("6789"));
        assert_eq!(claims.discord_account_id.as_deref(), Some("111222333"));
        assert!(claims.rsi_verified);
    }

    #[test]
    fn rejects_wrong_issuer_and_missing_trailing_slash() {
        assert!(
            validate_claims(
                &payload(serde_json::json!({"iss": "https://citizenid.space/"})),
                NOW
            )
            .is_err()
        );
        assert!(
            validate_claims(
                &payload(serde_json::json!({"iss": "https://citizenid.dev"})),
                NOW
            )
            .is_err()
        );
    }

    #[test]
    fn rejects_missing_and_wrong_audience() {
        assert!(
            validate_claims(
                &payload(serde_json::json!({"aud": "00000000-0000-0000-0000-000000000000"})),
                NOW
            )
            .is_err()
        );
        let mut no_aud = serde_json::json!({"iss": CITIZEN_ID_ISSUER, "sub": "s", "exp": NOW + 1});
        no_aud.as_object_mut().unwrap().remove("aud");
        assert!(validate_claims(&no_aud.to_string(), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"aud": []})), NOW).is_err());
        assert!(
            validate_claims(
                &payload(serde_json::json!({"aud": [CITIZEN_ID_CLIENT_ID, 5]})),
                NOW
            )
            .is_err()
        );
        assert!(validate_claims(&payload(serde_json::json!({"aud": 42})), NOW).is_err());
    }

    #[test]
    fn enforces_azp_with_multiple_audiences() {
        let multi = serde_json::json!({"aud": [CITIZEN_ID_CLIENT_ID, "other"]});
        assert!(validate_claims(&payload(multi.clone()), NOW).is_err());
        assert!(
            validate_claims(
                &payload(serde_json::json!({"aud": multi["aud"], "azp": "other"})),
                NOW
            )
            .is_err()
        );
        assert!(
            validate_claims(
                &payload(serde_json::json!({"aud": multi["aud"], "azp": CITIZEN_ID_CLIENT_ID})),
                NOW
            )
            .is_ok()
        );
        assert!(validate_claims(&payload(serde_json::json!({"azp": "other"})), NOW).is_err());
    }

    #[test]
    fn rejects_expired_missing_and_invalid_exp() {
        assert!(validate_claims(&payload(serde_json::json!({"exp": NOW})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"exp": NOW - 1})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"exp": "later"})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"exp": 1.5})), NOW).is_err());
        let mut no_exp =
            serde_json::json!({"iss": CITIZEN_ID_ISSUER, "sub": "s", "aud": CITIZEN_ID_CLIENT_ID});
        no_exp.as_object_mut().unwrap().remove("exp");
        assert!(validate_claims(&no_exp.to_string(), NOW).is_err());
    }

    #[test]
    fn rejects_future_and_invalid_nbf() {
        assert!(validate_claims(&payload(serde_json::json!({"nbf": NOW + 1})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"nbf": "soon"})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"nbf": NOW})), NOW).is_ok());
        assert!(validate_claims(&payload(serde_json::json!({"nbf": null})), NOW).is_ok());
    }

    #[test]
    fn rejects_blank_subject() {
        assert!(validate_claims(&payload(serde_json::json!({"sub": ""})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"sub": "   "})), NOW).is_err());
        assert!(validate_claims(&payload(serde_json::json!({"sub": 7})), NOW).is_err());
    }

    #[test]
    fn treats_absent_and_null_profile_claims_as_none() {
        let claims = validate_claims(
            &payload(serde_json::json!({
                "name": null,
                "urn:user:rsi:username": null,
                "urn:user:discord:accountId": null
            })),
            NOW,
        )
        .unwrap();
        assert_eq!(claims.display_name, None);
        assert_eq!(claims.rsi_handle, None);
        assert_eq!(claims.discord_account_id, None);
        assert_eq!(claims.rsi_primary_org, None);
        assert!(claims.rsi_public_orgs.is_empty());
    }

    #[test]
    fn parses_org_snapshot_claims() {
        let minimal = validate_claims(&payload(serde_json::json!({})), NOW).unwrap();
        assert_eq!(minimal.rsi_primary_org, None);
        assert!(minimal.rsi_public_orgs.is_empty());

        let nulled = validate_claims(
            &payload(serde_json::json!({
                "urn:user:rsi:orgs:primary": null,
                "urn:user:rsi:orgs:public": null
            })),
            NOW,
        )
        .unwrap();
        assert_eq!(nulled.rsi_primary_org, None);
        assert!(nulled.rsi_public_orgs.is_empty());

        let single = validate_claims(
            &payload(serde_json::json!({
                "urn:user:rsi:orgs:primary": "SRN",
                "urn:user:rsi:orgs:public": "SRN"
            })),
            NOW,
        )
        .unwrap();
        assert_eq!(single.rsi_primary_org.as_deref(), Some("SRN"));
        assert_eq!(single.rsi_public_orgs, vec!["SRN"]);

        let empty = validate_claims(
            &payload(serde_json::json!({"urn:user:rsi:orgs:public": []})),
            NOW,
        )
        .unwrap();
        assert!(empty.rsi_public_orgs.is_empty());
    }

    #[test]
    fn rejects_malformed_org_claims() {
        for overrides in [
            serde_json::json!({"urn:user:rsi:orgs:primary": 42}),
            serde_json::json!({"urn:user:rsi:orgs:primary": {"handle": "SRN"}}),
            serde_json::json!({"urn:user:rsi:orgs:public": ["SRN", 5]}),
            serde_json::json!({"urn:user:rsi:orgs:public": 7}),
            serde_json::json!({"urn:user:rsi:orgs:public": {"orgs": ["SRN"]}}),
        ] {
            let token = payload(overrides.clone());
            assert!(
                validate_claims(&token, NOW).is_err(),
                "expected rejection for {overrides}"
            );
        }
    }

    #[test]
    fn rejects_malformed_profile_claims() {
        for key in [
            "name",
            "preferred_username",
            "urn:user:rsi:username",
            "urn:user:rsi:citizenId",
            "urn:user:rsi:spectrumId",
            "urn:user:discord:accountId",
        ] {
            let overrides = serde_json::json!({ key: 42 });
            assert!(
                validate_claims(&payload(overrides), NOW).is_err(),
                "expected rejection for {key}"
            );
        }
    }

    #[test]
    fn detects_verified_role_in_role_or_roles_string_or_array() {
        for overrides in [
            serde_json::json!({"role": "CitizenId/Status/Verified"}),
            serde_json::json!({"roles": "CitizenId/Status/Verified"}),
            serde_json::json!({"role": ["CitizenId/User", "CitizenId/Status/Verified"]}),
            serde_json::json!({"roles": ["CitizenId/Status/Verified"]}),
        ] {
            let claims = validate_claims(&payload(overrides), NOW).unwrap();
            assert!(claims.rsi_verified);
        }
    }

    #[test]
    fn non_verified_roles_do_not_grant_verification() {
        for overrides in [
            serde_json::json!({"role": "CitizenId/User"}),
            serde_json::json!({"roles": ["CitizenId/Admin", "CitizenId/Status/Unverified"]}),
            serde_json::json!({"role": "citizenid/status/verified"}),
            serde_json::json!({"roles": 42}),
        ] {
            let claims = validate_claims(&payload(overrides), NOW).unwrap();
            assert!(!claims.rsi_verified);
        }
    }

    #[test]
    fn rejects_malformed_payload() {
        assert!(validate_claims("not json", NOW).is_err());
        assert!(validate_claims("[1,2]", NOW).is_err());
        assert!(validate_claims("{}", NOW).is_err());
    }
}
