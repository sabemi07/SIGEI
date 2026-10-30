# SIGEI — Sistema de Gestión de Emergencias Institucionales

Proyecto de estadías para una institución educativa. El acceso, las cuentas, el reporte público, los incidentes, la campana y Configuración utilizan Laravel y MySQL. Web Push está integrado mediante colas y se comprobó la recepción de una prueba en Brave de escritorio. La demostración Vue se conserva por separado; las pruebas físicas Android/iPhone con HTTPS siguen pendientes.

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
| Avisos actuales | Campana y Web Push mediante cola; aceptación del proveedor, lectura y recepción del personal se distinguen |
| Dispositivos | Suscripciones por cuenta y dispositivo, claves cifradas, revocación y mensajes genéricos protegidos; prueba real recibida en Brave |
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
- `push_deliveries`: estado de envío por aviso y asociación de dispositivo, intentos y respuesta del proveedor sin claves ni direcciones.

Los incidentes guardan los nombres del tipo y la ubicación al registrarse para conservar su contexto cuando cambien los catálogos. Las relaciones preservan los casos y su autoría; se prevé desactivar cuentas y catálogos en lugar de borrar su historia.

`DatabaseSeeder` inicializa configuración y catálogos sin importar cuentas ni casos de demostración. El nombre inicial es **Institución por configurar**. Los catálogos parten del diseño actual y deben revisarse con la institución. Cada catálogo se inicializa únicamente si está vacío; volver a ejecutar el seeder conserva nombres editados, desactivaciones y preferencias, sin completar catálogos personalizados.

La lectura de campana y la recepción explícita se guardan por separado. Las operaciones de incidentes registran auditoría, historial y avisos internos dentro de la misma transacción. Sus trabajos Push se insertan en la cola de base de datos en esa transacción; la cola debe usar la misma conexión MySQL de la aplicación. El envío externo ocurre después, desde el procesador de cola.

## Acceso institucional y cuentas

La pantalla inicial en `/` es siempre el botón de pánico público, incluso al recuperar una sesión existente. El login institucional se abre desde “Iniciar sesión”; permite volver al botón, y los dos paneles conservan ese acceso sin cerrar la sesión. Al salir de la cuenta se regresa al botón de pánico. Registrar una emergencia no requiere cuenta: se guarda y devuelve un folio del servidor. La demostración completa permanece en `/demo`, con su selector ficticio y sus datos en memoria, al igual que la demo estática de Vite y Vercel. Las autorizaciones reales siempre se verifican en Laravel.

- Inicio con correo y contraseña, sin registro público ni credenciales predeterminadas.
- Sesión con cookie HttpOnly y protección CSRF; el identificador y el token se renuevan al entrar y salir.
- Límite de cinco fallos por correo e IP durante un minuto, además del límite general de solicitudes al login.
- Revisión de activación, rol y versión de autorización en cada petición protegida. Cambiar correo, contraseña, rol o activación invalida las sesiones anteriores; una reactivación no recupera esas sesiones.
- Listado, alta, edición y activación de cuentas solo para Administradores activos. El bloqueo de la configuración institucional serializa las escrituras y protege al último Administrador activo frente a cambios simultáneos.
- Registro de altas y cambios con autor y fecha; la auditoría excluye contraseñas y tokens. Cambiar correo, contraseña, rol o activación revoca las suscripciones Push de la cuenta y los trabajos pendientes comprueban de nuevo esa revocación.

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

El envío desde Laravel y la cola están conectados. Se comprobó un aviso de prueba en Brave de escritorio; las pruebas físicas Android/iPhone con HTTPS siguen pendientes. No se implementó audio, video ni escalamiento automático.

## Configuración persistente

Entrar con una cuenta de Administrador y abrir **Configuración**. Permite cambiar el nombre de la institución y las siglas, agregar o renombrar tipos y ubicaciones, desactivar o reactivar opciones y guardar las preferencias de avisos. Una ubicación puede pedir que se especifique el lugar, independientemente de su nombre; ese indicador controla el campo adicional del reporte público.

**Guardar configuración** aplica el formulario completo en una transacción. Las opciones nuevas reciben su identificador en el servidor. Desactivar una opción la retira de los nuevos reportes; no borra sus registros ni cambia los nombres guardados en incidentes anteriores. El servidor impide duplicados, identificadores desconocidos, catálogos incompletos y dejar un catálogo sin opciones activas. Cada catálogo admite hasta 200 opciones, incluidas las inactivas; los nombres de tipos admiten 100 caracteres y los de ubicaciones 150.

Las preferencias son institucionales y controlan los nuevos avisos de registro, asignación y seguimiento, tanto en la campana como por Web Push cuando el dispositivo está vinculado. No eliminan avisos anteriores, no confirman recepción ni vinculan dispositivos. Los cambios se registran en auditoría con autor, fecha y valores anteriores y nuevos.

`GET /api/settings` y `PUT /api/settings` requieren un Administrador activo. Laravel valida los campos, comprueba otra vez la cuenta dentro de la transacción y compara una versión de configuración. Una edición desactualizada recibe 409 sin sobrescribir la versión guardada. La interfaz conserva el borrador; **Descartar y recargar** lo sustituye por la versión actual únicamente si la consulta tiene éxito. La consulta pública sigue mostrando solo los datos institucionales y las opciones activas, sin preferencias ni autor de edición. Al volver al botón de pánico se actualizan los catálogos.

La migración `2026_10_30_000001_add_lock_version_to_institution_settings_table` agrega únicamente el contador de versión; `php artisan migrate` la aplica conservando los datos. El botón de pánico continúa siendo la pantalla inicial, y la demo mantiene su configuración en memoria.

## Notificaciones Web Push

Desde la campana del panel institucional se puede **Vincular este dispositivo con mi cuenta**, comprobar la vinculación, **Desvincular este dispositivo** y **Enviar aviso de prueba**. El permiso solo se solicita tras pulsar el botón. La comprobación periódica no solicita permiso ni crea suscripciones automáticamente. El estado vinculado exige una suscripción del navegador y un registro coincidente en Laravel; permiso concedido por sí solo no basta.

Para preparar otra instalación, con las migraciones aplicadas:

```powershell
php artisan sigei:push-keys
```

El comando usa OpenSSL P-256 y guarda las claves en `storage/app/private/sigei-vapid.json`, excluido de Git. No muestra la clave privada ni sustituye un archivo existente. En Laragon utiliza, cuando hace falta, el archivo OpenSSL incluido junto a PHP; `SIGEI_OPENSSL_CONFIG` permite especificar otra ruta. El envío prepara esa configuración en el proceso sin cambiar la instalación de PHP. Respaldar de forma privada estas claves y la `APP_KEY`; no regenerarlas sobre una instalación en uso. Se añadió `minishlink/web-push` 11.0.0 y siete paquetes en total, con autorización del usuario y conservando las versiones de las dependencias existentes.

En otra instalación, configurar en el `.env` privado un contacto técnico real, proporcionado por el operador. Estos valores son un ejemplo y el correo debe sustituirse:

```dotenv
SIGEI_PUSH_ENABLED=true
SIGEI_VAPID_SUBJECT=mailto:contacto@institucion.example
SIGEI_PUSH_TTL=3600
```

Después de cambiar la configuración, ejecutar `php artisan config:clear`. La clave privada y el correo no se incluyen en el frontend ni se registran en auditoría. La clave pública permite crear la suscripción del navegador. En esta instalación se configuró el contacto indicado por el usuario sin publicarlo en el repositorio.

Mantener una segunda terminal de VS Code abierta mientras se prueban los envíos locales:

```powershell
php artisan queue:work database --queue=webpush --tries=3 --timeout=45
```

Esa terminal procesa los trabajos de la cola `webpush`. Si se detiene, los nuevos avisos quedan pendientes de envío. Reiniciar el procesador después de cambios en el código PHP o la configuración. Para producción se necesitará un proceso supervisado y permanente; todavía no hay alojamiento elegido.

Para verificar el recorrido sin crear incidentes, iniciar sesión, abrir la campana y pulsar **Enviar aviso de prueba**. Se crea un aviso identificado como prueba, destinado únicamente al dispositivo actual. Una prueba pendiente se reutiliza y el endpoint limita las solicitudes. La interfaz distingue pendiente, en proceso, aceptada por el servicio, fallida, caducada u omitida. **Aceptada por el proveedor** no confirma por sí sola que el navegador la haya mostrado. Abrirla solo marca su lectura; no confirma la recepción de ningún incidente.

La migración `2026_10_30_000002_secure_push_subscription_bindings` cifra dirección y claves de las suscripciones existentes, conserva sus contenidos y exige volver a vincular los registros antiguos. Añade una asociación renovable, una huella de sesión, la versión de autorización y una restricción para permitir una sola asociación activa por dispositivo entre todas las cuentas. Los datos cifrados dependen de la `APP_KEY` existente.

Laravel identifica este navegador mediante una cookie cifrada y HttpOnly; el cliente no elige el propietario ni el identificador de dispositivo. La petición incluye la cuenta esperada únicamente para rechazar cambios de cuenta durante la operación. Se comprueban activación, rol, versión y CSRF, y las escrituras se serializan con las de cuentas e incidentes. Al salir se revoca la asociación de esa sesión y dispositivo; los otros dispositivos de la cuenta se conservan. Un nuevo inicio de sesión invalida cualquier asociación anterior de este navegador y requiere una vinculación explícita. Las respuestas tardías de otra cuenta se descartan.

`GET /api/push` devuelve clave pública, estado de la asociación y última prueba de ese dispositivo; `POST /api/push` registra la suscripción, `DELETE /api/push` la revoca y `POST /api/push/test` solicita una prueba para la cuenta y el dispositivo actuales. Las direcciones admitidas usan HTTPS y servicios de Google, Mozilla o Apple configurados en `config/sigei.php`. El emisor vuelve a validar el destino, mantiene la verificación de certificados y no sigue redirecciones. Las suscripciones caducadas no se presentan como vinculadas. La auditoría no almacena direcciones ni claves.

`public/sigei-sw.js` recibe únicamente un identificador opaco de aviso y su asociación, dentro del mensaje cifrado. Antes de mostrar un aviso genérico, consulta al servidor con la cookie cifrada del dispositivo y la asociación recibida. Se comprueban destinatario, activación, versión de autorización y permiso sobre el caso. Esta comprobación solo devuelve texto genérico y permite recibir avisos aunque la sesión de acceso haya caducado. Al tocar el aviso, la aplicación exige sesión y permisos para abrir el caso. El enlace explícito puede solicitar inicio de sesión; la entrada habitual sigue siendo el botón de pánico. El worker no muestra observaciones, ubicaciones ni datos internos y no intercepta ni guarda páginas, respuestas API ni reportes para enviarlos sin conexión.

Los eventos nuevos de registro, asignación y seguimiento se encolan según las preferencias institucionales; no se reenvía el historial anterior al habilitar Push. Antes de enviar se vuelven a comprobar preferencias, cuenta, permisos, asociación y caducidad. Una respuesta 404 o 410 revoca la asociación caducada sin afectar una asociación posterior. Los errores temporales de red, 429 y fallos del proveedor pueden reintentarse hasta tres veces, con esperas técnicas de 15 y 60 segundos. Los rechazos permanentes y los errores de certificado se registran sin direcciones, claves ni mensajes sensibles del proveedor. Un fallo de Push no elimina el aviso interno ni realiza llamadas externas durante el formulario de emergencia.

El manifiesto y el icono del sistema están preparados para su revisión como aplicación web. Se requiere un navegador compatible y un contexto seguro: HTTPS o localhost. Abrir desde otro teléfono mediante una dirección HTTP de la red local no acredita compatibilidad ni recepción. Las pruebas físicas, la revisión de instalación en iPhone y la entrega con la página cerrada siguen pendientes. Referencias: [suscripción del navegador](https://developer.mozilla.org/en-US/docs/Web/API/PushManager/subscribe), [Web Push en iOS y iPadOS](https://webkit.org/blog/13878/), [formato de los destinos de Mozilla](https://mozilla-services.github.io/autopush-rs/http.html).

En Brave de escritorio, `Registration failed - push service error` puede aparecer si el navegador no tiene habilitado su servicio de mensajería Push. Revisar **Configuración → Privacidad y seguridad → Use Google services for push messaging**; esta opción permite usar el servicio de Google y es independiente del permiso concedido a SIGEI. Si se elige habilitarla, recargar SIGEI y volver a vincular el dispositivo. El mensaje también puede deberse a un fallo de conexión con el proveedor, por lo que no confirma por sí solo que esa opción esté desactivada. SIGEI muestra una explicación de este caso y conserva el estado sin vincular cuando el navegador falla. Referencias: [ayuda oficial de Brave](https://support.brave.app/hc/en-us/articles/360017989132-How-do-I-change-my-Privacy-Settings), [incidencia del navegador con ese mensaje](https://github.com/brave/brave-browser/issues/41930).

`delivery_available` indica que están preparados biblioteca, claves, contacto y conexión de cola; no acredita que el procesador esté ejecutándose ni la entrega al dispositivo. `SIGEI_PUSH_TTL` limita técnicamente la vigencia del mensaje en cola y en el proveedor. Las esperas de reintento y esa vigencia no son tiempos de confirmación de recepción ni un protocolo de escalamiento; ese acuerdo con la institución sigue pendiente.

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

Sesiones, caché y colas utilizan la configuración de base de datos. Los envíos Push usan la cola `webpush`; el alojamiento final deberá permitir HTTPS y un proceso permanente para atenderla.

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
| Suite SQLite en memoria | 220 pruebas correctas, 1274 aserciones; se omite solo la comparación de acentos específica de MySQL |
| Misma suite en MySQL 8.4.3 aislado | 221 pruebas, 1280 aserciones, correctas |
| Cliente, sesión y service worker (`npm run test:frontend`) | 34 pruebas correctas; incluye prueba encolada, separación de aceptación y entrega, y respuestas de otra cuenta |
| Reversión y reaplicación de las catorce migraciones nuevas | Correcta; conserva los datos originales de usuario y las claves de suscripciones durante su conversión |
| Build de la demo en una salida temporal | Correcto; `dist` conserva los cambios previos |
| `npm run build:laravel` | Correcto; manifest y recursos para Blade generados |
| Laravel Pint y `composer validate` | Correctos |
| Preparación de la base local `sigei` en MySQL 8.4.3 | 17 migraciones aplicadas; 19 tablas, configuración y catálogos conservados |
| Prueba real local de Web Push | El usuario confirmó una notificación de Brave en su laptop; el proveedor aceptó los envíos y no había fallos registrados al comprobarlo |

Las pruebas cubren restricciones, folios y fechas, autenticación, CSRF real, revocación de sesiones, permisos, último Administrador, alta inicial, registro público, deduplicación, límites, transición de estados, reasignación, conflictos, privacidad y lectura de avisos. Configuración y dispositivos tienen pruebas de persistencia, versiones, cifrado, revocación, permisos y validación. La nueva suite verifica transacciones de aviso y trabajo, mensajes cifrados con la biblioteca real y cliente HTTP simulado, respuestas 404/410, reintentos, rechazo permanente, asociación cambiada y prueba destinada al dispositivo actual. El usuario confirmó la recepción real de una notificación en Brave y la corrección del foco del formulario Usuarios. Las simulaciones automatizadas no acreditan otras plataformas: Android/iPhone, recepción con la página cerrada y revisión móvil completa siguen pendientes. Se actualizó `symfony/mime` de 7.4.19 a 7.4.22 para corregir el aviso CVE-2026-45070 detectado por Composer; la actualización terminó sin avisos de seguridad.

Las pruebas automatizadas de MySQL se verificaron en una instancia temporal independiente, que se apagó al terminar. La base de trabajo `sigei` recibió las migraciones aditivas para revocar sesiones, controlar versiones de incidentes y configuración, y deduplicar solicitudes. Se conservó la cuenta creada por el usuario y no se importaron incidentes de demostración. Las credenciales permanecen en el `.env` local, excluido de Git.

## Pendientes de seguridad y operación

- Completar las pruebas reales del flujo de incidentes y Web Push con varias cuentas, dispositivos, sesión caducada y página cerrada.
- Verificar Android e iPhone compatibles con HTTPS: permiso concedido no equivale a suscripción, entrega ni recepción confirmada.
- Comparar alojamiento que soporte Laravel, MySQL y procesos de cola, con documentación, precios y límites vigentes antes de decidir o contratar.
- Preparar respaldos, monitoreo, documentación de usuario y evidencias reales de pruebas.

No se publica ni se contrata alojamiento en esta etapa. En la documentación Scrum se deben registrar únicamente actividades y evidencias efectivamente realizadas; estos pendientes no representan actividades ni fechas ejecutadas.

Referencias: [Vite en Laravel 13](https://laravel.com/docs/13.x/vite), [configuración de base de datos](https://laravel.com/docs/13.x/database) y [migraciones](https://laravel.com/docs/13.x/migrations).
