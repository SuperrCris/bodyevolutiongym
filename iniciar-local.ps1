$ErrorActionPreference = 'Stop'

$identidad = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identidad)

if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    $scriptPath = $PSCommandPath
    $arguments = '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}"' -f $scriptPath

    try {
        Start-Process -FilePath (Join-Path $PSHOME 'powershell.exe') -Verb RunAs -ArgumentList $arguments | Out-Null
    } catch {
        throw "Se necesitan permisos de administrador para reiniciar SQL Server. No se inició el stack: $($_.Exception.Message)"
    }

    return
}

$root = $PSScriptRoot
$artisan = Join-Path $root 'artisan'
$php = (Get-Command php -ErrorAction Stop).Source
$sqlServerService = 'MSSQL$SQLEXPRESS'
$bridgeScript = Join-Path $root 'biometria-java\ejecutar.ps1'
$compileScript = Join-Path $root 'biometria-java\compilar.ps1'
$bridgeWorkingDirectory = Join-Path $root 'biometria-java'
$bridgeHealthUrl = 'http://127.0.0.1:8765/salud'
$appUrl = 'http://127.0.0.1:8000'

function Stop-ProjectProcesses {
    param (
        [Parameter(Mandatory = $true)]
        [string] $ArtisanPath
    )

    $processes = @(Get-CimInstance Win32_Process)
    $ownedProcesses = @{}
    $artisanPattern = [regex]::Escape($ArtisanPath)

    foreach ($process in $processes) {
        if ($process.Name -match '^php(?:-cgi)?\.exe$' `
            -and $process.CommandLine -match $artisanPattern `
            -and $process.CommandLine -match '(?i)\bserve\b') {
            $ownedProcesses[[string] $process.ProcessId] = 0
        }
    }

    $foundDescendant = $true

    while ($foundDescendant) {
        $foundDescendant = $false

        foreach ($process in $processes) {
            $parentId = [string] $process.ParentProcessId
            $processId = [string] $process.ProcessId

            if ($ownedProcesses.ContainsKey($parentId) -and -not $ownedProcesses.ContainsKey($processId)) {
                $ownedProcesses[$processId] = $ownedProcesses[$parentId] + 1
                $foundDescendant = $true
            }
        }
    }

    foreach ($process in $processes | Where-Object { $ownedProcesses.ContainsKey([string] $_.ProcessId) } | Sort-Object { $ownedProcesses[[string] $_.ProcessId] } -Descending) {
        Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    }

    if ($ownedProcesses.Count -gt 0) {
        Write-Host "Se reinició el servidor Laravel anterior del proyecto ($($ownedProcesses.Count) procesos)."
    }
}

function Stop-BiometricBridge {
    $bridgeProcesses = @(Get-CimInstance Win32_Process | Where-Object {
        $_.Name -match '^javaw?\.exe$' -and $_.CommandLine -match 'BodyEvolutionBiometria'
    })

    foreach ($process in $bridgeProcesses) {
        Stop-Process -Id $process.ProcessId -Force -ErrorAction Stop
    }

    if ($bridgeProcesses.Count -gt 0) {
        Write-Host 'Se detuvo el puente Java anterior.'
    }
}

function Test-DatabaseConnection {
    param (
        [Parameter(Mandatory = $true)]
        [string] $PhpPath,
        [Parameter(Mandatory = $true)]
        [string] $ArtisanPath
    )

    $ultimoResultado = ''

    for ($intento = 1; $intento -le 15; $intento++) {
        $salida = & $PhpPath $ArtisanPath migrate:status --no-interaction 2>&1
        $codigoSalida = $LASTEXITCODE

        if ($codigoSalida -eq 0) {
            Write-Host 'La conexión con la base de datos está disponible.'
            return
        }

        $ultimoResultado = $salida | Out-String
        Write-Host "Esperando la base de datos ($intento/15)..."
        Start-Sleep -Seconds 2
    }

    throw "No se pudo conectar con SQL Server tras 30 segundos. Último resultado: $ultimoResultado"
}

Set-Location $root

if (-not (Test-Path $artisan -PathType Leaf)) {
    throw "No se encontró el proyecto Laravel en $root."
}

foreach ($requiredFile in @($bridgeScript, $compileScript)) {
    if (-not (Test-Path $requiredFile -PathType Leaf)) {
        throw "No se encontró un archivo requerido: $requiredFile"
    }
}

Write-Host 'Deteniendo el servidor local anterior del proyecto, si existe...'
Stop-ProjectProcesses -ArtisanPath $artisan

$service = Get-Service -Name $sqlServerService -ErrorAction SilentlyContinue

if ($null -eq $service) {
    Write-Host "No se encontró $sqlServerService. Se continuará con la conexión configurada en .env."
} else {
    Write-Host "Reiniciando el servicio $sqlServerService..."

    if ($service.Status -eq [System.ServiceProcess.ServiceControllerStatus]::Running) {
        Restart-Service -Name $sqlServerService -ErrorAction Stop
    } else {
        Start-Service -Name $sqlServerService -ErrorAction Stop
    }

    $service = Get-Service -Name $sqlServerService -ErrorAction Stop
    $service.WaitForStatus(
        [System.ServiceProcess.ServiceControllerStatus]::Running,
        [TimeSpan]::FromSeconds(60)
    )
    Write-Host 'SQL Server Express está en ejecución.'
}

Write-Host 'Compilando el puente Java...'
& $compileScript

Write-Host 'Reiniciando el puente Java...'
Stop-BiometricBridge
$bridgeWindow = Start-Process `
    -FilePath (Join-Path $PSHOME 'powershell.exe') `
    -ArgumentList @('-NoExit', '-NoProfile', '-WindowStyle', 'Hidden', '-ExecutionPolicy', 'Bypass', '-File', "`"$bridgeScript`"") `
    -WorkingDirectory $bridgeWorkingDirectory `
    -PassThru

$puenteDisponible = $false
$ultimoErrorPuente = ''

for ($intento = 1; $intento -le 30; $intento++) {
    try {
        $salud = Invoke-RestMethod -Uri $bridgeHealthUrl -TimeoutSec 2
        $puenteDisponible = $true
        break
    } catch {
        $ultimoErrorPuente = $_.Exception.Message
        Start-Sleep -Seconds 1
    }
}

if (-not $puenteDisponible) {
    throw "El puente Java no respondió en $bridgeHealthUrl. Revise la ventana Java (PID $($bridgeWindow.Id)). Último error: $ultimoErrorPuente"
}

Write-Host "Puente Java disponible en $bridgeHealthUrl."

Write-Host 'Verificando conexión a la base de datos...'
Test-DatabaseConnection -PhpPath $php -ArtisanPath $artisan

Write-Host 'Limpiando cachés de Laravel...'
$salidaLimpieza = & $php $artisan optimize:clear --no-interaction 2>&1

if ($LASTEXITCODE -ne 0) {
    throw "No se pudieron limpiar las cachés de Laravel: $($salidaLimpieza | Out-String)"
}

Write-Host "Stack local preparado. Abre $appUrl"
Write-Host 'Laravel se ejecutará en primer plano. Presiona Ctrl+C para detener la aplicación.'
& $php $artisan serve --host=127.0.0.1 --port=8000 --tries=1

if ($LASTEXITCODE -ne 0) {
    throw "Laravel terminó con el código de salida $LASTEXITCODE."
}
