# Horizon 2

This folder is the separately authorized Horizon 2 rebuild. The Laravel/Vue/Tailwind rules in the parent repository apply to Horizon 1; Horizon 2 follows `docs/architecture/foundation-design-spec.md`.

- Keep all Horizon 2 changes inside this folder. Do not modify Horizon 1 unless explicitly asked.
- Read the relevant architecture/domain documentation before changing a subsystem.
- Svelte 5, SvelteKit, TypeScript, and vanilla CSS are the web stack. Do not add Tailwind.
- SpaceTimeDB Rust reducers own domain writes and authorization. Clients and integrations are adapters.
- Reuse generated bindings; never hand-edit generated files.
- Keep sensitive state private and enforce read permissions on the server.
- Reuse established UI primitives and Penpot-derived design tokens when available.
- Explain the need before adding dependencies outside the approved foundation stack.
- Keep optional edge HTTP glue separate from domain rules; create it only when required.
- Preserve accessibility and keyboard interaction.
- Run relevant formatting, linting, checks, tests, and builds before finishing.

See `README.md` for setup, verification, and deferred integrations.

## Local backups and development artifacts

- Boss opts out of automatic deployment backups. Use Git for source history; keep no rollback sets, source copies, previous builds or dated audit/recovery archives unless explicitly requested.
- Remove one-off probes, browser output and temporary verification files after the check. Preserve requested design/research outputs and required test source.
- `.runtime/citizenid-auth` is live SpaceTimeDB data, not disposable auth-test output. Preserve the entire directory, credentials, current builds/bindings and active service logs. Never prune individual database logs or snapshots.
- Keep `.runtime/claims-harness` source and its Cargo files for the native claims tests; its generated `target` directory and saved test output are disposable when no build/test uses them.
- The parent `cleanup-local-artifacts.ps1` previews known disposable artifacts in both Horizons and saves no cleanup report. Only an explicitly requested backup may be retained; failed restoration must keep the temporary data needed to finish recovery.

## Citizen iD staging identity proof (verification notes)

- `services/edge` (`horizon-edge`) is the same-origin token-exchange bridge for the staging SPA; it binds `127.0.0.1:3102` and only accepts `POST /auth/token` from `Origin: https://horizon.ichaa.dev`, forwarding the five authorization-code fields to the fixed Citizen iD token endpoint. Dev routing goes through the Vite `server.proxy` entry (`^/auth/token$`); a production static deploy needs an equivalent reverse-proxy route to the edge service.
- Verify with `cargo test -p horizon-edge`, `cargo clippy -p horizon-edge --all-targets -- -D warnings`, `npm run check`, `npm run build`, and `npx playwright test apps/web/tests/citizen-id-auth.spec.ts --workers=1`.
- `services/spacetime` cannot link natively on Windows; run the pure claims tests via `cargo test` inside `.runtime/claims-harness` (includes `users/claims.rs` by path, serde_json `=1.0.151`).
