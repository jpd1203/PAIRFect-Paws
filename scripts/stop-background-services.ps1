$ErrorActionPreference = 'Stop'

& (Join-Path $PSScriptRoot 'stop-scheduler.ps1')
& (Join-Path $PSScriptRoot 'stop-queue-worker.ps1')

Write-Output 'PAIRfect Paws background email services are stopped.'
