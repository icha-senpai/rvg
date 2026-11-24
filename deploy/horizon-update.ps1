#requires -Version 7.0
[CmdletBinding()]
param(
    [switch]$Plan,
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'
if ($Plan -and $Apply) { throw 'Choose either -Plan or -Apply.' }

$mode = if ($Apply) { '--apply' } else { '--plan' }
ssh.exe -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 horizon "horizon-update $mode"
if ($LASTEXITCODE -ne 0) { throw "Horizon deployment failed (exit $LASTEXITCODE)." }
