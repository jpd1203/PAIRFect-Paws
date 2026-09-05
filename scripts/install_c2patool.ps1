[CmdletBinding()]
param(
    [switch] $Force
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$C2paToolVersion = '0.27.15'
$ArchiveName = "c2patool-v$C2paToolVersion-x86_64-pc-windows-msvc.zip"
$ArchiveUrl = "https://github.com/contentauth/c2pa-rs/releases/download/c2patool-v$C2paToolVersion/$ArchiveName"
$ExpectedArchiveSha256 = '7e931f3d7dce7ffed47e33e46a01b1d7e453059b639f676bf7efb44805610d0a'
$ExpectedExecutableSha256 = '4fc8581b51a5539f002edeb4a5c2300615ddb3fab5b05ee5bff7ee24ffc08c91'
$TrustListUrl = 'https://raw.githubusercontent.com/c2pa-org/conformance-public/refs/heads/main/trust-list/C2PA-TRUST-LIST.pem'

function Assert-FileHash {
    param(
        [Parameter(Mandatory)]
        [string] $LiteralPath,

        [Parameter(Mandatory)]
        [string] $ExpectedSha256
    )

    $actualSha256 = (Get-FileHash -LiteralPath $LiteralPath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualSha256 -ne $ExpectedSha256.ToLowerInvariant()) {
        throw "SHA-256 verification failed for '$LiteralPath'. Expected $ExpectedSha256; received $actualSha256."
    }
}

function Assert-CertificateAuthorityPemBundle {
    param(
        [Parameter(Mandatory)]
        [string] $LiteralPath
    )

    $pem = Get-Content -LiteralPath $LiteralPath -Raw
    $matches = [regex]::Matches(
        $pem,
        '(?ms)-----BEGIN CERTIFICATE-----\s*(?<body>[A-Za-z0-9+/=\r\n]+?)\s*-----END CERTIFICATE-----'
    )

    if ($matches.Count -lt 1) {
        throw "The downloaded C2PA trust list does not contain any PEM certificates."
    }

    foreach ($match in $matches) {
        $certificate = $null

        try {
            $base64 = $match.Groups['body'].Value -replace '\s', ''
            $certificateBytes = [Convert]::FromBase64String($base64)
            $certificate = [Security.Cryptography.X509Certificates.X509Certificate2]::new($certificateBytes)
            $extension = $certificate.Extensions |
                Where-Object { $_.Oid.Value -eq '2.5.29.19' } |
                Select-Object -First 1

            if ($null -eq $extension) {
                throw "A certificate in the C2PA trust list has no Basic Constraints extension."
            }

            $basicConstraints = [Security.Cryptography.X509Certificates.X509BasicConstraintsExtension]::new(
                $extension,
                $extension.Critical
            )

            if (-not $basicConstraints.CertificateAuthority) {
                throw "A certificate in the C2PA trust list is not a certificate authority."
            }
        }
        catch {
            throw "The downloaded C2PA trust list is not a valid CA PEM bundle: $($_.Exception.Message)"
        }
        finally {
            if ($null -ne $certificate) {
                $certificate.Dispose()
            }
        }
    }

    return $matches.Count
}

function Get-C2paToolVersionOutput {
    param(
        [Parameter(Mandatory)]
        [string] $LiteralPath
    )

    $versionOutput = (& $LiteralPath -V 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw "The c2patool executable failed its version check."
    }

    return $versionOutput
}

$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'artisan') -PathType Leaf)) {
    throw "This script must remain under the PAIRfect Paws project's scripts directory."
}

$destinationExecutable = Join-Path $projectRoot 'tools\c2patool\bin\c2patool\c2patool.exe'
$trustListDestination = Join-Path $projectRoot 'storage\app\c2pa\c2pa-trust-list.pem'
$installBinary = $true

if (Test-Path -LiteralPath $destinationExecutable -PathType Leaf) {
    $installedHash = (Get-FileHash -LiteralPath $destinationExecutable -Algorithm SHA256).Hash.ToLowerInvariant()
    $installedVersion = Get-C2paToolVersionOutput -LiteralPath $destinationExecutable

    if ($installedHash -eq $ExpectedExecutableSha256 -and $installedVersion -eq "c2patool $C2paToolVersion") {
        Write-Host "c2patool $C2paToolVersion is already installed and checksum-verified."
        $installBinary = $false
    }
    elseif (-not $Force) {
        throw "A different c2patool executable already exists. Review it, then rerun with -Force to replace only that executable."
    }
}

$systemTempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$temporaryDirectory = Join-Path $systemTempRoot ("pairfect-c2patool-install-" + [guid]::NewGuid().ToString('N'))
$resolvedTemporaryDirectory = [IO.Path]::GetFullPath($temporaryDirectory)
$expectedTempPrefix = $systemTempRoot.TrimEnd([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar

if (-not $resolvedTemporaryDirectory.StartsWith($expectedTempPrefix, [StringComparison]::OrdinalIgnoreCase) -or
    -not ([IO.Path]::GetFileName($resolvedTemporaryDirectory)).StartsWith('pairfect-c2patool-install-', [StringComparison]::Ordinal)) {
    throw "Refusing to use an unexpected temporary directory."
}

New-Item -ItemType Directory -Path $resolvedTemporaryDirectory | Out-Null

try {
    if ([Net.ServicePointManager]::SecurityProtocol -notlike '*Tls12*') {
        [Net.ServicePointManager]::SecurityProtocol =
            [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
    }

    if ($installBinary) {
        $archivePath = Join-Path $resolvedTemporaryDirectory $ArchiveName
        $extractPath = Join-Path $resolvedTemporaryDirectory 'archive'

        Write-Host "Downloading official c2patool $C2paToolVersion release..."
        Invoke-WebRequest -Uri $ArchiveUrl -OutFile $archivePath -UseBasicParsing
        Assert-FileHash -LiteralPath $archivePath -ExpectedSha256 $ExpectedArchiveSha256

        Expand-Archive -LiteralPath $archivePath -DestinationPath $extractPath
        $extractedExecutable = Join-Path $extractPath 'c2patool\c2patool.exe'

        if (-not (Test-Path -LiteralPath $extractedExecutable -PathType Leaf)) {
            throw "The verified archive did not contain the expected c2patool executable."
        }

        Assert-FileHash -LiteralPath $extractedExecutable -ExpectedSha256 $ExpectedExecutableSha256
        $extractedVersion = Get-C2paToolVersionOutput -LiteralPath $extractedExecutable
        if ($extractedVersion -ne "c2patool $C2paToolVersion") {
            throw "The extracted executable reported '$extractedVersion' instead of c2patool $C2paToolVersion."
        }

        $destinationDirectory = Split-Path -Parent $destinationExecutable
        New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
        Copy-Item -LiteralPath $extractedExecutable -Destination $destinationExecutable -Force:$Force
        Assert-FileHash -LiteralPath $destinationExecutable -ExpectedSha256 $ExpectedExecutableSha256

        Write-Host "Installed checksum-verified c2patool at $destinationExecutable"
    }

    $downloadedTrustList = Join-Path $resolvedTemporaryDirectory 'C2PA-TRUST-LIST.pem'
    Write-Host 'Downloading the current official C2PA trust list...'
    Invoke-WebRequest -Uri $TrustListUrl -OutFile $downloadedTrustList -UseBasicParsing
    $certificateCount = Assert-CertificateAuthorityPemBundle -LiteralPath $downloadedTrustList

    $trustListDirectory = Split-Path -Parent $trustListDestination
    New-Item -ItemType Directory -Path $trustListDirectory -Force | Out-Null
    Copy-Item -LiteralPath $downloadedTrustList -Destination $trustListDestination -Force

    $trustListSha256 = (Get-FileHash -LiteralPath $trustListDestination -Algorithm SHA256).Hash.ToLowerInvariant()
    Write-Host "Provisioned $certificateCount official CA certificates at $trustListDestination"
    Write-Host "Trust-list SHA-256 for this deployment: $trustListSha256"
    Write-Host 'Run `php artisan config:clear` (or rebuild the production config cache) after changing C2PA environment values.'
}
finally {
    if (Test-Path -LiteralPath $resolvedTemporaryDirectory) {
        Remove-Item -LiteralPath $resolvedTemporaryDirectory -Recurse -Force
    }
}
