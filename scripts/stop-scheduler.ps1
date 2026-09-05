$ErrorActionPreference = 'Stop'
$commonScript = Join-Path $PSScriptRoot '_background-service-common.ps1'
. $commonScript

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pidFile = Join-Path $projectRoot 'storage\framework\scheduler.pid'
$artisanPath = Join-Path $projectRoot 'artisan'

Stop-PairfectArtisanService `
    -ServiceName 'Scheduler' `
    -PidFile $pidFile `
    -ArtisanPath $artisanPath `
    -CommandMarker 'schedule:work'
