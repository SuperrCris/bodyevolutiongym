$ErrorActionPreference = 'Stop'

$java = Join-Path $env:USERPROFILE '.local\java\jdk-21.0.12.1+1\bin'
$sdk = 'C:\Program Files\DigitalPersona\Bin\Java'
$build = Join-Path $PSScriptRoot 'build'

New-Item -ItemType Directory -Force -Path $build | Out-Null
& (Join-Path $java 'javac.exe') `
    -encoding UTF-8 `
    -cp (Join-Path $sdk 'dpotapi.jar') `
    -d $build `
    (Join-Path $PSScriptRoot 'src\BodyEvolutionBiometria.java')

Write-Host "Puente compilado en $build"
