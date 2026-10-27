$ErrorActionPreference = 'Stop'

$java = Join-Path $env:USERPROFILE '.local\java\jdk-21.0.12.1+1\bin\java.exe'
$sdk = 'C:\Program Files\DigitalPersona\Bin\Java'
$build = Join-Path $PSScriptRoot 'build'
$classpath = "$build;$sdk\dpotapi.jar;$sdk\dpfpenrollment.jar;$sdk\dpfpverification.jar;$sdk\dpotjni.jar"

& $java '-cp' $classpath 'BodyEvolutionBiometria'
