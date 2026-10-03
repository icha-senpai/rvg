import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

export const spacetimeVersion = '2.10.2';

export function runSpacetime(args) {
  const installedCli = process.platform === 'win32' && process.env.LOCALAPPDATA
    ? join(process.env.LOCALAPPDATA, 'SpacetimeDB', 'bin', spacetimeVersion, 'spacetimedb-cli.exe')
    : undefined;
  const cli = installedCli && existsSync(installedCli) ? installedCli : 'spacetime';
  const version = spawnSync(cli, ['--version'], { encoding: 'utf8' });

  if (version.error || version.status !== 0 || !version.stdout.includes(`tool version ${spacetimeVersion};`)) {
    throw new Error(`Install SpaceTimeDB ${spacetimeVersion} with: spacetime version install ${spacetimeVersion}`);
  }

  const result = spawnSync(cli, args, { stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`SpaceTimeDB exited with status ${result.status}`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  runSpacetime(process.argv.slice(2));
}
