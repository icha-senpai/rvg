# Horizon 2

The separately scaffolded Horizon Interstellar rebuild. The supplied foundation specification is preserved in [docs/architecture/foundation-design-spec.md](docs/architecture/foundation-design-spec.md). Horizon 1 remains the behavioral reference in the parent repository.

The first source-backed workflow review is in [docs/domains/operations-behavior-map.md](docs/domains/operations-behavior-map.md). It records Horizon 1 operation behavior, test references, and decisions to resolve before implementation; it is not an implemented Horizon 2 feature.

Citizen iD discovery is in [docs/domains/citizenid-identity-research.md](docs/domains/citizenid-identity-research.md). It records the current public OAuth/OIDC, RSI claim, Discord claim, and integration constraints before any auth implementation.

## Installed foundation

- `apps/web`: Svelte 5, SvelteKit, TypeScript, Vite, static adapter, and vanilla CSS. TipTap core, ProseMirror, and StarterKit are installed for a future Horizon editor wrapper.
- `services/spacetime`: Rust SpaceTimeDB 2.10.2 module, with an initialization reducer and no domain schema yet.
- `services/discord`: Rust executable with Twilight Gateway/HTTP/model, Tokio, tracing, and the SpaceTimeDB SDK. It compiles the generated Rust bindings; running it currently only reports that the scaffold is ready.
- `apps/desktop`: Tauri 2 shell using the same built Svelte UI.
- `packages/generated`: generated TypeScript and Rust SpaceTimeDB bindings. Never edit these by hand.
- `tooling`: project-pinned SpaceTimeDB CLI wrapper and binding generation.
- ESLint, Prettier, Svelte checks, Rustfmt, Clippy, and Playwright with Chromium.

Dependencies are locked in `package-lock.json` and `Cargo.lock`. Rust is pinned by `rust-toolchain.toml`. Use Node 24 and npm 11 or newer.

## Setup

Run these commands from this folder:

```powershell
npm ci
spacetime version install 2.10.2
npm run bindings
npm exec -- playwright install chromium
```

On Windows, the CLI wrapper uses the installed 2.10.2 executable directly without changing other projects' global SpaceTimeDB version. On other systems, the `spacetime` on PATH must be 2.10.2.

Windows desktop builds require Visual Studio C++ build tools and the WebView2 runtime. Both were present during initial setup.

## Development

```powershell
npm run dev
```

The initial route is a minimal Horizon 2 placeholder at `http://127.0.0.1:5173`. Penpot artwork and UI design are not implemented yet. The web app is a client-only static shell; Horizon domain behavior will live in SpaceTimeDB reducers.

Desktop development starts the same web client:

```powershell
npm run desktop:dev
```

Do not separately start the web dev server on the same port when using this command. Production desktop compilation without an installer:

```powershell
npm run desktop:build -- --no-bundle
```

For the SpaceTimeDB module:

```powershell
npm run spacetime -- build --module-path services/spacetime
npm run bindings
```

No database is published during setup. Before starting a local database, check whether port 3000 already belongs to another project. Use `npm run spacetime -- start --help` to choose a dedicated listen address and data directory.

## Verification

```powershell
npm run check
npm run lint
npm run build
cargo fmt --all --check
cargo clippy --workspace --all-targets -- -D warnings
cargo test -p horizon-discord -p horizon-desktop --all-targets
```

`npm run format` formats the web source. Regenerate bindings whenever the module schema or reducer signatures change. The SpaceTimeDB module is verified by its WASM build: its runtime imports cannot link into a native Windows test executable. Reducer integration tests must execute in a SpaceTimeDB host; native unit tests can cover pure domain functions when those exist. Domain tests and E2E workflows will be added with their implementation; the initial native test harnesses contain zero tests.

## Next Integration Steps

The install is not the foundation definition of done. These are still outstanding:

- Citizen iD/OIDC registration, authenticated SpaceTimeDB connection, and the identity/authorization proof.
- Discord application credentials, account mapping, gateway connection, interaction handling, and durable jobs.
- R2 account/bucket configuration and signed uploads. The optional Axum edge service is deferred until a concrete trusted HTTP integration needs it.
- Penpot source assets, tokens, UI primitives, and the operation vertical slice.
- PWA manifest, actual Horizon icons, shell caching, reconnection, install testing, and push notifications. SvelteKit provides native service-worker support; a PWA dependency is not needed at this stage.
- CI enforcement and generated-binding drift checks when CI is established.
- Production endpoints and desktop CSP updates for those endpoints. The current desktop CSP permits only the local SpaceTimeDB endpoint.
- Replace the scaffold's Svelte/Tauri icons before distribution.

Environment examples document the upcoming connection settings. They are not consumed by live integrations yet. Keep credentials out of committed files.
