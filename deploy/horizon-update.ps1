#requires -Version 7.0
[CmdletBinding()]
param(
    [switch]$Plan,
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'
if ($Plan -and $Apply) { throw 'Choose either -Plan or -Apply.' }

$root = Split-Path $PSScriptRoot -Parent
$git = (Get-Command git.exe).Source
$sshOptions = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'ConnectTimeout=10')
$remote = (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'horizon-update.sh') -Raw) -replace "`r`n", "`n"

function Invoke-Git {
    & $git -C $root @args
    if ($LASTEXITCODE -ne 0) { throw "Git $($args[0]) failed (exit $LASTEXITCODE)." }
}

function Invoke-Horizon {
    $remote | ssh.exe @sshOptions horizon ("tr -d '\r' | bash -s -- " + ($args -join ' '))
    if ($LASTEXITCODE -ne 0) { throw "Horizon $($args[0]) failed (exit $LASTEXITCODE)." }
}

$target = Invoke-Git rev-parse HEAD
$status = @(Invoke-Horizon status)
if ($status.Count -ne 3 -or ($status | Where-Object { $_ -notmatch '^([0-9a-f]{40}|-)$' })) {
    throw 'Unrecognized deployment status from Horizon.'
}
$head, $base, $pending = $status
if ($pending -eq '-') { $base = $head }
if ($pending -ne '-' -and $pending -ne $target) {
    throw "Horizon has an unfinished deployment of $pending. Retry that commit before deploying another."
}

& $git -C $root merge-base --is-ancestor $head $target
if ($LASTEXITCODE -ne 0) { throw "Local HEAD is not a fast-forward from Horizon ($head)." }

$dirty = @(Invoke-Git status --porcelain --untracked-files=all '--' backend bots/horizon-bot)
$backend = @(Invoke-Git diff --name-only $base $target '--' backend)
$bot = @(Invoke-Git diff --name-only $base $target '--' bots/horizon-bot)
$migrations = @(Invoke-Git diff --name-only $base $target '--' backend/database/migrations backend/database/schema)

Write-Host "Horizon: $($head.Substring(0, 8)) -> $($target.Substring(0, 8))"
Write-Host "Laravel: $(if ($backend.Count) { 'stage PHP dependencies and web build' } else { 'unchanged' })"
Write-Host "Discord bot: $(if ($bot.Count) { 'stage dependencies and restart if running' } else { 'unchanged' })"
if ($dirty.Count) { Write-Host 'Uncommitted Laravel or bot changes will not be deployed.' }
if ($migrations.Count) { Write-Host 'Blocked: database migrations or schema files changed.' }
if (!$Apply) { return }
if ($dirty.Count) { throw 'Commit the Laravel and bot changes before applying.' }
if ($migrations.Count) { throw 'Review and deploy schema changes separately; this updater never runs migrations.' }
if ($head -eq $target -and $pending -eq '-') {
    Write-Host 'Horizon is already up to date.'
    return
}

$bundle = $null
$transfer = '-'
$checksum = '-'
try {
    if ($head -ne $target) {
        $gitDir = Invoke-Git rev-parse --absolute-git-dir
        $bundle = Join-Path $gitDir ('horizon-update-' + [guid]::NewGuid().ToString('N') + '.bundle')
        Invoke-Git bundle create $bundle HEAD "^$head"
        $checksum = (Get-FileHash -LiteralPath $bundle -Algorithm SHA256).Hash.ToLowerInvariant()
        $upload = Invoke-Horizon allocate
        if ($upload -notmatch '^/srv/rvg/\.horizon-transfer\.[A-Za-z0-9]{8}$') {
            throw 'Horizon returned an invalid upload directory.'
        }
        $transfer = $upload
        scp.exe -q -B @sshOptions $bundle "horizon:$transfer/update.bundle"
        if ($LASTEXITCODE -ne 0) { throw 'Git bundle upload failed.' }
    }
    Invoke-Horizon apply $head $base $target $transfer $checksum
} finally {
    if ($bundle -and (Test-Path -LiteralPath $bundle -PathType Leaf)) { Remove-Item -LiteralPath $bundle }
    if ($transfer -ne '-') {
        try { Invoke-Horizon discard $transfer }
        catch { Write-Warning "Could not clean the temporary upload at $transfer." }
    }
}
