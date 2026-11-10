param (
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $DeployPath,

    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $PhpExecutable
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

if ([string]::IsNullOrWhiteSpace($env:GITHUB_WORKSPACE)) {
    throw 'GITHUB_WORKSPACE is not set. Run this script from a GitHub Actions runner.'
}

$sourcePath = [System.IO.Path]::GetFullPath($env:GITHUB_WORKSPACE)
$DeployPath = [System.IO.Path]::GetFullPath($DeployPath)
$PhpExecutable = [System.IO.Path]::GetFullPath($PhpExecutable)

if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
    throw "Source directory does not exist: $sourcePath"
}

if (-not (Test-Path -LiteralPath $PhpExecutable -PathType Leaf)) {
    throw "PHP executable does not exist: $PhpExecutable"
}

if (-not (Test-Path -LiteralPath $DeployPath -PathType Container)) {
    throw "Production directory does not exist: $DeployPath"
}

if ($sourcePath.Equals($DeployPath, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'The source and production directories must be different.'
}

$sourcePrefix = $sourcePath.TrimEnd('\') + '\'
$deployPrefix = $DeployPath.TrimEnd('\') + '\'

if (
    $sourcePath.StartsWith($deployPrefix, [System.StringComparison]::OrdinalIgnoreCase) -or
    $DeployPath.StartsWith($sourcePrefix, [System.StringComparison]::OrdinalIgnoreCase)
) {
    throw 'The source and production directories must not contain one another.'
}

foreach ($requiredPath in @('.env', 'artisan', 'storage')) {
    if (-not (Test-Path -LiteralPath (Join-Path $DeployPath $requiredPath))) {
        throw "The production directory must already contain '$requiredPath'."
    }
}

if (-not (Test-Path -LiteralPath (Join-Path $sourcePath 'vendor\autoload.php'))) {
    throw 'Production Composer dependencies are missing from the checked-out source.'
}

if (-not (Test-Path -LiteralPath (Join-Path $sourcePath 'public\build\manifest.json'))) {
    throw 'The frontend build is missing from the checked-out source.'
}

function Invoke-CheckedCommand {
    param (
        [Parameter(Mandatory = $true)]
        [string] $FilePath,
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    & $FilePath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "$FilePath failed with exit code $LASTEXITCODE."
    }
}

Push-Location -LiteralPath $DeployPath

try {
    $maintenanceEnabled = $false

    try {
        Invoke-CheckedCommand -FilePath $PhpExecutable -Arguments @('artisan', 'down')
        $maintenanceEnabled = $true

        $excludedDirectories = @(
            '.git',
            '.github',
            '.vscode',
            'node_modules',
            'public\storage',
            'storage',
            'tests'
        )
        $robocopyArguments = @(
            $sourcePath,
            $DeployPath,
            '/MIR',
            '/R:2',
            '/W:5',
            '/XD'
        ) + $excludedDirectories + @('/XF', '.env', 'database.sqlite')

        & robocopy.exe @robocopyArguments

        if ($LASTEXITCODE -ge 8) {
            throw "robocopy.exe failed with exit code $LASTEXITCODE."
        }

        Invoke-CheckedCommand -FilePath $PhpExecutable -Arguments @('artisan', 'migrate', '--force')
        Invoke-CheckedCommand -FilePath $PhpExecutable -Arguments @('artisan', 'optimize')
    }
    finally {
        if ($maintenanceEnabled) {
            Invoke-CheckedCommand -FilePath $PhpExecutable -Arguments @('artisan', 'up')
        }
    }
}
finally {
    Pop-Location
}
