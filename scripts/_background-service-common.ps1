Set-StrictMode -Version Latest

function Get-PairfectProjectRoot {
    return (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
}

function Resolve-PairfectPhp {
    param(
        [string] $PhpPath = ''
    )

    $minimumVersionId = 80401
    $projectRoot = Get-PairfectProjectRoot
    $candidatePaths = [System.Collections.Generic.List[string]]::new()

    if ($PhpPath) {
        if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
            throw "The requested PHP executable does not exist: $PhpPath"
        }

        $candidatePaths.Add((Resolve-Path -LiteralPath $PhpPath).Path)
    } else {
        $candidatePaths.Add((Join-Path $projectRoot 'tools\php85\php.exe'))

        $userProfile = [Environment]::GetFolderPath('UserProfile')
        if ($userProfile) {
            $candidatePaths.Add((Join-Path $userProfile '.config\herd\bin\php85\php.exe'))
            $candidatePaths.Add((Join-Path $userProfile '.config\herd\bin\php84\php.exe'))
        }

        $candidatePaths.Add('C:\xampp\php\php.exe')

        $pathPhp = Get-Command php.exe -CommandType Application -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($pathPhp) {
            $candidatePaths.Add($pathPhp.Source)
        }
    }

    $checkedPaths = [System.Collections.Generic.HashSet[string]]::new(
        [System.StringComparer]::OrdinalIgnoreCase
    )
    $rejections = [System.Collections.Generic.List[string]]::new()

    foreach ($candidatePath in $candidatePaths) {
        if (-not $candidatePath -or -not $checkedPaths.Add($candidatePath)) {
            continue
        }

        if (-not (Test-Path -LiteralPath $candidatePath -PathType Leaf)) {
            $rejections.Add("$candidatePath (not found)")
            continue
        }

        try {
            $versionIdText = [string](& $candidatePath -r 'echo PHP_VERSION_ID;' 2>$null)
            $versionCommandExitCode = $LASTEXITCODE
            $parsedVersionId = 0

            if ($versionCommandExitCode -ne 0 -or
                -not [int]::TryParse($versionIdText.Trim(), [ref] $parsedVersionId)) {
                $rejections.Add("$candidatePath (could not determine its PHP version)")
                continue
            }

            if ($parsedVersionId -lt $minimumVersionId) {
                $versionText = [string](& $candidatePath -r 'echo PHP_VERSION;' 2>$null)
                $rejections.Add("$candidatePath (PHP $($versionText.Trim()); 8.4.1+ required)")
                continue
            }

            $resolvedPath = (Resolve-Path -LiteralPath $candidatePath).Path
            $resolvedVersion = [string](& $resolvedPath -r 'echo PHP_VERSION;' 2>$null)

            return [pscustomobject]@{
                Path      = $resolvedPath
                Version   = $resolvedVersion.Trim()
                VersionId = $parsedVersionId
            }
        } catch {
            $rejections.Add("$candidatePath ($($_.Exception.Message))")
        }
    }

    $details = if ($rejections.Count -gt 0) {
        " Checked: $($rejections -join '; ')."
    } else {
        ''
    }

    throw "PHP 8.4.1 or newer was not found. Pass -PhpPath with a compatible php.exe.$details"
}

function Remove-PairfectStalePidFile {
    param(
        [Parameter(Mandatory = $true)]
        [string] $PidFile
    )

    if (Test-Path -LiteralPath $PidFile) {
        Remove-Item -LiteralPath $PidFile -Force
    }
}

function Get-PairfectServiceState {
    param(
        [Parameter(Mandatory = $true)]
        [string] $ServiceName,

        [Parameter(Mandatory = $true)]
        [string] $PidFile,

        [Parameter(Mandatory = $true)]
        [string] $ArtisanPath,

        [Parameter(Mandatory = $true)]
        [string] $CommandMarker,

        [switch] $CleanStalePid
    )

    if (-not (Test-Path -LiteralPath $PidFile -PathType Leaf)) {
        return [pscustomobject]@{
            Service   = $ServiceName
            Status    = 'Stopped'
            ProcessId = $null
            Detail    = 'No PID file.'
        }
    }

    $recordedProcessId = 0
    try {
        $pidContents = (Get-Content -LiteralPath $PidFile -Raw).Trim()
    } catch {
        $pidContents = ''
    }

    if (-not [int]::TryParse($pidContents, [ref] $recordedProcessId) -or
        $recordedProcessId -le 0) {
        if ($CleanStalePid) {
            Remove-PairfectStalePidFile -PidFile $PidFile
        }

        return [pscustomobject]@{
            Service   = $ServiceName
            Status    = 'Stale'
            ProcessId = $null
            Detail    = 'The PID file was invalid and was removed when cleanup was requested.'
        }
    }

    $serviceProcess = Get-CimInstance Win32_Process `
        -Filter "ProcessId = $recordedProcessId" `
        -ErrorAction SilentlyContinue

    if (-not $serviceProcess) {
        if ($CleanStalePid) {
            Remove-PairfectStalePidFile -PidFile $PidFile
        }

        return [pscustomobject]@{
            Service   = $ServiceName
            Status    = 'Stale'
            ProcessId = $recordedProcessId
            Detail    = 'The recorded process no longer exists.'
        }
    }

    $commandLine = [string] $serviceProcess.CommandLine
    $hasExpectedProject = $commandLine.IndexOf(
        $ArtisanPath,
        [System.StringComparison]::OrdinalIgnoreCase
    ) -ge 0
    $markerPattern = '(?i)(?:^|\s)' + [regex]::Escape($CommandMarker) + '(?:\s|$)'
    $hasExpectedCommand = [regex]::IsMatch($commandLine, $markerPattern)

    if (-not $hasExpectedProject -or -not $hasExpectedCommand) {
        if ($CleanStalePid) {
            Remove-PairfectStalePidFile -PidFile $PidFile
        }

        return [pscustomobject]@{
            Service   = $ServiceName
            Status    = 'Stale'
            ProcessId = $recordedProcessId
            Detail    = 'The PID belongs to another process; that process was not touched.'
        }
    }

    return [pscustomobject]@{
        Service   = $ServiceName
        Status    = 'Running'
        ProcessId = $recordedProcessId
        Detail    = $commandLine
    }
}

function Start-PairfectArtisanService {
    param(
        [Parameter(Mandatory = $true)]
        [string] $ServiceName,

        [Parameter(Mandatory = $true)]
        [string] $PidFile,

        [Parameter(Mandatory = $true)]
        [string] $OutputLog,

        [Parameter(Mandatory = $true)]
        [string] $ErrorLog,

        [Parameter(Mandatory = $true)]
        [string] $CommandMarker,

        [Parameter(Mandatory = $true)]
        [string[]] $ArtisanArguments,

        [string] $PhpPath = ''
    )

    $projectRoot = Get-PairfectProjectRoot
    $artisanPath = Join-Path $projectRoot 'artisan'
    $currentState = Get-PairfectServiceState `
        -ServiceName $ServiceName `
        -PidFile $PidFile `
        -ArtisanPath $artisanPath `
        -CommandMarker $CommandMarker `
        -CleanStalePid

    if ($currentState.Status -eq 'Running') {
        Write-Output "$ServiceName is already running (PID $($currentState.ProcessId))."
        return
    }

    $runtime = Resolve-PairfectPhp -PhpPath $PhpPath
    New-Item -ItemType Directory -Path (Split-Path -Parent $PidFile) -Force | Out-Null
    New-Item -ItemType Directory -Path (Split-Path -Parent $OutputLog) -Force | Out-Null

    # Quoting the absolute Artisan path keeps service identity checks reliable,
    # including when the project directory contains spaces.
    $processArguments = @("`"$artisanPath`"") + $ArtisanArguments
    $serviceProcess = Start-Process `
        -FilePath $runtime.Path `
        -ArgumentList $processArguments `
        -WorkingDirectory $projectRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $OutputLog `
        -RedirectStandardError $ErrorLog `
        -PassThru

    if ($serviceProcess.WaitForExit(750)) {
        throw "$ServiceName exited immediately with code $($serviceProcess.ExitCode). Review $ErrorLog."
    }

    Set-Content -LiteralPath $PidFile -Value $serviceProcess.Id -NoNewline
    Write-Output "$ServiceName started in the background (PID $($serviceProcess.Id), PHP $($runtime.Version))."
    Write-Output "Output: $OutputLog"
    Write-Output "Errors: $ErrorLog"
}

function Stop-PairfectArtisanService {
    param(
        [Parameter(Mandatory = $true)]
        [string] $ServiceName,

        [Parameter(Mandatory = $true)]
        [string] $PidFile,

        [Parameter(Mandatory = $true)]
        [string] $ArtisanPath,

        [Parameter(Mandatory = $true)]
        [string] $CommandMarker
    )

    $currentState = Get-PairfectServiceState `
        -ServiceName $ServiceName `
        -PidFile $PidFile `
        -ArtisanPath $ArtisanPath `
        -CommandMarker $CommandMarker `
        -CleanStalePid

    if ($currentState.Status -ne 'Running') {
        if ($currentState.Status -eq 'Stale') {
            Write-Output "$ServiceName was not running; its stale PID file was cleaned up."
        } else {
            Write-Output "$ServiceName is not running."
        }

        return
    }

    $nativeProcess = Get-Process -Id $currentState.ProcessId -ErrorAction Stop
    Stop-Process -InputObject $nativeProcess -ErrorAction Stop
    if (-not $nativeProcess.WaitForExit(10000)) {
        throw "$ServiceName did not stop within 10 seconds; its PID file was retained."
    }

    Remove-PairfectStalePidFile -PidFile $PidFile
    Write-Output "$ServiceName stopped (PID $($currentState.ProcessId))."
}
