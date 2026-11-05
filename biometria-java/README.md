# Puente local DigitalPersona

Este proceso escucha únicamente en `127.0.0.1:8765` y usa los JAR oficiales del SDK DigitalPersona instalados en Windows. No publica muestras ni plantillas hacia Internet.

## Compilación y ejecución

Desde PowerShell:

```powershell
.\biometria-java\compilar.ps1
.\biometria-java\ejecutar.ps1
```

El lector debe estar conectado y sus controladores DigitalPersona instalados. Laravel utiliza `BIOMETRIA_URL=http://127.0.0.1:8765`.

## Identificación 1:N

`POST /identificar` recibe la muestra y un lote de plantillas candidatas (`{"sample": "...", "candidatos": [{"id": "1", "template": "..."}], "todas": false}`), extrae las características de la muestra una sola vez y las compara con cada plantilla en memoria. Responde `{"coincidencias": ["1"], "evaluadas": N, "invalidas": 0}`. Con `"todas": true` evalúa todo el lote en vez de detenerse en la primera coincidencia. Laravel envía los lotes según `BIOMETRIA_LOTE_IDENTIFICACION` (250 por defecto). Recompile y reinicie el puente tras actualizar.

Laravel inicia el sondeo al cargar una página autenticada; la primera consulta a `/poll` activa la captura continua. La captura se pausa durante operaciones exclusivas y se reanuda al terminar.

## Impresión de recibos

El endpoint local `POST /imprimir` envía un único trabajo a la impresora predeterminada de Windows con una o dos copias completas del recibo, en páginas consecutivas; no crea un segundo pago ni requiere una segunda solicitud. El protocolo actual (`BEG-PRINT-V2`) indica la cantidad de copias; las solicitudes anteriores `BEG-PRINT-V1` siguen imprimiendo dos.

Para imprimir una línea en negritas, agrega `?n` en cualquier parte de esa línea del contenido del recibo. El puente elimina el marcador y aplica negritas a toda la línea.

Comprueba el estado del puente y la impresora predeterminada desde PowerShell:

```powershell
Invoke-RestMethod http://127.0.0.1:8765/salud
Invoke-RestMethod http://127.0.0.1:8765/impresora
```

La respuesta de impresión confirma la cantidad solicitada en `copies`. La salida física debe validarse con la ticketera conectada.
