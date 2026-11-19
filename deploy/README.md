# Update Horizon

From a Windows PowerShell 7 terminal in the repository, run:

```powershell
.\deploy\horizon-update.ps1 -Plan
.\deploy\horizon-update.ps1 -Apply
```

The first command only inspects the local and server commits. The second
deploys tested, committed changes using the existing `ssh horizon` alias. It
does not push to GitHub or include uncommitted work. The server must be on an
ancestor of local `HEAD`, with a clean checkout. `-Apply` refuses uncommitted
Laravel or bot changes; unrelated Horizon 2 work is never built or started.

The updater locks the server against concurrent deploys. It stages and audits
only the affected Laravel or Discord bot components before switching them.
Laravel updates use brief maintenance mode while refreshing the generated
dependencies and frontend assets. The queue and scheduler resume only if they
were running beforehand. Bot updates restart an already running bot, which
runs its normal Discord command registration and nickname sync.

Server environment files, uploads, PostgreSQL and Redis data, certificates,
and system service settings are not replaced. Changes under
`backend/database/migrations` or `backend/database/schema` are blocked: schema
work needs a separately planned release. Temporary build and transfer files
are cleaned up; there are no retained release copies.

If a preflight or staging check fails, the active release is not switched.
If activation fails, inspect the reported error before rerunning `-Apply`
with the same local commit. The updater records the unfinished commit and the
original service states so a retry does not treat the partially updated
checkout as a completed release. If Laravel health fails, it stays in
maintenance rather than continuing with an unhealthy site.
