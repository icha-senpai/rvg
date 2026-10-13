use std::sync::Arc;
use std::time::Duration;

use axum::Router;
use axum::extract::rejection::FormRejection;
use axum::extract::{DefaultBodyLimit, FromRequest, Request, State};
use axum::http::{HeaderMap, StatusCode, header};
use axum::middleware::{self, Next};
use axum::response::{IntoResponse, Response};
use axum::routing::post;
use axum::{Form, Json};
use serde::Deserialize;
use serde::Serialize;
use serde_json::json;
use tokio::sync::Semaphore;

const UPSTREAM_TOKEN_URL: &str = "https://citizenid.dev/connect/token";
const EXPECTED_CLIENT_ID: &str = "6192d6ef-f599-4ec3-9d99-7429ef934a86";
const EXPECTED_REDIRECT_URI: &str = "https://horizon.ichaa.dev/auth/callback";
const ALLOWED_ORIGIN: &str = "https://horizon.ichaa.dev";
const MAX_BODY_BYTES: usize = 16 * 1024;
const MAX_UPSTREAM_BODY_BYTES: usize = 1024 * 1024;
const MAX_IN_FLIGHT: usize = 16;

const ALLOWED_UPSTREAM_ERRORS: &[&str] = &[
    "invalid_request",
    "invalid_client",
    "invalid_grant",
    "unauthorized_client",
    "unsupported_grant_type",
    "invalid_scope",
    "access_denied",
    "server_error",
    "temporarily_unavailable",
];

fn description_for(error: &str) -> &'static str {
    match error {
        "invalid_request" => "The token request was invalid.",
        "invalid_client" => "The client was rejected by the identity provider.",
        "invalid_grant" => "The authorization grant was rejected.",
        "unauthorized_client" => "The client is not authorized for this grant.",
        "unsupported_grant_type" => "The grant type is not supported.",
        "invalid_scope" => "The requested scope is invalid.",
        "access_denied" => "The request was denied.",
        "server_error" => "The identity provider encountered an error.",
        "temporarily_unavailable" => "The token service is temporarily unavailable.",
        _ => "The token request failed.",
    }
}

fn oauth_error(status: StatusCode, error: &'static str) -> Response {
    (
        status,
        Json(json!({ "error": error, "error_description": description_for(error) })),
    )
        .into_response()
}

struct AppState {
    client: reqwest::Client,
    upstream: String,
    permits: Semaphore,
}

#[derive(Debug, Deserialize, Serialize)]
#[serde(deny_unknown_fields)]
struct TokenForm {
    grant_type: String,
    client_id: String,
    redirect_uri: String,
    code: String,
    code_verifier: String,
}

struct ValidatedForm(TokenForm);

impl<S> FromRequest<S> for ValidatedForm
where
    S: Send + Sync,
{
    type Rejection = Response;

    async fn from_request(req: Request, state: &S) -> Result<Self, Self::Rejection> {
        match Form::<TokenForm>::from_request(req, state).await {
            Ok(Form(form)) => Ok(ValidatedForm(form)),
            Err(rejection) => Err(form_error(rejection)),
        }
    }
}

fn form_error(rejection: FormRejection) -> Response {
    if rejection.status() == StatusCode::PAYLOAD_TOO_LARGE {
        return oauth_error(StatusCode::PAYLOAD_TOO_LARGE, "invalid_request");
    }
    oauth_error(StatusCode::BAD_REQUEST, "invalid_request")
}

fn valid_code(code: &str) -> bool {
    !code.is_empty() && code.len() <= 8192 && code.bytes().all(|b| b.is_ascii_graphic())
}

fn valid_verifier(verifier: &str) -> bool {
    (43..=128).contains(&verifier.len())
        && verifier
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || matches!(b, b'.' | b'_' | b'~' | b'-'))
}

async fn add_no_store(req: Request, next: Next) -> Response {
    let mut response = next.run(req).await;
    let headers = response.headers_mut();
    headers.insert(header::CACHE_CONTROL, "no-store".parse().unwrap());
    headers.insert(header::PRAGMA, "no-cache".parse().unwrap());
    response
}

pub fn router() -> Router {
    let client = reqwest::Client::builder()
        .redirect(reqwest::redirect::Policy::none())
        .connect_timeout(Duration::from_secs(5))
        .timeout(Duration::from_secs(15))
        .build()
        .expect("edge HTTP client configuration is valid");
    router_with_state(Arc::new(AppState {
        client,
        upstream: UPSTREAM_TOKEN_URL.to_string(),
        permits: Semaphore::new(MAX_IN_FLIGHT),
    }))
}

fn router_with_state(state: Arc<AppState>) -> Router {
    Router::new()
        .route("/auth/token", post(exchange_token))
        .layer(DefaultBodyLimit::max(MAX_BODY_BYTES))
        .layer(middleware::from_fn(add_no_store))
        .with_state(state)
}

async fn exchange_token(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
    ValidatedForm(form): ValidatedForm,
) -> Response {
    let Ok(_permit) = state.permits.try_acquire() else {
        return oauth_error(StatusCode::SERVICE_UNAVAILABLE, "temporarily_unavailable");
    };

    let origin_ok = headers
        .get(header::ORIGIN)
        .and_then(|v| v.to_str().ok())
        .is_some_and(|o| o == ALLOWED_ORIGIN);
    if !origin_ok {
        return oauth_error(StatusCode::FORBIDDEN, "invalid_request");
    }

    if headers.contains_key(header::AUTHORIZATION) {
        return oauth_error(StatusCode::BAD_REQUEST, "invalid_request");
    }

    if form.grant_type != "authorization_code" {
        return oauth_error(StatusCode::BAD_REQUEST, "unsupported_grant_type");
    }
    if form.client_id != EXPECTED_CLIENT_ID
        || form.redirect_uri != EXPECTED_REDIRECT_URI
        || !valid_code(&form.code)
        || !valid_verifier(&form.code_verifier)
    {
        return oauth_error(StatusCode::BAD_REQUEST, "invalid_request");
    }

    let upstream = state
        .client
        .post(&state.upstream)
        .header(header::ACCEPT, "application/json")
        .form(&form)
        .send()
        .await;

    let mut upstream = match upstream {
        Ok(response) => response,
        Err(err) => {
            tracing::warn!(timeout = err.is_timeout(), "upstream token request failed");
            return if err.is_timeout() || err.is_connect() {
                oauth_error(StatusCode::SERVICE_UNAVAILABLE, "temporarily_unavailable")
            } else {
                oauth_error(StatusCode::BAD_GATEWAY, "server_error")
            };
        }
    };

    let status = upstream.status();
    if let Some(len) = upstream.content_length()
        && len > MAX_UPSTREAM_BODY_BYTES as u64
    {
        return oauth_error(StatusCode::BAD_GATEWAY, "server_error");
    }

    let mut body = Vec::new();
    loop {
        match upstream.chunk().await {
            Ok(Some(chunk)) => {
                if body.len() + chunk.len() > MAX_UPSTREAM_BODY_BYTES {
                    return oauth_error(StatusCode::BAD_GATEWAY, "server_error");
                }
                body.extend_from_slice(&chunk);
            }
            Ok(None) => break,
            Err(_) => return oauth_error(StatusCode::BAD_GATEWAY, "server_error"),
        }
    }

    let payload: serde_json::Value = match serde_json::from_slice(&body) {
        Ok(value) => value,
        Err(_) => return oauth_error(StatusCode::BAD_GATEWAY, "server_error"),
    };

    if status.is_success() {
        return token_success(&payload);
    }

    let upstream_error = payload.get("error").and_then(|v| v.as_str());
    let error = ALLOWED_UPSTREAM_ERRORS
        .iter()
        .copied()
        .find(|e| Some(*e) == upstream_error)
        .unwrap_or(if status.is_client_error() {
            "invalid_request"
        } else {
            "server_error"
        });
    tracing::warn!(status = status.as_u16(), error, "upstream token rejection");

    if status.is_client_error() {
        oauth_error(
            StatusCode::from_u16(status.as_u16()).unwrap_or(StatusCode::BAD_REQUEST),
            error,
        )
    } else {
        oauth_error(StatusCode::BAD_GATEWAY, "server_error")
    }
}

fn token_success(payload: &serde_json::Value) -> Response {
    let get_str = |key: &str| {
        payload
            .get(key)
            .and_then(|v| v.as_str())
            .filter(|s| !s.is_empty())
    };
    let (Some(access_token), Some(id_token), Some(token_type)) = (
        get_str("access_token"),
        get_str("id_token"),
        get_str("token_type"),
    ) else {
        return oauth_error(StatusCode::BAD_GATEWAY, "server_error");
    };

    let mut out = json!({
        "access_token": access_token,
        "id_token": id_token,
        "token_type": token_type
    });
    if let Some(expires_in) = payload.get("expires_in").and_then(|v| v.as_u64()) {
        out["expires_in"] = json!(expires_in);
    }
    if let Some(scope) = get_str("scope") {
        out["scope"] = json!(scope);
    }
    (StatusCode::OK, Json(out)).into_response()
}

#[cfg(test)]
mod tests {
    use super::*;
    use axum::body::Body;
    use axum::http::Request;
    use http_body_util::BodyExt;
    use std::sync::Mutex;
    use tower::ServiceExt;

    const ORIGIN: &str = "https://horizon.ichaa.dev";

    #[derive(Clone)]
    struct StubBehavior {
        status: StatusCode,
        body: Vec<u8>,
        delay: Option<Duration>,
        location: Option<String>,
    }

    impl StubBehavior {
        fn json(status: StatusCode, body: &'static str) -> Self {
            Self {
                status,
                body: body.as_bytes().to_vec(),
                delay: None,
                location: None,
            }
        }
    }

    async fn stub_handler(
        State((behavior, received)): State<(StubBehavior, Arc<Mutex<Vec<String>>>)>,
        body: String,
    ) -> Response {
        received.lock().unwrap().push(body);
        if behavior.delay.is_some() {
            std::future::pending::<()>().await;
        }
        let mut response = behavior.body.clone().into_response();
        *response.status_mut() = behavior.status;
        response
            .headers_mut()
            .insert(header::CONTENT_TYPE, "application/json".parse().unwrap());
        if let Some(location) = &behavior.location {
            response
                .headers_mut()
                .insert(header::LOCATION, location.parse().unwrap());
        }
        response
    }

    async fn spawn_stub(behavior: StubBehavior) -> (String, Arc<Mutex<Vec<String>>>) {
        let received = Arc::new(Mutex::new(Vec::new()));
        let app = Router::new()
            .route("/token", post(stub_handler))
            .with_state((behavior, received.clone()));
        let listener = tokio::net::TcpListener::bind("127.0.0.1:0").await.unwrap();
        let addr = listener.local_addr().unwrap();
        tokio::spawn(async move { axum::serve(listener, app).await.unwrap() });
        (format!("http://{addr}/token"), received)
    }

    fn test_app(upstream: &str, permits: usize, timeout: Duration) -> Router {
        let client = reqwest::Client::builder()
            .redirect(reqwest::redirect::Policy::none())
            .connect_timeout(Duration::from_secs(2))
            .timeout(timeout)
            .build()
            .unwrap();
        router_with_state(Arc::new(AppState {
            client,
            upstream: upstream.to_string(),
            permits: Semaphore::new(permits),
        }))
    }

    fn canonical_form() -> String {
        "grant_type=authorization_code&client_id=6192d6ef-f599-4ec3-9d99-7429ef934a86&redirect_uri=https%3A%2F%2Fhorizon.ichaa.dev%2Fauth%2Fcallback&code=probe-code-123&code_verifier=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"
			.to_string()
    }

    fn token_request(
        origin: Option<&str>,
        content_type: Option<&str>,
        body: impl Into<Body>,
    ) -> Request<Body> {
        let mut builder = Request::builder().method("POST").uri("/auth/token");
        if let Some(origin) = origin {
            builder = builder.header(header::ORIGIN, origin);
        }
        if let Some(content_type) = content_type {
            builder = builder.header(header::CONTENT_TYPE, content_type);
        }
        builder.body(body.into()).unwrap()
    }

    fn good_request() -> Request<Body> {
        token_request(
            Some(ORIGIN),
            Some("application/x-www-form-urlencoded"),
            canonical_form(),
        )
    }

    async fn response_json(response: Response) -> serde_json::Value {
        let body = response.into_body().collect().await.unwrap().to_bytes();
        serde_json::from_slice(&body).unwrap()
    }

    #[tokio::test]
    async fn forwards_five_fields_and_filters_response() {
        let (upstream, received) = spawn_stub(StubBehavior::json(
			StatusCode::OK,
			r#"{"access_token":"AT","id_token":"IT","token_type":"Bearer","expires_in":300,"scope":"openid profile","refresh_token":"SECRET","unknown":"x"}"#
		))
		.await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));

        let response = app.oneshot(good_request()).await.unwrap();
        assert_eq!(response.status(), StatusCode::OK);
        assert_eq!(
            response.headers().get(header::CACHE_CONTROL).unwrap(),
            "no-store"
        );
        assert_eq!(response.headers().get(header::PRAGMA).unwrap(), "no-cache");
        let json = response_json(response).await;
        assert_eq!(
            json,
            json!({
                "access_token": "AT",
                "id_token": "IT",
                "token_type": "Bearer",
                "expires_in": 300,
                "scope": "openid profile"
            })
        );

        let received = received.lock().unwrap();
        assert_eq!(received.len(), 1);
        assert_eq!(received[0], canonical_form());
    }

    #[tokio::test]
    async fn missing_or_wrong_origin_rejected_before_upstream() {
        let (upstream, received) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));

        for origin in [
            None,
            Some("https://evil.example"),
            Some("http://127.0.0.1:5273"),
        ] {
            let response = app
                .clone()
                .oneshot(token_request(
                    origin,
                    Some("application/x-www-form-urlencoded"),
                    canonical_form(),
                ))
                .await
                .unwrap();
            assert_eq!(response.status(), StatusCode::FORBIDDEN);
            assert_eq!(response_json(response).await["error"], "invalid_request");
        }
        assert!(received.lock().unwrap().is_empty());
    }

    #[tokio::test]
    async fn authorization_header_rejected_before_upstream() {
        let (upstream, received) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));

        let mut request = good_request();
        request
            .headers_mut()
            .insert(header::AUTHORIZATION, "Bearer test".parse().unwrap());
        let response = app.oneshot(request).await.unwrap();
        assert_eq!(response.status(), StatusCode::BAD_REQUEST);
        assert_eq!(response_json(response).await["error"], "invalid_request");
        assert!(received.lock().unwrap().is_empty());
    }

    #[tokio::test]
    async fn invalid_form_fields_rejected_before_upstream() {
        let (upstream, received) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));

        let base = canonical_form();
        let cases = [
			base.replace("grant_type=authorization_code", "grant_type=password"),
			base.replace("client_id=6192d6ef", "client_id=00000000"),
			base.replace("redirect_uri=https%3A%2F%2Fhorizon.ichaa.dev", "redirect_uri=https%3A%2F%2Fevil.example"),
			base.replace("code=probe-code-123", "code="),
			base.replace("code=probe-code-123", "code=has%20space"),
			base.replace(
				"code_verifier=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
				"code_verifier=tooshort"
			),
			base.replace(
				"code_verifier=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
				"code_verifier=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA!"
			),
			base.replace(
				"code_verifier=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
				"code_verifier=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"
			),
			format!("{base}&extra_field=1"),
			format!("{base}&code=second-value")
		];
        for body in cases {
            let response = app
                .clone()
                .oneshot(token_request(
                    Some(ORIGIN),
                    Some("application/x-www-form-urlencoded"),
                    body,
                ))
                .await
                .unwrap();
            assert_eq!(response.status(), StatusCode::BAD_REQUEST);
            response_json(response).await;
        }
        assert!(received.lock().unwrap().is_empty());
    }

    #[tokio::test]
    async fn oversized_body_gets_controlled_413() {
        let (upstream, received) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));

        let body = format!("{}&code={}", canonical_form(), "A".repeat(17 * 1024));
        let response = app
            .oneshot(token_request(
                Some(ORIGIN),
                Some("application/x-www-form-urlencoded"),
                body,
            ))
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::PAYLOAD_TOO_LARGE);
        assert_eq!(response_json(response).await["error"], "invalid_request");
        assert!(received.lock().unwrap().is_empty());
    }

    #[tokio::test]
    async fn wrong_content_type_gets_controlled_json() {
        let (upstream, received) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));

        for content_type in [None, Some("text/plain"), Some("application/json")] {
            let response = app
                .clone()
                .oneshot(token_request(Some(ORIGIN), content_type, canonical_form()))
                .await
                .unwrap();
            assert_eq!(response.status(), StatusCode::BAD_REQUEST);
            assert_eq!(response_json(response).await["error"], "invalid_request");
        }
        assert!(received.lock().unwrap().is_empty());
    }

    #[tokio::test]
    async fn upstream_rejection_maps_to_safe_error() {
        let cases = [
            (
                StatusCode::BAD_REQUEST,
                r#"{"error":"invalid_grant","error_description":"SECRET details","error_uri":"https://x"}"#,
                StatusCode::BAD_REQUEST,
                "invalid_grant",
            ),
            (
                StatusCode::UNAUTHORIZED,
                r#"{"error":"invalid_client","error_description":"SECRET details"}"#,
                StatusCode::UNAUTHORIZED,
                "invalid_client",
            ),
            (
                StatusCode::INTERNAL_SERVER_ERROR,
                r#"{"error":"server_error","error_description":"SECRET details"}"#,
                StatusCode::BAD_GATEWAY,
                "server_error",
            ),
            (
                StatusCode::BAD_REQUEST,
                r#"{"error":"weird_custom","error_description":"SECRET details"}"#,
                StatusCode::BAD_REQUEST,
                "invalid_request",
            ),
        ];
        for (upstream_status, upstream_body, want_status, want_error) in cases {
            let (upstream, _) =
                spawn_stub(StubBehavior::json(upstream_status, upstream_body)).await;
            let app = test_app(&upstream, 16, Duration::from_secs(5));
            let response = app.oneshot(good_request()).await.unwrap();
            assert_eq!(response.status(), want_status);
            let json = response_json(response).await;
            assert_eq!(json["error"], want_error);
            let text = json.to_string();
            assert!(!text.contains("SECRET"));
            assert!(!text.contains("error_uri"));
        }
    }

    #[tokio::test]
    async fn upstream_redirect_not_followed() {
        let (upstream, _) = spawn_stub(StubBehavior {
            location: Some("http://127.0.0.1:1/evil".to_string()),
            ..StubBehavior::json(StatusCode::FOUND, r#"{"error":"x"}"#)
        })
        .await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));
        let response = app.oneshot(good_request()).await.unwrap();
        assert_eq!(response.status(), StatusCode::BAD_GATEWAY);
    }

    #[tokio::test]
    async fn malformed_success_fails_safely() {
        for body in [
            r#"not json"#,
            r#"{"access_token":"AT","token_type":"Bearer"}"#,
            r#"{"access_token":"AT","id_token":"IT","token_type":""}"#,
        ] {
            let (upstream, _) = spawn_stub(StubBehavior::json(StatusCode::OK, body)).await;
            let app = test_app(&upstream, 16, Duration::from_secs(5));
            let response = app.oneshot(good_request()).await.unwrap();
            assert_eq!(response.status(), StatusCode::BAD_GATEWAY);
            assert_eq!(response_json(response).await["error"], "server_error");
        }
    }

    #[tokio::test]
    async fn oversized_upstream_body_fails_safely() {
        let (upstream, _) = spawn_stub(StubBehavior {
            body: vec![b'x'; 2 * 1024 * 1024],
            ..StubBehavior::json(StatusCode::OK, "")
        })
        .await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));
        let response = app.oneshot(good_request()).await.unwrap();
        assert_eq!(response.status(), StatusCode::BAD_GATEWAY);
    }

    #[tokio::test]
    async fn upstream_timeout_is_temporarily_unavailable() {
        let (upstream, _) = spawn_stub(StubBehavior {
            delay: Some(Duration::from_secs(2)),
            ..StubBehavior::json(StatusCode::OK, "{}")
        })
        .await;
        let app = test_app(&upstream, 16, Duration::from_millis(150));
        let response = app.oneshot(good_request()).await.unwrap();
        assert_eq!(response.status(), StatusCode::SERVICE_UNAVAILABLE);
        assert_eq!(
            response_json(response).await["error"],
            "temporarily_unavailable"
        );
    }

    #[tokio::test]
    async fn busy_semaphore_returns_503() {
        let (upstream, _) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let state = Arc::new(AppState {
            client: reqwest::Client::builder()
                .redirect(reqwest::redirect::Policy::none())
                .build()
                .unwrap(),
            upstream,
            permits: Semaphore::new(1),
        });
        state.permits.try_acquire().unwrap().forget();
        let app = router_with_state(state);
        let response = app.oneshot(good_request()).await.unwrap();
        assert_eq!(response.status(), StatusCode::SERVICE_UNAVAILABLE);
    }

    #[tokio::test]
    async fn method_rejection_is_controlled() {
        let (upstream, _) = spawn_stub(StubBehavior::json(StatusCode::OK, "{}")).await;
        let app = test_app(&upstream, 16, Duration::from_secs(5));
        let response = app
            .oneshot(
                Request::builder()
                    .uri("/auth/token")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::METHOD_NOT_ALLOWED);
        assert_eq!(
            response.headers().get(header::CACHE_CONTROL).unwrap(),
            "no-store"
        );
    }
}
