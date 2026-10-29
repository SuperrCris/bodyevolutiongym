$ErrorActionPreference = 'Stop'

$java = @(
    $env:JAVA_HOME,
    (Join-Path $env:USERPROFILE '.local\java\jdk-21.0.12.1+1'),
    'C:\Program Files\Java\jdk-21'
) | Where-Object { $_ -and (Test-Path (Join-Path $_ 'bin\javac.exe')) } | Select-Object -First 1

if (-not $java) {
    throw 'No se encontró un JDK. Instale JDK 21 o defina JAVA_HOME.'
}

$java = Join-Path $java 'bin'
$sdk = 'C:\Program Files\DigitalPersona\Bin\Java'
$build = Join-Path $PSScriptRoot 'build'

New-Item -ItemType Directory -Force -Path $build | Out-Null
& (Join-Path $java 'javac.exe') `
    -encoding UTF-8 `
    -cp (Join-Path $sdk 'dpotapi.jar') `
    -d $build `
    (Join-Path $PSScriptRoot 'src\BodyEvolutionBiometria.java')

if ($LASTEXITCODE -ne 0) {
    throw "La compilación del puente Java falló con el código de salida $LASTEXITCODE."
}

Write-Host "Puente compilado en $build"
