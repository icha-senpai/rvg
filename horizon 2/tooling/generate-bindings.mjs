import { runSpacetime } from './spacetime.mjs';

// Build once so both clients receive bindings from exactly the same module binary.
runSpacetime(['build', '--module-path', 'services/spacetime']);
const binary = 'target/wasm32-unknown-unknown/release/horizon_spacetime.wasm';

for (const [lang, outDir] of [
  ['typescript', 'packages/generated/typescript'],
  ['rust', 'packages/generated/rust']
]) {
  runSpacetime(['generate', '--lang', lang, '--bin-path', binary, '--out-dir', outDir, '--no-config', '--yes']);
}
