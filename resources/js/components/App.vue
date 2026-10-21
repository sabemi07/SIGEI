
<script setup>
import { ref } from 'vue';

import Login from './Login.vue';
import BotonPanico from './BotonPanico.vue';
import ResponsableLayout from './responsable/ResponsableLayout.vue';
import AlertasAsignadas from './responsable/AlertasAsignadas.vue';
import AdminLayout from './admin/AdminLayout.vue';
import Dashboard from './admin/Dashboard.vue';
import Alertas from './admin/Alertas.vue';
import Incidentes from './admin/Incidentes.vue';
import DetalleIncidente from './admin/DetalleIncidente.vue';
import MisReportes from './responsable/MisReportes.vue';
import UsuarioLayout from './usuario/UsuarioLayout.vue';
const pantalla = ref('login');
const rolActual = ref(null);
const vistaAdmin = ref('dashboard');
const vistaResponsable = ref('alertas');
const vistaUsuario = ref('reportar');

const incidenteSeleccionado = ref(null);
const origenDetalle = ref('alertas');

const nombresRoles = {
    usuario: 'Usuario',
    responsable: 'Responsable',
    administrador: 'Administrador',
};

const titulosAdmin = {
    alertas: 'Gestión de alertas',
    incidentes: 'Gestión de incidentes',
    historial: 'Historial de incidentes',
    usuarios: 'Usuarios y roles',
    reportes: 'Reportes y estadísticas',
    configuracion: 'Configuración del sistema',
};

function iniciarSesion(rol = 'usuario') {
    if (!Object.prototype.hasOwnProperty.call(nombresRoles, rol)) {
        return;
    }
    
    rolActual.value = rol;
    vistaAdmin.value = 'dashboard';
    vistaResponsable.value = 'alertas';
    incidenteSeleccionado.value = null;
    origenDetalle.value = 'alertas';
    vistaUsuario.value = 'reportar';

    if (rol === 'administrador') {
        pantalla.value = 'admin';
    } else if (rol === 'responsable') {
        pantalla.value = 'responsable';
    } else {
        pantalla.value = 'usuario';
    }
}

function cambiarVistaResponsable(vista) {
    if (rolActual.value !== 'responsable') return;

    if (['alertas', 'mis-reportes'].includes(vista)) {
        vistaResponsable.value = vista;
    }
}


function cerrarSesion() {
    pantalla.value = 'login';
    rolActual.value = null;
    vistaAdmin.value = 'dashboard';
    incidenteSeleccionado.value = null;
    origenDetalle.value = 'alertas';
    vistaUsuario.value = 'reportar';
    vistaResponsable.value = 'alertas';
}

function abrirBotonPanico() {
    if (!rolActual.value) return;

    if (rolActual.value === 'usuario') {
        vistaUsuario.value = 'reportar';
        pantalla.value = 'usuario';
    } else {
        pantalla.value = 'panico';
    }
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

    vistaAdmin.value = vista;
}

function abrirDetalleIncidente(incidente, origen = 'alertas') {
    if (rolActual.value !== 'administrador') return;

    incidenteSeleccionado.value = incidente;
    origenDetalle.value = origen;
    vistaAdmin.value = 'detalle-incidente';
}

function volverDeDetalle() {
    vistaAdmin.value = origenDetalle.value;
    incidenteSeleccionado.value = null;
}

function cambiarVistaUsuario(vista) {
    if (rolActual.value !== 'usuario') return;

    if (['reportar', 'mis-reportes'].includes(vista)) {
        vistaUsuario.value = vista;
    }
}
</script>

<template>
    <Login
        v-if="pantalla === 'login'"
        @iniciar="iniciarSesion"
    />

    <template v-else>
        <!-- Controles temporales para recorrer el diseño por roles -->
        <header
            class="demo-session-bar d-flex flex-wrap align-items-center
                   justify-content-between gap-3 px-3 py-2 border-bottom bg-white"
        >
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-bold">SIGEI</span>

                <span class="badge text-bg-primary">
                    {{ nombresRoles[rolActual] }}
                </span>

                <small class="text-secondary">
                    Modo demostración
                </small>
            </div>

            <nav
                class="d-flex flex-wrap gap-2"
                aria-label="Navegación de demostración"
            >
                <button
                    v-if="
                        pantalla !== 'panico' &&
                        !(pantalla === 'usuario' && vistaUsuario === 'reportar')
                    "
                    type="button"
                    class="btn btn-outline-danger btn-sm"
                    @click="abrirBotonPanico"
                >
                    Reportar emergencia
                </button>

                <button
                    v-if="
                        pantalla === 'panico' &&
                        (
                            rolActual === 'administrador' ||
                            rolActual === 'responsable'
                        )
                    "
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    @click="volverAlPanel"
                >
                    Volver al panel
                </button>

                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="cerrarSesion"
                >
                    Cerrar sesión
                </button>
            </nav>
        </header>

            <UsuarioLayout
        v-if="
            pantalla === 'usuario' &&
            rolActual === 'usuario'
        "
        :active-view="vistaUsuario"
        @navigate="cambiarVistaUsuario"
        @logout="cerrarSesion"
    >
        <BotonPanico
            v-if="vistaUsuario === 'reportar'"
        />

        <section v-else class="admin-coming-soon">
            <div class="coming-soon-icon">▤</div>

            <h2>Mis reportes</h2>

            <p>
                Aquí podrás consultar el estado y los avances
                de las emergencias que hayas reportado.
            </p>

            <span class="badge text-bg-light border">
                Contenido pendiente de diseño
            </span>
        </section>
    </UsuarioLayout>

            <BotonPanico
                v-else-if="
                    pantalla === 'panico' &&
                    (
                        rolActual === 'administrador' ||
                        rolActual === 'responsable'
                    )
                "
            />

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

        <AdminLayout
            v-else-if="
                pantalla === 'admin' &&
                rolActual === 'administrador'
            "
            :active-view="vistaAdmin"
            @navigate="cambiarVistaAdmin"
            @logout="cerrarSesion"
        >
            <Dashboard
                v-if="vistaAdmin === 'dashboard'"
                @navigate="cambiarVistaAdmin"
            />

            <Alertas
                v-else-if="vistaAdmin === 'alertas'"
                @gestionar="abrirDetalleIncidente($event, 'alertas')"
            />

            <Incidentes
                v-else-if="vistaAdmin === 'incidentes'"
                @gestionar="abrirDetalleIncidente($event, 'incidentes')"
            />

            <DetalleIncidente
                v-else-if="vistaAdmin === 'detalle-incidente'"
                :incidente="incidenteSeleccionado"
                @volver="volverDeDetalle"
            />

            <section v-else class="admin-coming-soon">
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
</style>
