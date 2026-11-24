param([switch]$Apply)
$ErrorActionPreference = 'Stop'
$project = [IO.Path]::GetFullPath($PSScriptRoot)
$horizon = Join-Path $project 'horizon 2'
$runtime = Join-Path $horizon '.runtime'

function Assert-Unlinked([string]$Path) {
    $cursor = [IO.Path]::GetFullPath($Path)
    if (-not $cursor.StartsWith($project + '\', [StringComparison]::OrdinalIgnoreCase)) { throw "Outside dev2: $Path" }
    while ($cursor -ne $project) {
        if ((Get-Item -LiteralPath $cursor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Linked cleanup path: $cursor" }
        $cursor = Split-Path $cursor -Parent
    }
}
function Get-ReviewedEntries([string]$Path) {
    Assert-Unlinked $Path
    $item = Get-Item -LiteralPath $Path -Force
    $item
    if ($item.PSIsContainer) {
        foreach ($child in Get-ChildItem -LiteralPath $Path -Force) { Get-ReviewedEntries $child.FullName }
    }
}
$targets = [Collections.Generic.List[string]]::new()
foreach ($relative in @(
    'backend\playwright-report', 'backend\test-results',
    'horizon 2\.playwright-cli', 'horizon 2\test-results', 'horizon 2\apps\web\test-results',
    'horizon 2\.runtime\bindings', 'horizon 2\.runtime\playwright-output',
    'horizon 2\.runtime\claims-harness\target', 'horizon 2\.runtime\claims-harness\test-output.txt'
)) {
    $path = Join-Path $project $relative
    if (Test-Path -LiteralPath $path) { $targets.Add($path) }
}
# This allowlist contains only inspected one-off probes/results and a stale config copy.
# Current web, edge and tunnel logs, database storage and unknown future files stay intact.
foreach ($item in Get-ChildItem -LiteralPath $runtime -Force -File) {
    if ($item.Name -eq 'cloudflared-config.before.yaml' -or
        $item.Name -match '^(?:auth-eslint|citizenid-tunnel-origin|edge-probe|goto-probe|horizon-citizenid-.+|horizon-domain-.+|org-.+|original-db-after|owned-.+|prettier-baseline|rmnul2?|shared-sites-head|tunnel-connectors|ws-probe)\.(?:txt|mjs|png|json|py)$') {
        $targets.Add($item.FullName)
    }
}
$processes = @(Get-CimInstance Win32_Process | Where-Object { $_.ProcessId -ne $PID })
# Relative working directories can hide a Cargo build from path matching. Leave its cache alone.
if ($processes | Where-Object { $_.Name -match '^(cargo|rustc|claims-harness.*)\.exe$' }) { throw 'A Rust build/test is active; leave generated test artifacts intact until it finishes.' }
$entries = @()
foreach ($target in $targets) {
    $relative = $target.Substring($project.Length + 1)
    $horizonRelative = if ($target.StartsWith($horizon + '\', [StringComparison]::OrdinalIgnoreCase)) { $target.Substring($horizon.Length + 1) } else { $null }
    foreach ($process in $processes) {
        foreach ($reference in @($target, $relative, $horizonRelative) | Where-Object { $_ }) {
            if (($process.CommandLine -and $process.CommandLine.Replace('/', '\').IndexOf($reference, [StringComparison]::OrdinalIgnoreCase) -ge 0) -or
                ($process.ExecutablePath -and $process.ExecutablePath.StartsWith($target, [StringComparison]::OrdinalIgnoreCase))) { throw "Active cleanup target: $target" }
        }
    }
    $entries += @(Get-ReviewedEntries $target | ForEach-Object {
        [pscustomobject]@{Path=$_.FullName;Directory=$_.PSIsContainer;Bytes=if ($_.PSIsContainer) {0} else {$_.Length};Modified=$_.LastWriteTimeUtc.Ticks}
    })
}
$bytes = ($entries | Measure-Object -Property Bytes -Sum).Sum
Write-Host "$($targets.Count) scoped targets, $($entries.Count) entries, $([Math]::Round($bytes / 1MB, 2)) MiB."
foreach ($target in $targets) { Write-Host $target.Substring($project.Length + 1) }
if (-not $Apply) { Write-Host 'Preview only. No cleanup plan or report saved.'; exit 0 }
foreach ($entry in $entries) {
    Assert-Unlinked $entry.Path
    $current = Get-Item -LiteralPath $entry.Path -Force
    if ($current.PSIsContainer -ne $entry.Directory -or $current.LastWriteTimeUtc.Ticks -ne $entry.Modified -or (-not $entry.Directory -and $current.Length -ne $entry.Bytes)) { throw "Cleanup entry changed: $($entry.Path)" }
}
foreach ($entry in $entries | Where-Object { -not $_.Directory }) { Remove-Item -LiteralPath $entry.Path -Force }
foreach ($entry in $entries | Where-Object Directory | Sort-Object { $_.Path.Length } -Descending) {
    Assert-Unlinked $entry.Path
    [IO.Directory]::Delete($entry.Path, $false)
}
Write-Host "Removed $bytes bytes. Live data, uploads, credentials, current builds, dependencies, logs and test source preserved."
