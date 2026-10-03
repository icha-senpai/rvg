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
