# SIGEI — Sistema de Gestión de Emergencias Institucionales

Proyecto de estadías para una institución educativa. El acceso, las cuentas, el reporte público, los incidentes, la campana y Configuración utilizan Laravel y MySQL. La demostración Vue se conserva por separado. Las suscripciones por dispositivo y el service worker están preparados; el envío Web Push desde Laravel sigue pendiente.

## Estado comprobado

| Parte | Situación |
| --- | --- |
| Backend | Laravel 13.37.0 instalado; estructura `app`, `bootstrap`, `config`, `database`, `routes` y `tests` |
| Entorno inspeccionado | PHP 8.3.30, Composer 2.9.7, Node 22.19.0 y npm 10.9.3 |
| Frontend | Vue 3.5.44, Composition API, Vite 6.4.4 y Bootstrap 5.3.8 |
| Recursos | `laravel-vite-plugin` 1.3.0; compilación Laravel separada de la demo |
| Demostración | Datos y acciones en memoria en `resources/js/components/App.vue`; recargar reinicia los cambios |
| Acceso institucional | Correo y contraseña, sesión en servidor, CSRF y gestión de cuentas exclusiva del Administrador |
| Acceso de demostración | Selector ficticio únicamente en la demo; no concede permisos sobre la base real |
| Incidentes reales | Registro público, asignación, recepción, seguimiento y cierre persistentes con autorización |
| Avisos actuales | Campana guardada por cuenta; lectura independiente de recepción. Web Push aún sin envío |
| Dispositivos | Suscripciones por cuenta, navegador y sesión; claves cifradas y desvinculación. Service worker preparado, sin envío desde Laravel |
| Configuración | Nombre, siglas, catálogos y preferencias persistentes; edición exclusiva del Administrador |
| Base de datos | MySQL local `sigei` preparado; credenciales privadas en `.env` |
| Alojamiento | Pendiente; `vercel.json` sigue destinado a la demo estática |

Las versiones exactas están fijadas en `composer.lock` y `package-lock.json`. Laravel Boost se incorpora como herramienta de desarrollo conforme a `AGENTS.md`. Los archivos antiguos `admin/Alertas.vue` y `admin/Historial.vue` se conservan; no se importan en la interfaz actual.

## Orden de implementación

1. **Base local:** configuración Laravel/MySQL, migraciones, modelos, catálogos, folios y pruebas de integridad. Conservar la demo independiente.
2. **Sesión y permisos:** autenticación, cierre de sesión, cuentas activas, roles, protección del último Administrador y acceso por asignación o autoría.
3. **Incidentes reales:** formulario público protegido frente a abuso, validación, asignación, recepción explícita, seguimiento, cierre y auditoría; integrar Vue por partes verificables.
4. **Avisos reales:** campana persistente y Web Push con service worker, suscripciones por cuenta/dispositivo, cola, caducidad y desvinculación al salir o cambiar de cuenta.
5. **Operación:** reportes reales, pruebas de permisos y móviles compatibles con HTTPS, documentación de usuario, alojamiento, respaldos y monitoreo.

El tiempo de espera y el escalamiento cuando nadie confirma recepción deben acordarse con la institución. No hay un protocolo aprobado implementado. Audio y video quedan fuera de estas etapas.

## Alcance de la primera etapa

Las migraciones conservan las originales de Laravel y añaden estructuras para:

- `users`: rol, área y activación; cuentas nuevas inactivas por defecto.
- `emergency_types` y `locations`: catálogos desactivables y ubicaciones con detalle adicional.
- `institution_settings`: configuración única y preferencias de avisos.
- `incidents`: folio único `SIG-` + ULID generado en servidor, reporte, autoría opcional, estado y prioridad.
- `incident_assignments`: historial y confirmación explícita por asignación; una sola asignación abierta por incidente.
- `incident_updates` y `audit_logs`: observaciones internas y estructura para acciones con autor y fecha.
- `internal_notifications`: avisos por cuenta y fecha de lectura.
- `push_subscriptions`: cuenta, dispositivo, datos de suscripción, caducidad y revocación.

Los incidentes guardan los nombres del tipo y la ubicación al registrarse para conservar su contexto cuando cambien los catálogos. Las relaciones preservan los casos y su autoría; se prevé desactivar cuentas y catálogos en lugar de borrar su historia.

`DatabaseSeeder` inicializa configuración y catálogos sin importar cuentas ni casos de demostración. El nombre inicial es **Institución por configurar**. Los catálogos parten del diseño actual y deben revisarse con la institución. Cada catálogo se inicializa únicamente si está vacío; volver a ejecutar el seeder conserva nombres editados, desactivaciones y preferencias, sin completar catálogos personalizados.

La lectura de campana y la recepción explícita se guardan por separado. Las operaciones de incidentes registran auditoría, historial y avisos internos dentro de la misma transacción. Configuración ya dispone de edición persistente; la siguiente etapa completará Web Push.

## Acceso institucional y cuentas

La pantalla inicial en `/` es siempre el botón de pánico público, incluso al recuperar una sesión existente. El login institucional se abre desde “Iniciar sesión”; permite volver al botón, y los dos paneles conservan ese acceso sin cerrar la sesión. Al salir de la cuenta se regresa al botón de pánico. Registrar una emergencia no requiere cuenta: se guarda y devuelve un folio del servidor. La demostración completa permanece en `/demo`, con su selector ficticio y sus datos en memoria, al igual que la demo estática de Vite y Vercel. Las autorizaciones reales siempre se verifican en Laravel.

- Inicio con correo y contraseña, sin registro público ni credenciales predeterminadas.
- Sesión con cookie HttpOnly y protección CSRF; el identificador y el token se renuevan al entrar y salir.
- Límite de cinco fallos por correo e IP durante un minuto, además del límite general de solicitudes al login.
- Revisión de activación, rol y versión de autorización en cada petición protegida. Cambiar correo, contraseña, rol o activación invalida las sesiones anteriores; una reactivación no recupera esas sesiones.
- Listado, alta, edición y activación de cuentas solo para Administradores activos. El bloqueo de la configuración institucional serializa las escrituras y protege al último Administrador activo frente a cambios simultáneos.
- Registro de altas y cambios con autor y fecha; la auditoría excluye contraseñas y tokens. Cambiar correo, contraseña, rol o activación revoca las suscripciones Push de la cuenta. El envío todavía no está conectado.

Los endpoints de incidentes distinguen Administrador, asignación vigente y autoría. Ser autor permite consultar el reporte, pero no sus observaciones internas ni registrar seguimiento como Responsable. La respuesta de Mis reportes excluye notas y autores internos desde el servidor, incluso si la misma cuenta también atiende el caso.

Para crear el **primer Administrador**, ejecutar personalmente en la terminal integrada de VS Code, desde este repositorio y con MySQL iniciado:

```powershell
php artisan sigei:create-admin
```

Solicita nombre, correo, área y contraseña con confirmación. La contraseña no se muestra ni se pasa como argumento. Debe tener al menos 12 caracteres y no superar 72 bytes; algunos caracteres ocupan más de un byte. Si ya existe un Administrador activo, el comando rechaza una segunda alta: las demás cuentas se gestionan desde Usuarios. No se creó ninguna cuenta real durante el desarrollo o las pruebas.

Entrar después con el correo y contraseña elegidos. Un Responsable no recibe el listado de cuentas y no puede gestionarlas. Si se cambian los datos de acceso, rol o activación de la cuenta propia, se cierra su sesión y debe entrar nuevamente. No se almacenan credenciales en localStorage.

Los paneles institucionales identifican los módulos aún pendientes de conexión; no muestran casos ficticios como datos reales. El enlace a la demostración permite seguir revisando el recorrido completo del diseño.

## Incidentes y avisos persistentes

El botón conserva la confirmación breve y el formulario único. Tipos y ubicaciones se consultan en MySQL; el campo de ubicación adicional depende del indicador del catálogo, de modo que funciona aunque se cambie el nombre de “Otro lugar”. La descripción es opcional. El servidor fija autoría, fecha UTC, folio único, estado Pendiente y prioridad Por evaluar. El recibo solo se muestra tras una respuesta válida del servidor y no equivale a recepción del personal ni a entrega Web Push.

El formulario reutiliza un identificador de solicitud al reintentar. Laravel guarda su huella con índice único, ámbito de sesión/cuenta y huella del contenido: repetir la misma solicitud devuelve el mismo caso; reutilizarla desde otra cuenta o con contenido diferente se rechaza sin revelar el folio. El servidor aplica CSRF, validación, un campo señuelo y límites técnicos iniciales de 5 solicitudes por sesión y 60 por IP cada minuto. Estos límites se ajustan con `SIGEI_REPORTS_PER_SESSION_MINUTE` y `SIGEI_REPORTS_PER_IP_MINUTE`; deben revisarse para la red compartida de la institución. No son un protocolo de espera ni de escalamiento.

Flujo de uso:

1. Cualquier persona registra el reporte sin iniciar sesión. Si tiene una sesión válida, la autoría se toma de esa cuenta, independientemente de quién atienda el incidente.
2. El Administrador abre Incidentes y asigna una cuenta de Responsable activa y la prioridad. Puede añadir notas internas.
3. El Responsable abre Alertas asignadas y confirma recepción explícitamente. El estado inicial pasa a Recibida; leer la campana no realiza esa confirmación.
4. Con prioridad Alta, Media o Baja, el Responsable conserva el estado con una nota o avanza un paso: Recibida → En atención → Atendida. Atendida queda pendiente del cierre administrativo.
5. El Administrador selecciona Cerrada y escribe una observación de cierre. Un caso cerrado es de consulta.

Una reasignación conserva el historial y el estado alcanzado. La nueva asignación necesita su propia confirmación antes de seguir atendiendo; no hereda la recepción anterior. Las escrituras bloquean la configuración institucional y el caso, revalidan la cuenta y comparan la versión del incidente. Si otra operación ya lo cambió, la respuesta es 409 y la interfaz conserva las notas para revisar el caso antes de guardar nuevamente.

Dashboard, Incidentes y Reportes consultan todas las páginas autorizadas antes de mostrar indicadores o exportar CSV. Los paneles visibles actualizan casos y avisos cada 15 segundos y disponen de botón de actualización. Eso es consulta periódica dentro de la página, no Web Push. Mis reportes consulta exclusivamente la autoría, con una proyección sin comentarios internos.

Los avisos por registro, asignación y seguimiento se guardan por destinatario según las preferencias actuales. La lectura individual o de todos los avisos no cambia la recepción del incidente. Abrir un aviso consulta el caso y comprueba los permisos vigentes antes de mostrarlo. Cerrar sesión o cambiar de cuenta limpia los datos del panel; las respuestas antiguas no se incorporan a otra cuenta.

El envío al celular desde Laravel, la cola y las pruebas Android/iPhone con HTTPS siguen pendientes. La preparación de suscripciones y service worker se describe abajo. No se implementó audio, video ni escalamiento automático.

## Configuración persistente

Entrar con una cuenta de Administrador y abrir **Configuración**. Permite cambiar el nombre de la institución y las siglas, agregar o renombrar tipos y ubicaciones, desactivar o reactivar opciones y guardar las preferencias de avisos. Una ubicación puede pedir que se especifique el lugar, independientemente de su nombre; ese indicador controla el campo adicional del reporte público.

**Guardar configuración** aplica el formulario completo en una transacción. Las opciones nuevas reciben su identificador en el servidor. Desactivar una opción la retira de los nuevos reportes; no borra sus registros ni cambia los nombres guardados en incidentes anteriores. El servidor impide duplicados, identificadores desconocidos, catálogos incompletos y dejar un catálogo sin opciones activas. Cada catálogo admite hasta 200 opciones, incluidas las inactivas; los nombres de tipos admiten 100 caracteres y los de ubicaciones 150.

Las preferencias son institucionales y afectan solo a los nuevos avisos de la campana: registro, asignación y seguimiento. No eliminan avisos anteriores, no confirman recepción y no activan Web Push. Los cambios se registran en auditoría con autor, fecha y valores anteriores y nuevos.

`GET /api/settings` y `PUT /api/settings` requieren un Administrador activo. Laravel valida los campos, comprueba otra vez la cuenta dentro de la transacción y compara una versión de configuración. Una edición desactualizada recibe 409 sin sobrescribir la versión guardada. La interfaz conserva el borrador; **Descartar y recargar** lo sustituye por la versión actual únicamente si la consulta tiene éxito. La consulta pública sigue mostrando solo los datos institucionales y las opciones activas, sin preferencias ni autor de edición. Al volver al botón de pánico se actualizan los catálogos.

La migración `2026_10_30_000001_add_lock_version_to_institution_settings_table` agrega únicamente el contador de versión; `php artisan migrate` la aplica conservando los datos. El botón de pánico continúa siendo la pantalla inicial, y la demo mantiene su configuración en memoria.

## Suscripciones Web Push: primera etapa

Desde la campana del panel institucional se puede **Vincular este dispositivo con mi cuenta**, comprobar la vinculación y **Desvincular este dispositivo**. El permiso solo se solicita tras pulsar el botón. La comprobación periódica no solicita permiso ni crea suscripciones automáticamente. El estado vinculado exige una suscripción del navegador y un registro coincidente en Laravel; permiso concedido por sí solo no basta. La pantalla indica que el envío al celular aún está pendiente.

Para preparar otra instalación, con las migraciones aplicadas:

```powershell
php artisan sigei:push-keys
```

El comando usa OpenSSL P-256 y guarda las claves en `storage/app/private/sigei-vapid.json`, excluido de Git. No muestra la clave privada ni sustituye un archivo existente. En Laragon utiliza, cuando hace falta, el archivo OpenSSL incluido junto a PHP; `SIGEI_OPENSSL_CONFIG` permite especificar otra ruta. Respaldar de forma privada estas claves y la `APP_KEY`; no regenerarlas sobre una instalación en uso. No se añadieron dependencias.

La migración `2026_10_30_000002_secure_push_subscription_bindings` cifra dirección y claves de las suscripciones existentes, conserva sus contenidos y exige volver a vincular los registros antiguos. Añade una asociación renovable, una huella de sesión, la versión de autorización y una restricción para permitir una sola asociación activa por dispositivo entre todas las cuentas. Los datos cifrados dependen de la `APP_KEY` existente.

Laravel identifica este navegador mediante una cookie cifrada y HttpOnly; el cliente no elige el propietario ni el identificador de dispositivo. La petición incluye la cuenta esperada únicamente para rechazar cambios de cuenta durante la operación. Se comprueban activación, rol, versión y CSRF, y las escrituras se serializan con las de cuentas e incidentes. Al salir se revoca la asociación de esa sesión y dispositivo; los otros dispositivos de la cuenta se conservan. Un nuevo inicio de sesión invalida cualquier asociación anterior de este navegador y requiere una vinculación explícita. Las respuestas tardías de otra cuenta se descartan.

`GET /api/push` devuelve únicamente clave pública y estado de la asociación actual; `POST /api/push` registra la suscripción del navegador y `DELETE /api/push` la revoca. Las direcciones admitidas usan HTTPS y servicios de Google, Mozilla o Apple configurados en `config/sigei.php`; no se realiza ninguna petición saliente a esos destinos desde Laravel en esta etapa. Las suscripciones caducadas no se presentan como vinculadas. La auditoría de alta y desvinculación no almacena direcciones ni claves.

`public/sigei-sw.js` prepara la recepción de un identificador opaco de aviso y su asociación. Antes de mostrar un aviso genérico, consulta al servidor con la sesión del navegador y comprueba destinatario, asociación vigente y permiso sobre el caso. No muestra observaciones, ubicaciones ni datos del incidente en la pantalla del dispositivo. Al tocarlo abre una dirección del propio SIGEI; la aplicación recupera sesión y permisos antes de abrir el caso. El enlace explícito puede solicitar inicio de sesión; la entrada habitual sigue siendo el botón de pánico. Leer el aviso no confirma recepción. El worker no intercepta ni guarda páginas, respuestas API ni reportes para enviarlos sin conexión.

El manifiesto y el icono del sistema están preparados para su revisión como aplicación web. Se requiere un navegador compatible y un contexto seguro: HTTPS o localhost. Abrir desde otro teléfono mediante una dirección HTTP de la red local no acredita compatibilidad ni recepción. Las pruebas físicas, la revisión de instalación en iPhone y la entrega con la página cerrada siguen pendientes. Referencias: [suscripción del navegador](https://developer.mozilla.org/en-US/docs/Web/API/PushManager/subscribe), [Web Push en iOS y iPadOS](https://webkit.org/blog/13878/), [formato de los destinos de Mozilla](https://mozilla-services.github.io/autopush-rs/http.html).

El siguiente paso será integrar una biblioteca de envío compatible con PHP 8.3, trabajos en cola, reintentos y tratamiento de respuestas de suscripciones caducadas. `delivery_available` permanece en `false`; esta etapa no acredita avisos enviados, entregados ni recibidos por el personal, ni implementa tiempos de espera o escalamiento.

## Windows PowerShell

Ejecutar desde la carpeta del repositorio. Se necesitan PHP 8.3 con las extensiones de Laravel y `pdo_mysql`, Composer, Node/npm y MySQL local. Las pruebas rápidas utilizan `pdo_sqlite`.

```powershell
php -v
composer -V
node --version
npm --version
composer install
npm ci
```

No ejecutar el antiguo `composer run setup` sobre una instalación existente: primero revisar `.env`, la conexión y las migraciones pendientes. No reemplazar una `APP_KEY` que ya se utilice para datos cifrados.

### Demostración independiente

```powershell
npm run dev
npm run build
```

Abrir la dirección que muestre Vite. El recorrido permanece igual y los datos se pierden al recargar. `npm run build` sigue generando `dist` desde `index.html`. Para verificar sin modificar el `dist` existente:

```powershell
npm run build -- --outDir storage/framework/testing/frontend-build
```

### Preparar MySQL local

Iniciar MySQL con la herramienta local utilizada y crear una base vacía desde su cliente, con una cuenta autorizada:

```sql
CREATE DATABASE sigei CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Si ya existe, revisar su contenido y hacer respaldo antes de aplicar migraciones. No borrar ni recrear una base que contenga trabajo existente.

Crear `.env` únicamente si aún no existe:

```powershell
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
}
```

Completar `.env` con la conexión real. `.env.example` deja usuario y contraseña vacíos para configurarlos localmente. No subir `.env`, credenciales ni claves a GitHub.

```dotenv
APP_NAME=SIGEI
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_MX
SIGEI_DISPLAY_TIMEZONE=America/Cancun

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sigei
DB_USERNAME=
DB_PASSWORD=
```

Solo si `APP_KEY` está vacío, ejecutar `php artisan key:generate`. Después:

```powershell
php artisan config:clear
php artisan migrate:status
php artisan migrate --seed
```

`migrate` aplica únicamente migraciones pendientes. No utilizar `migrate:fresh` para preparar la base de trabajo. Para el primer Administrador, utilizar el comando interactivo explicado arriba.

Laravel y la conexión MySQL trabajan en **UTC**. `config/sigei.php` define **America/Cancun** para visualización. La demo ya formatea sus fechas en esa zona; al integrar la API se deberán convertir las fechas del servidor al mostrarlas.

### Abrir desde Laravel

```powershell
npm run build:laravel
php artisan serve
```

Abrir la dirección que indique Artisan. `build:laravel` genera `public/build/manifest.json`, utilizado por `resources/views/app.blade.php`. El acceso, Usuarios, los incidentes, la campana y Configuración trabajan con MySQL. El recorrido ficticio permanece en `/demo`; sus incidentes y avisos siguen en memoria. Ejecutar únicamente `npm run dev` abre la demo estática, no el sistema conectado.

Para desarrollo, abrir dos terminales: una con `php artisan serve` y otra con `npm run dev:laravel`. Detener Vite con Ctrl+C al terminar para que retire `public/hot`.

Sesiones, caché y colas tienen configuración para base de datos. Las tablas de trabajos y fallos ya pertenecían al esqueleto Laravel; aún no hay trabajos de envío Push. El alojamiento final deberá permitir HTTPS y un proceso permanente para la cola.

## Comprobaciones

La suite predeterminada utiliza SQLite en memoria y una clave exclusiva de pruebas mediante `phpunit.xml`. No requiere una compilación previa de Vite. La protección de `tests/TestCase.php` rechaza archivos SQLite persistentes, bases MySQL con otros nombres y conexiones mediante `DB_URL` antes de las migraciones:

```powershell
php artisan config:clear
php artisan test
npm run test:frontend
vendor/bin/pint --dirty
npm run build -- --outDir storage/framework/testing/frontend-build
npm run build:laravel
```

SQLite no reemplaza la comprobación del esquema y sus restricciones en MySQL. Para probar en MySQL, crear una base **exclusiva de pruebas**, vacía y sin información que deba conservarse:

```sql
CREATE DATABASE sigei_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Las pruebas pueden reconstruir sus tablas. Usar las credenciales locales de `.env` y este bloque PowerShell para seleccionar únicamente esa base y restaurar las variables al terminar:

```powershell
$sigeiTestDatabase = 'sigei_testing'
if ($sigeiTestDatabase -ne 'sigei_testing') {
    throw 'Utiliza únicamente la base dedicada sigei_testing.'
}
$sigeiTestVariables = @{
    APP_ENV = 'testing'
    DB_CONNECTION = 'mysql'
    DB_DATABASE = $sigeiTestDatabase
    DB_URL = ''
}
$sigeiPreviousVariables = @{}
foreach ($sigeiVariable in $sigeiTestVariables.Keys) {
    $sigeiPreviousVariables[$sigeiVariable] = [Environment]::GetEnvironmentVariable($sigeiVariable, 'Process')
}
try {
    foreach ($sigeiVariable in $sigeiTestVariables.Keys) {
        [Environment]::SetEnvironmentVariable($sigeiVariable, $sigeiTestVariables[$sigeiVariable], 'Process')
    }
    php artisan config:clear
    php artisan test
} finally {
    foreach ($sigeiVariable in $sigeiTestVariables.Keys) {
        [Environment]::SetEnvironmentVariable($sigeiVariable, $sigeiPreviousVariables[$sigeiVariable], 'Process')
    }
}
```

## Resultado de las etapas locales

| Comprobación ejecutada | Resultado |
| --- | --- |
| Suite SQLite en memoria | 203 pruebas correctas, 1159 aserciones; se omite solo la comparación de acentos específica de MySQL |
| Misma suite en MySQL 8.4.3 aislado | 204 pruebas, 1165 aserciones, correctas |
| Cliente, sesión y service worker (`npm run test:frontend`) | 27 pruebas correctas; paginación, recibos, vinculación, cambios de cuenta y apertura de avisos |
| Reversión y reaplicación de las trece migraciones nuevas | Correcta; conserva los datos originales de usuario y las claves de suscripciones durante su conversión |
| Build de la demo en una salida temporal | Correcto; `dist` conserva los cambios previos |
| `npm run build:laravel` | Correcto; manifest y recursos para Blade generados |
| Laravel Pint y `composer validate` | Correctos |
| Preparación de la base local `sigei` en MySQL 8.4.3 | 16 migraciones aplicadas; 18 tablas, 1 configuración, 5 tipos y 10 ubicaciones |

Las pruebas cubren restricciones, folios y fechas, autenticación, CSRF real, revocación de sesiones, permisos, último Administrador, alta inicial, registro público, deduplicación, límites, transición de estados, reasignación, conflictos, privacidad y lectura de avisos. Las pruebas de Configuración comprueban persistencia, autorización, versiones, auditoría, validación, desactivación, conservación de incidentes y aplicación de preferencias a los nuevos avisos. Las pruebas de dispositivos verifican cifrado, revocación, cambio de cuenta, caducidad, protección de claves, destinos admitidos, contexto de mensajes y conservación de límites de acceso con caché en base de datos. Las pruebas del cliente y worker usan APIs simuladas; no equivalen a una suscripción o entrega en un navegador real. La revisión manual previa comprobó el botón inicial, la confirmación, los catálogos y el campo Otro lugar sin registrar datos ficticios en la base de trabajo. La revisión visual de Configuración y dispositivos queda pendiente porque la herramienta de navegador no pudo iniciar. No se acredita entrega Push ni pruebas en teléfonos físicos. Se actualizó `symfony/mime` de 7.4.19 a 7.4.22 para corregir el aviso CVE-2026-45070 detectado por Composer; la actualización terminó sin avisos de seguridad.

Las pruebas automatizadas de MySQL se verificaron en una instancia temporal independiente, que se apagó al terminar. La base de trabajo `sigei` recibió las migraciones aditivas para revocar sesiones, controlar versiones de incidentes y configuración, y deduplicar solicitudes. Se conservó la cuenta creada por el usuario y no se importaron incidentes de demostración. Las credenciales permanecen en el `.env` local, excluido de Git.

## Pendientes de seguridad y operación

- Conectar el envío Web Push desde Laravel con colas, reintentos y tratamiento de suscripciones rechazadas o caducadas por el proveedor.
- Verificar Android e iPhone compatibles con HTTPS: permiso concedido no equivale a suscripción, entrega ni recepción confirmada.
- Comparar alojamiento que soporte Laravel, MySQL y procesos de cola, con documentación, precios y límites vigentes antes de decidir o contratar.
- Preparar respaldos, monitoreo, documentación de usuario y evidencias reales de pruebas.

No se publica ni se contrata alojamiento en esta etapa. En la documentación Scrum se deben registrar únicamente actividades y evidencias efectivamente realizadas; estos pendientes no representan actividades ni fechas ejecutadas.

Referencias: [Vite en Laravel 13](https://laravel.com/docs/13.x/vite), [configuración de base de datos](https://laravel.com/docs/13.x/database) y [migraciones](https://laravel.com/docs/13.x/migrations).
