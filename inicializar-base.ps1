param(
    [switch] $OmitirDependencias
)

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$envFile = Join-Path $root '.env'
$envExample = Join-Path $root '.env.example'
$artisan = Join-Path $root 'artisan'
$autoload = Join-Path $root 'vendor\autoload.php'

Set-Location $root

if (-not (Test-Path $artisan -PathType Leaf)) {
    throw "No se encontró el proyecto Laravel en $root."
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP no está disponible en PATH. Instala PHP 8.4 o superior y vuelve a ejecutar este script.'
}

if (-not (Test-Path $envFile -PathType Leaf)) {
    if (-not (Test-Path $envExample -PathType Leaf)) {
        throw "No se encontró $envExample para crear la configuración local."
    }

    Copy-Item $envExample $envFile
    Write-Host "Se creó $envFile a partir de .env.example."
}

if (-not $OmitirDependencias -and -not (Test-Path $autoload -PathType Leaf)) {
    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        throw 'Composer no está disponible en PATH. Instálalo y vuelve a ejecutar este script.'
    }

    Write-Host 'Instalando dependencias PHP...'
    composer install --no-interaction --prefer-dist

    if ($LASTEXITCODE -ne 0) {
        throw "Composer terminó con el código de salida $LASTEXITCODE."
    }
}

if (-not (Test-Path $autoload -PathType Leaf)) {
    throw 'No se encontró vendor\autoload.php. Ejecuta composer install o quita -OmitirDependencias.'
}

$appKey = Get-Content $envFile | Where-Object { $_ -match '^\s*APP_KEY\s*=' } | Select-Object -First 1
$appKeyValue = $null

if ($null -ne $appKey) {
    $appKeyValue = ($appKey -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

if ([string]::IsNullOrWhiteSpace($appKeyValue)) {
    Write-Host 'Generando APP_KEY...'
    php artisan key:generate --no-interaction

    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo generar APP_KEY. Código de salida: $LASTEXITCODE."
    }
}

Write-Host 'Limpiando la configuración de Laravel...'
php artisan config:clear --no-interaction

if ($LASTEXITCODE -ne 0) {
    throw "No se pudo limpiar la configuración. Código de salida: $LASTEXITCODE."
}

Write-Host 'Ejecutando migraciones y datos iniciales...'
php artisan migrate --seed --force --no-interaction

if ($LASTEXITCODE -ne 0) {
    throw "La inicialización de la base de datos falló. Código de salida: $LASTEXITCODE."
}

Write-Host ''
Write-Host 'Base de datos inicializada correctamente.'
Write-Host 'Usuario inicial: cristian'
Write-Host 'Contraseña inicial: password'
Write-Host 'Cambia la contraseña después del primer inicio de sesión.'
