# Puente local DigitalPersona

Este proceso escucha únicamente en `127.0.0.1:8765` y usa los JAR oficiales del SDK DigitalPersona instalados en Windows. No publica muestras ni plantillas hacia Internet.

## Compilación y ejecución

Desde PowerShell:

```powershell
.\biometria-java\compilar.ps1
.\biometria-java\ejecutar.ps1
```

El lector debe estar conectado y sus controladores DigitalPersona instalados. Laravel utiliza `BIOMETRIA_URL=http://127.0.0.1:8765`.
