$ErrorActionPreference = 'Stop'

$java = 'C:\Program Files\Java\jdk-21\bin\java.exe'
$sdk = 'C:\Program Files\DigitalPersona\Bin\Java'
$build = Join-Path $PSScriptRoot 'build'
$classpath = "$build;$sdk\dpotapi.jar;$sdk\dpfpenrollment.jar;$sdk\dpfpverification.jar;$sdk\dpotjni.jar"

& $java '-cp' $classpath 'BodyEvolutionBiometria'
