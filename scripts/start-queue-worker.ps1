param(
    [string] $PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$commonScript = Join-Path $PSScriptRoot '_background-service-common.ps1'
. $commonScript

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pidFile = Join-Path $projectRoot 'storage\framework\queue-worker.pid'
$outputLog = Join-Path $projectRoot 'storage\logs\queue-worker.log'
$errorLog = Join-Path $projectRoot 'storage\logs\queue-worker-error.log'

Start-PairfectArtisanService `
    -ServiceName 'Queue worker' `
    -PidFile $pidFile `
    -OutputLog $outputLog `
    -ErrorLog $errorLog `
    -CommandMarker 'queue:work' `
    -ArtisanArguments @(
        'queue:work', 'database',
        '--queue=emails,default', '--sleep=3', '--tries=3',
        '--backoff=60', '--timeout=60', '--no-interaction'
    ) `
    -PhpPath $PhpPath
