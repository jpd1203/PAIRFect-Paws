param(
    [string] $PhpPath = ''
)

$ErrorActionPreference = 'Stop'

# Resolve once before starting either process so an invalid PHP configuration
# cannot leave only half of the background services running.
$commonScript = Join-Path $PSScriptRoot '_background-service-common.ps1'
. $commonScript
$runtime = Resolve-PairfectPhp -PhpPath $PhpPath

& (Join-Path $PSScriptRoot 'start-queue-worker.ps1') -PhpPath $runtime.Path
& (Join-Path $PSScriptRoot 'start-scheduler.ps1') -PhpPath $runtime.Path

Write-Output 'PAIRfect Paws background email services are ready.'
