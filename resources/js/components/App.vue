
<script setup>
import { ref } from 'vue';

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
const pantalla = ref('panico');
const rolActual = ref(null);
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

function iniciarSesion(rol = 'responsable') {
    if (!Object.prototype.hasOwnProperty.call(nombresRoles, rol)) {
        return;
    }

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
}

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

    incidenteSeleccionado.value = incidente;
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
    if (rolActual.value !== 'administrador') return;

    const original = incidenteSeleccionado.value;

    if (
        !original ||
        original.folio !== actualizado.folio ||
        original.estado === 'Cerrada'
    ) {
        return;
    }

    Object.assign(original, {
        responsable: actualizado.responsable,
        estado: actualizado.estado,
        observaciones: actualizado.observaciones,
    });
}
</script>

<template>
    <!-- Inicio público: no requiere sesión -->
<BotonPanico v-if="pantalla === 'panico'">
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

        <Login @iniciar="iniciarSesion" />
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
        <AlertasAsignadas
            v-if="vistaResponsable === 'alertas'"
        />

        <MisReportes
            v-else-if="vistaResponsable === 'mis-reportes'"
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
        <Dashboard
            v-if="vistaAdmin === 'dashboard'"
            @navigate="cambiarVistaAdmin"
        />

        <KeepAlive>
            <Incidentes
                v-if="vistaAdmin === 'incidentes'"
                @gestionar="abrirDetalleIncidente($event, 'incidentes')"
            />

            <Usuarios
                v-else-if="vistaAdmin === 'usuarios'"
            />

            <Reportes
                v-else-if="vistaAdmin === 'reportes'"
            />

            <Configuracion
                v-else-if="vistaAdmin === 'configuracion'"
            />
        </KeepAlive>

        <DetalleIncidente
            v-if="vistaAdmin === 'detalle-incidente'"
            :incidente="incidenteSeleccionado"
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
