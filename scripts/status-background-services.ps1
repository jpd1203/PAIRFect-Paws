$ErrorActionPreference = 'Stop'
$commonScript = Join-Path $PSScriptRoot '_background-service-common.ps1'
. $commonScript

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$artisanPath = Join-Path $projectRoot 'artisan'

$services = @(
    @{
        Name = 'Queue worker'
        PidFile = Join-Path $projectRoot 'storage\framework\queue-worker.pid'
        Marker = 'queue:work'
        OutputLog = Join-Path $projectRoot 'storage\logs\queue-worker.log'
        ErrorLog = Join-Path $projectRoot 'storage\logs\queue-worker-error.log'
    },
    @{
        Name = 'Scheduler'
        PidFile = Join-Path $projectRoot 'storage\framework\scheduler.pid'
        Marker = 'schedule:work'
        OutputLog = Join-Path $projectRoot 'storage\logs\scheduler.log'
        ErrorLog = Join-Path $projectRoot 'storage\logs\scheduler-error.log'
    }
)

foreach ($service in $services) {
    $state = Get-PairfectServiceState `
        -ServiceName $service.Name `
        -PidFile $service.PidFile `
        -ArtisanPath $artisanPath `
        -CommandMarker $service.Marker `
        -CleanStalePid

    [pscustomobject]@{
        Service   = $state.Service
        Status    = $state.Status
        ProcessId = $state.ProcessId
        OutputLog = $service.OutputLog
        ErrorLog  = $service.ErrorLog
    }
}
