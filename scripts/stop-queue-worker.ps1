$ErrorActionPreference = 'Stop'
$commonScript = Join-Path $PSScriptRoot '_background-service-common.ps1'
. $commonScript

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pidFile = Join-Path $projectRoot 'storage\framework\queue-worker.pid'
$artisanPath = Join-Path $projectRoot 'artisan'

Stop-PairfectArtisanService `
    -ServiceName 'Queue worker' `
    -PidFile $pidFile `
    -ArtisanPath $artisanPath `
    -CommandMarker 'queue:work'
