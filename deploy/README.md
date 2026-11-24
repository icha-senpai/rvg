# Update Horizon

Horizon deploys the GitHub repository's `release` branch. Commit, test, and
push the intended changes to `origin/release` before running the updater.

From Windows PowerShell 7 in the repository, run:

```powershell
.\deploy\horizon-update.ps1 -Plan
.\deploy\horizon-update.ps1 -Apply
```

Or run the installed command over SSH:

```powershell
ssh horizon "horizon-update --plan"
ssh horizon "horizon-update"
```

The server command defaults to applying the release. The Windows wrapper
defaults to a plan. Both use the installed server command, which fetches
`origin/release` directly and fast-forwards the server's `release` checkout.
A plan fetches and reports the candidate commit and changed components.
It leaves the running release and services intact.

The updater refuses a dirty checkout, divergent history, and concurrent
deployments. Laravel and bot dependencies and frontend assets are staged
and audited before activation. Laravel updates use brief maintenance mode.
The queue and scheduler resume if they were running beforehand. Bot updates
restart an already running bot, including its normal Discord command
registration and nickname sync.

The deployment preserves server environment files, uploads, PostgreSQL and
Redis data, certificates, and system service settings. Schema changes under
`backend/database/migrations` or `backend/database/schema` require a separately
planned release. Horizon 2 source follows the release checkout, while its
separate services are outside this updater's build and activation scope.
Temporary build files are removed after the run.

If activation fails, the updater keeps an unfinished deployment marker with
the original service states. The next run retries that commit, provided it
is still in `origin/release` history. Run the command again after recovery to
deploy any newer release commit. A failed Laravel health check leaves the
application in maintenance mode for diagnosis.

## Server installation

The command runs as root and performs Git and build operations as
`horizon_rvg`. That account needs read access to the GitHub repository.

Install the tracked launcher once after the updated deployment files have
been checked out on the server:

```bash
install -o root -g root -m 0755 /srv/rvg/repo/deploy/horizon-update /usr/local/bin/horizon-update
```

The launcher runs a temporary copy of the checked-out updater so a release
can safely change its own deployment script. Subsequent runs use the
updater version delivered by the preceding successful release.
