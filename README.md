# Body Evolution Gym

Aplicación web para la operación diaria de un gimnasio: clientes, membresías, pagos, ventas, egresos, reportes y control de acceso biométrico. Está construida con Laravel y ofrece un puente local Java para lectores DigitalPersona e impresión de recibos en Windows.

## Contenido

- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Inicio local en Windows](#inicio-local-en-windows)
- [Funciones y permisos](#funciones-y-permisos)
- [Arquitectura del proyecto](#arquitectura-del-proyecto)
- [Configuración](#configuración)
- [Pruebas](#pruebas)
- [Solución de problemas](#solución-de-problemas)

## Requisitos

- Windows para los scripts de inicio y la integración con dispositivos.
- PHP 8.4.1 o superior y Composer; mantén PHP actualizado a la última versión de seguridad de su rama.
- Node.js y npm para compilar los recursos de Vite.
- PostgreSQL, que es el motor configurado en `.env.example`. `config/database.php` también define conexiones para SQLite, MySQL, MariaDB y SQL Server; configura el motor y sus credenciales en `.env`.
- Para huellas e impresión: JDK 21, los controladores y el SDK Java de DigitalPersona instalados en `C:\Program Files\DigitalPersona\Bin\Java`, lector compatible y, para recibos, una impresora predeterminada configurada en Windows.

## Instalación

1. Clona el repositorio y abre PowerShell en su raíz.
2. Crea la base de datos configurada en `.env`. Para PostgreSQL se incluye [database/postgresql/create_database.sql](database/postgresql/create_database.sql); ejecútalo conectado a una base administrativa con permiso para crear bases de datos.
3. Revisa las variables `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env.example` y ajusta `.env` a tu entorno. Si `.env` aún no existe, el inicializador lo crea a partir de `.env.example`.
4. Inicializa Laravel y la base de datos:

   ```powershell
   .\inicializar-base.ps1
   ```

   El script instala dependencias PHP si falta `vendor`, genera `APP_KEY` si está vacía y ejecuta migraciones y seeders. No crea la base de datos ni elimina o reinicia tablas existentes. Usa `-OmitirDependencias` solo si las dependencias PHP ya están instaladas.

5. Instala y compila los recursos del frontend:

   ```powershell
   npm ci
   npm run build
   ```

El seeder crea el usuario inicial `cristian` con contraseña `password`, además del plan “Plan mensual”. Cambia la contraseña del usuario inicial tras el primer acceso y no compartas credenciales reales ni el archivo `.env`.

## Inicio local en Windows

Para iniciar el stack local integrado, ejecuta desde PowerShell:

```powershell
.\iniciar-local.ps1
```

El script solicita elevación de permisos, inicia o reinicia el servicio SQL Server Express `MSSQL$SQLEXPRESS` si está instalado, compila y levanta el puente biométrico, comprueba la conexión configurada en `.env` y sirve Laravel en `http://127.0.0.1:8000`. Si encuentra Google Chrome, abre la aplicación. Laravel se ejecuta en primer plano; usa `Ctrl+C` para detenerlo. Si el servidor termina con error, el script intenta iniciarlo de nuevo.

La conexión efectiva a la base de datos depende de `DB_CONNECTION` y las demás variables `DB_*` de `.env`; el servicio SQL Server Express que administra el script no cambia esa configuración.

Para iniciar el stack automáticamente al iniciar sesión en Windows, ejecuta PowerShell como administrador:

```powershell
.\iniciar-local.ps1 -ConfigurarInicio
```

Para quitar la tarea programada:

```powershell
.\iniciar-local.ps1 -EliminarInicio
```

El servidor web también puede ejecutarse por separado con `php artisan serve`. En ese modo, las funciones biométricas y de impresión requieren que el puente local esté iniciado. Consulta la guía de [biometria-java](biometria-java/README.md) para compilarlo, ejecutarlo y comprobar su estado.

## Funciones y permisos

Las rutas web están definidas en [routes/web.php](routes/web.php) y requieren autenticación, salvo el acceso y la autenticación biométrica desde la pantalla de inicio de sesión.

| Área | Funciones |
| --- | --- |
| Clientes | Alta, edición, consulta de documentos y resumen; renovación de membresías y recibos. |
| Finanzas | Registro de ventas y egresos; reportes con exportación CSV y Excel. |
| Membresías | Administración de tipos de plan y actualización de vigencias. |
| Empleados | Administración de empleados y enrolamiento biométrico. |
| Registros | Consulta de registros de acceso. |
| Ajustes | Preferencias de notificaciones y configuración del ticket. |
| Biometría | Inicio de sesión, enrolamiento y verificación de huellas mediante el puente local. |

El rol `recepcionista` tiene acceso a clientes, renovaciones, recibos, ventas, egresos y reportes. El rol `administrador` tiene acceso a administración de tipos de membresía, empleados, registros, vigencias y configuración del ticket. Los ajustes generales y las preferencias de notificación requieren autenticación. La API de [routes/api.php](routes/api.php) expone operaciones versionadas bajo `/api/v1` y también está protegida por autenticación y roles.

## Arquitectura del proyecto

- `app/Http/Controllers/`: controladores web y API.
- `app/Domain/`: servicios de dominio para ajustes, auditoría, biometría, finanzas, impresión y membresías.
- `app/Modelos/`: modelos Eloquent; el espacio de nombres `App\Models` se asigna a esta carpeta mediante Composer.
- `routes/`: rutas web, API y de consola.
- `resources/views/`: vistas Blade; `resources/css/` y `resources/js/` contienen los recursos compilados con Vite.
- `database/migrations/`, `database/seeders/` y `database/factories/`: esquema, datos iniciales y datos de prueba.
- `biometria-java/`: proceso local Java para el lector DigitalPersona y la impresión.
- `tests/Feature/` y `tests/Unit/`: pruebas Pest.

## Configuración

Además de las variables de aplicación y base de datos, el puente biométrico se configura en `.env`:

| Variable | Valor predeterminado | Uso |
| --- | --- | --- |
| `BIOMETRIA_URL` | `http://127.0.0.1:8765` | Dirección local del puente Java. |
| `BIOMETRIA_TERMINAL` | vacío | Identificador opcional de terminal. |
| `BIOMETRIA_CONNECT_TIMEOUT` | `2` | Tiempo límite de conexión en segundos. |
| `BIOMETRIA_TIMEOUT` | `45` | Tiempo límite general de las solicitudes biométricas. |
| `BIOMETRIA_PRINT_TIMEOUT` | `15` | Tiempo límite de impresión. |
| `BIOMETRIA_ENROLL_TIMEOUT` | `200` | Tiempo límite de enrolamiento. |
| `BIOMETRIA_LOTE_IDENTIFICACION` | `250` | Plantillas por lote durante la identificación. |

Los valores biométricos se definen en [config/biometria.php](config/biometria.php). No guardes secretos ni credenciales de producción en el repositorio.

## Pruebas

Ejecuta todas las pruebas con:

```powershell
php artisan test --compact
```

Para ejecutar un archivo específico, pásalo al comando, por ejemplo:

```powershell
php artisan test --compact tests/Feature/GestionClientesTest.php
```

## CI/CD

El workflow de GitHub Actions ejecuta las pruebas Pest y compila Vite en cada pull request hacia `main` y en cada push a `main`. Tras pasar esas comprobaciones, los pushes a `main` se despliegan automáticamente en el sistema Windows local mediante un runner self-hosted con la etiqueta adicional `production`.

Para habilitar el despliegue:

1. En GitHub, agrega un runner self-hosted Windows x64 al repositorio y asígnale la etiqueta `production`. Instálalo como servicio con la cuenta de sistema local.
2. En **Settings > Secrets and variables > Actions > Variables**, crea `PRODUCTION_PATH` con la ruta absoluta de la instalación existente de Laravel en ese equipo.
3. Confirma que el directorio de producción ya contiene `.env`, `artisan` y `storage`, y que la cuenta del runner puede escribir en él. El despliegue conserva `.env`, `storage`, `public/storage` y cualquier `database.sqlite` local; ejecuta las migraciones y optimiza Laravel. Si falla, el script intenta desactivar el modo de mantenimiento.
4. Protege la rama `main` para exigir que las comprobaciones del workflow pasen antes de integrar cambios y limita quién puede publicar en esa rama. El runner de producción usa privilegios de sistema y solo participa en pushes a `main`, nunca en pull requests.

El runner necesita acceso a GitHub, a la base de datos de producción y a `robocopy`. El servicio debe ejecutarse como sistema local; el workflow usa PHP 8.5 en `C:\Users\HP\.local\php\8.5.12` con `pdo_pgsql` habilitado y Composer en `C:\Users\HP\.config\herd-lite\bin`, y agrega esas rutas al `PATH` del job. También prepara Node.js 22 en cada ejecución. Si PHP o Composer se instalan en otras rutas, ajusta el paso `Configure PHP and Composer` del workflow. No guardes `.env` ni credenciales de producción en el repositorio.

## Solución de problemas

- **No conecta con la base de datos:** confirma que la base exista, que el servicio esté iniciado y que las variables `DB_*` de `.env` sean correctas. Para PostgreSQL, revisa también el puerto y que la extensión `pdo_pgsql` esté disponible.
- **El puente no responde en `127.0.0.1:8765`:** comprueba que JDK 21, los controladores DigitalPersona y sus JAR estén instalados; ejecuta `.\biometria-java\ejecutar.ps1` y consulta la guía del puente.
- **No compila el frontend:** ejecuta `npm ci` y luego `npm run build`.
- **El login inicial no funciona:** las credenciales de ejemplo solo aplican después de ejecutar los seeders y mientras no se hayan cambiado. No ejecutes `db:seed` como método de recuperación: puede volver a escribir los valores iniciales del usuario. No uses esas credenciales en producción.
