$ErrorActionPreference = 'Stop'

$jdk = @(
    $env:JAVA_HOME,
    (Join-Path $env:USERPROFILE '.local\java\jdk-21.0.12.1+1'),
    'C:\Program Files\Java\jdk-21'
) | Where-Object { $_ -and (Test-Path (Join-Path $_ 'bin\java.exe')) } | Select-Object -First 1

if (-not $jdk) {
    throw 'No se encontró un JDK. Instale JDK 21 o defina JAVA_HOME.'
}

$java = Join-Path $jdk 'bin\java.exe'
$sdk = 'C:\Program Files\DigitalPersona\Bin\Java'
$build = Join-Path $PSScriptRoot 'build'
$classpath = "$build;$sdk\dpotapi.jar;$sdk\dpfpenrollment.jar;$sdk\dpfpverification.jar;$sdk\dpotjni.jar"

& $java '-cp' $classpath 'BodyEvolutionBiometria'
