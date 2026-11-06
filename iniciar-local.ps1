param(
    [switch] $ConfigurarInicio,
    [switch] $EliminarInicio,
    [switch] $ArranqueWindows
)

$ErrorActionPreference = 'Stop'

$identidad = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identidad)

if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    $scriptPath = $PSCommandPath
    $switches = @()

    if ($ConfigurarInicio) {
        $switches += '-ConfigurarInicio'
    }

    if ($EliminarInicio) {
        $switches += '-EliminarInicio'
    }

    if ($ArranqueWindows) {
        $switches += '-ArranqueWindows'
    }

    $arguments = '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" {1}' -f $scriptPath, ($switches -join ' ')

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
$routerScript = Join-Path $root 'server-router.php'
$publicDirectory = Join-Path $root 'public'
$bridgeScript = Join-Path $root 'biometria-java\ejecutar.ps1'
$compileScript = Join-Path $root 'biometria-java\compilar.ps1'
$bridgeWorkingDirectory = Join-Path $root 'biometria-java'
$bridgeHealthUrl = 'http://127.0.0.1:8765/salud'
$appUrl = 'http://127.0.0.1:8000'
$chromeUrl = $appUrl
$startupTaskName = 'BodyEvolutionGym - Iniciar servicios'

function Set-WindowsStartupTask {
    param (
        [Parameter(Mandatory = $true)]
        [string] $ScriptPath,
        [Parameter(Mandatory = $true)]
        [string] $TaskName
    )

    $powershellPath = Join-Path $PSHOME 'powershell.exe'
    $action = New-ScheduledTaskAction `
        -Execute $powershellPath `
        -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -ArranqueWindows' -f $ScriptPath) `
        -WorkingDirectory $PSScriptRoot
    $trigger = New-ScheduledTaskTrigger -AtLogOn -User $identidad.Name
    $taskPrincipal = New-ScheduledTaskPrincipal `
        -UserId $identidad.Name `
        -LogonType Interactive `
        -RunLevel Highest
    $settings = New-ScheduledTaskSettingsSet `
        -StartWhenAvailable `
        -MultipleInstances IgnoreNew `
        -ExecutionTimeLimit ([TimeSpan]::Zero)

    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $action `
        -Trigger $trigger `
        -Principal $taskPrincipal `
        -Settings $settings `
        -Description 'Inicia SQL Server, el puente biométrico y Laravel para Body Evolution Gym al iniciar sesión.' `
        -Force | Out-Null
}

function Remove-WindowsStartupTask {
    param (
        [Parameter(Mandatory = $true)]
        [string] $TaskName
    )

    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue

    if ($null -eq $task) {
        Write-Host "No existe la tarea de inicio '$TaskName'."
        return
    }

    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Se eliminó la tarea de inicio '$TaskName'."
}

if ($ConfigurarInicio -and $EliminarInicio) {
    throw 'No se pueden usar -ConfigurarInicio y -EliminarInicio al mismo tiempo.'
}

if ($ConfigurarInicio) {
    Set-WindowsStartupTask -ScriptPath $PSCommandPath -TaskName $startupTaskName
    Write-Host "El stack se iniciará automáticamente al iniciar sesión en Windows."
    return
}

if ($EliminarInicio) {
    Remove-WindowsStartupTask -TaskName $startupTaskName
    return
}

function Stop-ProjectProcesses {
    param (
        [Parameter(Mandatory = $true)]
        [string] $ArtisanPath
    )

    $processes = @(Get-CimInstance Win32_Process)
    $ownedProcesses = @{}
    $artisanPattern = [regex]::Escape($ArtisanPath)
    $routerPattern = [regex]::Escape((Join-Path (Split-Path -Parent $ArtisanPath) 'server-router.php'))

    foreach ($process in $processes) {
        if ($process.Name -match '^php(?:-cgi)?\.exe$' `
            -and (
                ($process.CommandLine -match $artisanPattern -and $process.CommandLine -match '(?i)\bserve\b') `
                -or ($process.CommandLine -match '(?i)\s-S\s+127\.0\.0\.1:8000' -and $process.CommandLine -match $routerPattern)
            )) {
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

function Open-ApplicationInChrome {
    param (
        [Parameter(Mandatory = $true)]
        [string] $Url
    )

    $chromeCommand = Get-Command chrome.exe -ErrorAction SilentlyContinue
    $chromePaths = @(
        $(if ($null -ne $chromeCommand) { $chromeCommand.Source }),
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe')
    ) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) -and (Test-Path $_ -PathType Leaf) } | Select-Object -Unique
    $chromePaths = @($chromePaths)

    if ($chromePaths.Count -eq 0) {
        Write-Warning 'No se encontró Google Chrome. Laravel continuará ejecutándose sin abrir el navegador.'
        return
    }

    Start-Process -FilePath $chromePaths[0] -ArgumentList @('--new-window', $Url) | Out-Null
    Write-Host "Chrome abrió $Url."
}

Set-Location $root

if (-not (Test-Path $artisan -PathType Leaf)) {
    throw "No se encontró el proyecto Laravel en $root."
}

foreach ($requiredFile in @($bridgeScript, $compileScript, $routerScript)) {
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

$reiniciosLaravel = 0
$navegadorAbierto = $false

while ($true) {
    $laravelProcess = Start-Process `
        -FilePath $php `
        -ArgumentList @('-S', '127.0.0.1:8000', '-t', $publicDirectory, $routerScript) `
        -WorkingDirectory $root `
        -WindowStyle Hidden `
        -PassThru
    $aplicacionDisponible = $false

    for ($intento = 1; $intento -le 30; $intento++) {
        if ($laravelProcess.HasExited) {
            break
        }

        try {
            Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 2 | Out-Null
            $aplicacionDisponible = $true
            break
        } catch {
            Start-Sleep -Seconds 1
        }
    }

    if (-not $aplicacionDisponible) {
        if (-not $laravelProcess.HasExited) {
            Stop-Process -Id $laravelProcess.Id -Force
        }

        throw "Laravel no respondió en $appUrl después de 30 segundos."
    }

    if (-not $navegadorAbierto) {
        Open-ApplicationInChrome -Url $chromeUrl
        $navegadorAbierto = $true
    }

    Wait-Process -Id $laravelProcess.Id
    $laravelProcess.Refresh()
    $codigoSalidaLaravel = $laravelProcess.ExitCode

    if ($codigoSalidaLaravel -eq 0) {
        Write-Host 'Laravel se detuvo correctamente.'
        break
    }

    $reiniciosLaravel++
    Write-Warning "Laravel terminó inesperadamente con el código $codigoSalidaLaravel. Reinicio ${reiniciosLaravel} en 5 segundos."
    Start-Sleep -Seconds 5
}
