use spacetimedb::{ReducerContext, reducer};

// Domain tables and reducers will be added after the identity boundary is proven.
#[reducer(init)]
pub fn init(_ctx: &ReducerContext) {
    spacetimedb::log::info!("Horizon 2 module initialized");
}
