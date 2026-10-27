<script setup>
import { computed, ref, watch } from 'vue';

import Login from './Login.vue';
import BotonPanico from './BotonPanico.vue';
import ResponsableLayout from './responsable/ResponsableLayout.vue';
import AlertasAsignadas from './responsable/AlertasAsignadas.vue';
import AdminLayout from './admin/AdminLayout.vue';
import Dashboard from './admin/Dashboard.vue';
import Incidentes from './admin/Incidentes.vue';
import DetalleIncidente from './admin/DetalleIncidente.vue';
import MisReportes from './responsable/MisReportes.vue';
import Usuarios from './admin/Usuarios.vue';
import Reportes from './admin/Reportes.vue';
import Configuracion from './admin/Configuracion.vue';
import Notificaciones from './Notificaciones.vue';
const usuarios = ref([
    {
        id: 1,
        nombre: 'Administrador de demostración',
        correo: 'admin@example.com',
        rol: 'Administrador',
        area: 'Administración',
        activo: true,
    },
    {
        id: 2,
        nombre: 'Responsable de seguridad',
        correo: 'seguridad@example.com',
        rol: 'Responsable',
        area: 'Seguridad',
        activo: true,
    },
    {
        id: 3,
        nombre: 'Responsable de atención médica',
        correo: 'medico@example.com',
        rol: 'Responsable',
        area: 'Atención médica',
        activo: false,
    },
]);
const incidentes = ref([
    {
        folio: 'SIG-2026-001',
        tipo: 'Emergencia médica',
        ubicacion: 'Edificio A · Planta baja',
        fecha: '19/10/2026',
        hora: '10:42',
        reportante: 'Usuario de demostración',
        descripcion: 'Persona que requiere asistencia médica.',
        prioridad: 'Alta',
        estado: 'Pendiente',
        responsable: 'Sin asignar',
        observaciones: '',
    },
    {
        folio: 'SIG-2026-002',
        tipo: 'Incidente de seguridad',
        ubicacion: 'Acceso principal',
        fecha: '19/10/2026',
        hora: '10:28',
        reportante: 'Usuario de demostración',
        descripcion: 'Se solicita revisar una situación en el acceso principal.',
        prioridad: 'Media',
        estado: 'En atención',
        responsable: 'Personal de seguridad',
        observaciones: 'Personal enviado a revisar la situación.',
    },
    {
        folio: 'SIG-2026-003',
        tipo: 'Accidente',
        ubicacion: 'Laboratorio 2',
        fecha: '19/10/2026',
        hora: '09:55',
        reportante: 'Usuario de demostración',
        descripcion: 'Se reporta un accidente dentro del laboratorio.',
        prioridad: 'Alta',
        estado: 'Pendiente',
        responsable: 'Sin asignar',
        observaciones: '',
    },
    {
        folio: 'SIG-2026-004',
        tipo: 'Situación de riesgo',
        ubicacion: 'Edificio B · Segundo piso',
        fecha: '19/10/2026',
        hora: '09:30',
        reportante: 'Usuario de demostración',
        descripcion: 'Situación revisada por el personal responsable.',
        prioridad: 'Baja',
        estado: 'Atendida',
        responsable: 'Personal administrativo',
        observaciones: 'Revisión completada.',
    },
    {
        folio: 'SIG-2026-005',
        tipo: 'Emergencia médica',
        ubicacion: 'Cancha deportiva',
        fecha: '19/10/2026',
        hora: '09:12',
        reportante: 'Usuario de demostración',
        descripcion: 'Se solicita apoyo en el área deportiva.',
        prioridad: 'Media',
        estado: 'Cerrada',
        responsable: 'Personal médico',
        observaciones: 'Caso cerrado en la demostración.',
    },
]);
const pantalla = ref('panico');
const rolActual = ref(null);
const usuarioActualId = ref(null);

const usuarioActual = computed(() =>
    usuarios.value.find(
        usuario => usuario.id === usuarioActualId.value
    ) ?? null
);
const vistaAdmin = ref('dashboard');
const vistaResponsable = ref('alertas');

const incidenteSeleccionado = ref(null);
const origenDetalle = ref('incidentes');

const nombresRoles = {
    responsable: 'Responsable',
    administrador: 'Administrador',
};

const titulosAdmin = {
    dashboard: 'Dashboard',
    incidentes: 'Gestión de incidentes',
    usuarios: 'Usuarios y roles',
    reportes: 'Reportes y estadísticas',
    configuracion: 'Configuración del sistema',
};

function iniciarSesion(usuarioId) {
    const usuario = usuarios.value.find(
        cuenta => cuenta.id === usuarioId && cuenta.activo
    );

    if (!usuario) return;

    const roles = {
        Administrador: 'administrador',
        Responsable: 'responsable',
    };

    const rol = roles[usuario.rol];

    if (!rol) return;

    usuarioActualId.value = usuario.id;
    rolActual.value = rol;

    vistaAdmin.value = 'dashboard';
    vistaResponsable.value = 'alertas';
    incidenteSeleccionado.value = null;
    origenDetalle.value = 'incidentes';

    pantalla.value = rol === 'administrador'
        ? 'admin'
        : 'responsable';
}

function cambiarVistaResponsable(vista) {
    if (rolActual.value !== 'responsable') return;

    if (['alertas', 'mis-reportes'].includes(vista)) {
        vistaResponsable.value = vista;
    }
}


function cerrarSesion() {
    pantalla.value = 'panico';
    rolActual.value = null;
    vistaAdmin.value = 'dashboard';
    incidenteSeleccionado.value = null;
    origenDetalle.value = 'incidentes';
    vistaResponsable.value = 'alertas';
    usuarioActualId.value = null;
    solicitudDetalle.value = null;
    mensajeNotificacion.value = '';
}
watch(
    () => [
        usuarioActual.value?.activo,
        usuarioActual.value?.rol,
    ],
    () => {
        if (usuarioActualId.value === null) return;

        const usuario = usuarioActual.value;

        const rolEsperado = rolActual.value === 'administrador'
            ? 'Administrador'
            : 'Responsable';

        if (
            !usuario ||
            !usuario.activo ||
            usuario.rol !== rolEsperado
        ) {
            cerrarSesion();
        }
    }
);

function abrirBotonPanico() {
    pantalla.value = 'panico';
}

function volverAlPanel() {
    if (rolActual.value === 'administrador') {
        pantalla.value = 'admin';
    } else if (rolActual.value === 'responsable') {
        pantalla.value = 'responsable';
    }
}

function cambiarVistaAdmin(vista) {
    if (rolActual.value !== 'administrador') return;

    // Compatibilidad temporal con los accesos anteriores del Dashboard.
    const destino = ['alertas', 'historial'].includes(vista)
        ? 'incidentes'
        : vista;

    if (!Object.prototype.hasOwnProperty.call(titulosAdmin, destino)) {
        return;
    }

    vistaAdmin.value = destino;
    incidenteSeleccionado.value = null;
}

function abrirDetalleIncidente(incidente, origen = 'incidentes') {
    if (rolActual.value !== 'administrador' || !incidente) return;

    const registro = incidentes.value.find(
        item => item.folio === incidente.folio
    );

    if (!registro) return;

    incidenteSeleccionado.value = registro;

    origenDetalle.value = Object.prototype.hasOwnProperty.call(
        titulosAdmin,
        origen
    ) ? origen : 'incidentes';

    vistaAdmin.value = 'detalle-incidente';
}

function volverDeDetalle() {
    vistaAdmin.value = origenDetalle.value;
    incidenteSeleccionado.value = null;
}
function guardarSeguimientoIncidente(actualizado) {
    if (rolActual.value !== 'administrador' || !actualizado) return;

    const original = incidentes.value.find(
        item => item.folio === actualizado.folio
    );

    if (
        !original ||
        original.folio !== incidenteSeleccionado.value?.folio ||
        original.estado === 'Cerrada'
    ) {
        return;
    }

    const responsable = usuarios.value.find(
        usuario =>
            usuario.id === actualizado.responsableId &&
            usuario.rol === 'Responsable' &&
            usuario.activo
    );

    if (actualizado.responsableId && !responsable) return;
    const responsableAnteriorId = original.responsableId ?? null;

    const huboCambios =
    responsableAnteriorId !== (responsable?.id ?? null) ||
    original.prioridad !== actualizado.prioridad ||
    original.estado !== actualizado.estado ||
    original.observaciones !== actualizado.observaciones;
    if (original.estado !== actualizado.estado) {
    const ahora = new Date();
    const historial = original.seguimientos ?? [];

    original.seguimientos = [
        ...historial,
        {
            id: `admin-${ahora.getTime()}-${historial.length + 1}`,
            estado: actualizado.estado,
            fecha: new Intl.DateTimeFormat('es-MX', {
                timeZone: 'America/Cancun',
                dateStyle: 'short',
                timeStyle: 'short',
            }).format(ahora),
            autor: usuarioActual.value?.nombre ?? 'Administrador',
            autorId: usuarioActual.value?.id ?? null,
            observacion: actualizado.observaciones,
        },
    ];
}
    Object.assign(original, {
        responsableId: responsable?.id ?? null,
        responsable: responsable?.nombre ?? 'Sin asignar',
        prioridad: actualizado.prioridad,
        estado: actualizado.estado,
        observaciones: actualizado.observaciones,
    });
    if (huboCambios) {
    notificarCambio(original, responsableAnteriorId);
}
}
function registrarIncidente(reporte) {
    if (
        !reporte?.folio ||
        !reporte.tipo ||
        !reporte.ubicacion ||
        incidentes.value.some(item => item.folio === reporte.folio)
    ) {
        return;
    }

    incidentes.value.unshift({
        folio: reporte.folio,
        fecha: reporte.fecha,
        hora: reporte.hora,
        tipo: reporte.tipo,
        ubicacion: reporte.ubicacion,
        descripcion: reporte.descripcion,
        prioridad: 'Por evaluar',
        estado: 'Pendiente',
        responsable: 'Sin asignar',
        observaciones: '',
        reportanteId: usuarioActual.value?.activo
            ? usuarioActual.value.id
            : null,

        reportante: usuarioActual.value?.activo
            ? usuarioActual.value.nombre
            : 'Reporte público sin identificación',

        seguimientos: [],
        
    });
    notificarUsuarios(idsAdministradores(), {
    folio: reporte.folio,
    titulo: 'Nuevo incidente registrado',
    descripcion: 'Se recibió un nuevo reporte pendiente de revisión.',
    });
}
function guardarSeguimientoResponsable(cambio, responder) {
    const finalizar = resultado => {
        if (typeof responder === 'function') {
            responder(resultado);
        }
    };

    const usuario = usuarioActual.value;

    if (
        rolActual.value !== 'responsable' ||
        !usuario?.activo ||
        usuario.rol !== 'Responsable'
    ) {
        finalizar({
            ok: false,
            error: 'La cuenta no está habilitada para registrar seguimiento.',
        });
        return;
    }

    const incidente = incidentes.value.find(
        item => item.folio === cambio?.folio
    );

    if (!incidente || incidente.responsableId !== usuario.id) {
        finalizar({
            ok: false,
            error: 'Este incidente no está asignado a tu cuenta.',
        });
        return;
    }

    const orden = ['Pendiente', 'Recibida', 'En atención', 'Atendida'];
    const posicion = orden.indexOf(incidente.estado);

    // Permite conservar el estado o avanzar un paso.
    // Un caso atendido o cerrado ya no se modifica desde este panel.
    const permitidos = posicion >= 0 && posicion < 3
        ? orden.slice(posicion, posicion + 2)
        : [];

    if (!permitidos.includes(cambio.estado)) {
        finalizar({
            ok: false,
            error: 'El cambio de estado no está permitido.',
        });
        return;
    }

    if (
        incidente.estado === 'Pendiente' &&
        cambio.estado !== 'Recibida'
    ) {
        finalizar({
            ok: false,
            error: 'Primero confirma la recepción del incidente.',
        });
        return;
    }

    if (
        ['En atención', 'Atendida'].includes(cambio.estado) &&
        !['Alta', 'Media', 'Baja'].includes(incidente.prioridad)
    ) {
        finalizar({
            ok: false,
            error: 'El Administrador debe definir la prioridad del incidente.',
        });
        return;
    }

    const observacion = String(cambio.observacion ?? '').trim();

    if (!observacion || observacion.length > 1000) {
        finalizar({
            ok: false,
            error: 'Escribe una observación de hasta 1000 caracteres.',
        });
        return;
    }

    const ahora = new Date();

    const fecha = new Intl.DateTimeFormat('es-MX', {
        timeZone: 'America/Cancun',
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(ahora);

    const historial = incidente.seguimientos ?? [];

    incidente.seguimientos = [
        ...historial,
        {
            id: `${ahora.getTime()}-${historial.length + 1}`,
            estado: cambio.estado,
            fecha,
            autor: usuario.nombre,
            autorId: usuario.id,
            observacion,
        },
    ];

    if (
        incidente.estado === 'Pendiente' &&
        cambio.estado === 'Recibida'
    ) {
        incidente.recepcionConfirmadaPor = usuario.id;
        incidente.recepcionConfirmadaEn = ahora.toISOString();
    }

    incidente.estado = cambio.estado;
    incidente.observaciones = observacion;
    notificarCambio(incidente, incidente.responsableId);
    finalizar({ ok: true });
}
const notificaciones = ref([]);
const solicitudDetalle = ref(null);
const mensajeNotificacion = ref('');

let siguienteNotificacionId = 1;

const notificacionesActuales = computed(() => {
    if (!usuarioActual.value?.activo) return [];

    return notificaciones.value.filter(
        item => item.usuarioId === usuarioActual.value.id
    );
});

function notificarUsuarios(ids, datos) {
    const destinatarios = [...new Set(ids)];

    const fecha = new Intl.DateTimeFormat('es-MX', {
        timeZone: 'America/Cancun',
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date());

    for (const usuarioId of destinatarios) {
        const cuenta = usuarios.value.find(
            usuario => usuario.id === usuarioId && usuario.activo
        );

        // No genera un aviso de la propia acción para quien la realizó.
        if (!cuenta || cuenta.id === usuarioActualId.value) continue;

        notificaciones.value.unshift({
            id: siguienteNotificacionId++,
            usuarioId: cuenta.id,
            folio: datos.folio,
            titulo: datos.titulo,
            descripcion: datos.descripcion,
            fecha,
            leida: false,
        });
    }
}

function idsAdministradores() {
    return usuarios.value
        .filter(usuario =>
            usuario.activo && usuario.rol === 'Administrador'
        )
        .map(usuario => usuario.id);
}

function notificarCambio(incidente, responsableAnteriorId) {
    const nuevaAsignacion =
        incidente.responsableId &&
        incidente.responsableId !== responsableAnteriorId;

    if (nuevaAsignacion) {
        notificarUsuarios([incidente.responsableId], {
            folio: incidente.folio,
            titulo: 'Nuevo incidente asignado',
            descripcion: 'Se te asignó un caso. Abre el detalle para consultarlo.',
        });
    }

    const destinatarios = [
        ...idsAdministradores(),
        incidente.responsableId,
        incidente.reportanteId,
    ].filter(id => id !== null && id !== undefined);

    // Quien recibe el aviso de asignación no recibe además otro
    // aviso de seguimiento por la misma acción.
    notificarUsuarios(
        destinatarios.filter(id =>
            !nuevaAsignacion || id !== incidente.responsableId
        ),
        {
            folio: incidente.folio,
            titulo: 'Actualización de incidente',
            descripcion: `Se actualizó el caso. Estado: ${incidente.estado}.`,
        }
    );
}

function marcarNotificacionLeida(id) {
    const aviso = notificacionesActuales.value.find(
        item => item.id === id
    );

    if (aviso) aviso.leida = true;
}

function marcarTodasNotificaciones() {
    notificacionesActuales.value.forEach(item => {
        item.leida = true;
    });
}

function abrirNotificacion(id) {
    mensajeNotificacion.value = '';

    const aviso = notificacionesActuales.value.find(
        item => item.id === id
    );

    if (!aviso) return;

    const incidente = incidentes.value.find(
        item => item.folio === aviso.folio
    );

    if (!incidente) {
        mensajeNotificacion.value = 'El incidente ya no está disponible.';
        return;
    }

    if (rolActual.value === 'administrador') {
        marcarNotificacionLeida(id);
        abrirDetalleIncidente(incidente);
        return;
    }

    if (rolActual.value !== 'responsable') return;

    const esAsignado =
        incidente.responsableId === usuarioActualId.value;

    const esPropio =
        incidente.reportanteId === usuarioActualId.value;

    if (!esAsignado && !esPropio) {
        mensajeNotificacion.value =
            'Este caso ya no está asignado a tu cuenta ni pertenece a tus reportes.';
        return;
    }

    marcarNotificacionLeida(id);

    vistaResponsable.value = esAsignado
        ? 'alertas'
        : 'mis-reportes';

    // Un objeto nuevo permite abrir nuevamente el mismo folio.
    solicitudDetalle.value = { folio: incidente.folio };
}
</script>

<template>
    <!-- Inicio público: no requiere sesión -->
<BotonPanico v-if="pantalla === 'panico'"@registrar="registrarIncidente">
    <template #acceso>
        <button
            v-if="!rolActual"
            type="button"
            class="btn btn-outline-primary btn-sm"
            @click="pantalla = 'login'"
        >
            Iniciar sesión
        </button>

        <template v-else>
            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                @click="volverAlPanel"
            >
                Volver a mi panel
            </button>

            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                @click="cerrarSesion"
            >
                Cerrar sesión
            </button>
        </template>
    </template>
</BotonPanico>

    <!-- Acceso a los paneles -->
    <div v-else-if="pantalla === 'login'">
        <nav
            class="public-access"
            aria-label="Regresar al inicio"
        >
            <button
                type="button"
                class="btn btn-outline-danger btn-sm"
                @click="abrirBotonPanico"
            >
                Volver al botón de pánico
            </button>
        </nav>

    <Login :usuarios="usuarios" @iniciar="iniciarSesion"/>
    </div>

    <!-- Panel del Responsable -->
    <ResponsableLayout
        v-else-if="
            pantalla === 'responsable' &&
            rolActual === 'responsable'
        "
        :active-view="vistaResponsable"
        @navigate="cambiarVistaResponsable"
        @reportar="abrirBotonPanico"
        @logout="cerrarSesion"
    >
    <template #notificaciones>
    <Notificaciones
        :notificaciones="notificacionesActuales"
        @abrir="abrirNotificacion"
        @leer="marcarNotificacionLeida"
        @leer-todas="marcarTodasNotificaciones"
    />
    </template>

    <div
        v-if="mensajeNotificacion"
        class="alert alert-info small"
        role="status"
    >
        {{ mensajeNotificacion }}
    </div>
    <AlertasAsignadas
        v-if="vistaResponsable === 'alertas'"
        :incidentes="incidentes"
        :usuario="usuarioActual"
        :solicitud-detalle="solicitudDetalle"
        @guardar="guardarSeguimientoResponsable"
    />

    <MisReportes
        v-else-if="vistaResponsable === 'mis-reportes'"
        :incidentes="incidentes"
        :usuario="usuarioActual"
        :solicitud-detalle="solicitudDetalle"
    />
    </ResponsableLayout>

    <!-- Panel del Administrador -->
    <AdminLayout
        v-else-if="
            pantalla === 'admin' &&
            rolActual === 'administrador'
        "
        :active-view="
            vistaAdmin === 'detalle-incidente'
                ? 'incidentes'
                : vistaAdmin
        "
        @navigate="cambiarVistaAdmin"
        @logout="cerrarSesion"
    >
    <template #notificaciones>
    <Notificaciones
        :notificaciones="notificacionesActuales"
        @abrir="abrirNotificacion"
        @leer="marcarNotificacionLeida"
        @leer-todas="marcarTodasNotificaciones"
    />
        </template>

        <div
            v-if="mensajeNotificacion"
            class="alert alert-info small"
            role="status"
        >
            {{ mensajeNotificacion }}
        </div>
        <Dashboard
            v-if="vistaAdmin === 'dashboard'"
            :incidentes="incidentes"
            @navigate="cambiarVistaAdmin"
        />

        <KeepAlive>
            <Incidentes
                v-if="vistaAdmin === 'incidentes'"
                :incidentes="incidentes"
                @gestionar="abrirDetalleIncidente($event, 'incidentes')"
            />

            <Usuarios
                v-else-if="vistaAdmin === 'usuarios'"
                :usuarios="usuarios"
            />

            <Reportes
                v-else-if="vistaAdmin === 'reportes'"
                :incidentes="incidentes"
            />

            <Configuracion
                v-else-if="vistaAdmin === 'configuracion'"
            />
        </KeepAlive>

            <DetalleIncidente
                v-if="vistaAdmin === 'detalle-incidente'"
                :incidente="incidenteSeleccionado"
                :usuarios="usuarios"
                @guardar="guardarSeguimientoIncidente"
                @volver="volverDeDetalle"
            />

        <section
            v-if="![
                'dashboard',
                'incidentes',
                'detalle-incidente',
                'usuarios',
                'reportes',
                'configuracion',
            ].includes(vistaAdmin)"
            class="admin-coming-soon"
        >
            <div class="coming-soon-icon">▦</div>

            <h2>{{ titulosAdmin[vistaAdmin] }}</h2>

            <p>
                Esta sección está preparada para integrarse
                próximamente en SIGEI.
            </p>

            <button
                type="button"
                class="btn btn-primary"
                @click="cambiarVistaAdmin('dashboard')"
            >
                Volver al dashboard
            </button>
        </section>
    </AdminLayout>
</template> 
<style>
.admin-coming-soon {
    display: flex;
    min-height: 350px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    border: 1px solid #e8edf4;
    border-radius: 12px;
    background: white;
    text-align: center;
}

.coming-soon-icon {
    display: grid;
    width: 60px;
    height: 60px;
    place-items: center;
    margin-bottom: 18px;
    border-radius: 15px;
    background: #eaf1ff;
    color: #285bd1;
    font-size: 28px;
}

.admin-coming-soon h2 {
    color: #1b2b40;
    font-size: 21px;
    font-weight: 700;
}

.admin-coming-soon p {
    max-width: 380px;
    margin: 8px 0 22px;
    color: #8491a3;
    font-size: 13px;
}
.public-access {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
    padding: 12px 24px;
    background: #fff;
}
.panic-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    margin-left: auto;
}

.panic-header-actions .system-status {
    margin: 0;
}

@media (max-width: 576px) {
    .panic-header {
        flex-wrap: wrap;
        gap: 12px;
    }

    .panic-header-actions {
        width: 100%;
        justify-content: flex-start;
        margin-left: 0;
    }
}
</style>
