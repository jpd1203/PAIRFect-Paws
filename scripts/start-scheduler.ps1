param(
    [string] $PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$commonScript = Join-Path $PSScriptRoot '_background-service-common.ps1'
. $commonScript

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pidFile = Join-Path $projectRoot 'storage\framework\scheduler.pid'
$outputLog = Join-Path $projectRoot 'storage\logs\scheduler.log'
$errorLog = Join-Path $projectRoot 'storage\logs\scheduler-error.log'

Start-PairfectArtisanService `
    -ServiceName 'Scheduler' `
    -PidFile $pidFile `
    -OutputLog $outputLog `
    -ErrorLog $errorLog `
    -CommandMarker 'schedule:work' `
    -ArtisanArguments @('schedule:work', '--no-interaction') `
    -PhpPath $PhpPath
