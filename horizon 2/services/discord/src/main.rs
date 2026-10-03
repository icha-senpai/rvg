// Shared schema bindings are generated from the Rust module, never maintained by hand.
#[rustfmt::skip]
#[path = "../../../packages/generated/rust/mod.rs"]
#[allow(dead_code, unused_imports)]
mod module_bindings;

fn main() {
    tracing_subscriber::fmt::init();
    tracing::info!("Horizon Discord dependencies are ready; live integration is not configured");
}
