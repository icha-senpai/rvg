#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    tracing_subscriber::fmt()
        .with_env_filter(
            tracing_subscriber::EnvFilter::try_from_default_env()
                .unwrap_or_else(|_| tracing_subscriber::EnvFilter::new("info")),
        )
        .init();

    let listener = tokio::net::TcpListener::bind("127.0.0.1:3102").await?;
    tracing::info!(addr = %listener.local_addr()?, "horizon-edge listening");
    axum::serve(listener, horizon_edge::router()).await?;
    Ok(())
}
