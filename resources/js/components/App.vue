
<script setup>
import { ref } from 'vue';


import Incidentes from './admin/Incidentes.vue';
import Login from './Login.vue';
import BotonPanico from './BotonPanico.vue';

import AdminLayout from './admin/AdminLayout.vue';
import Dashboard from './admin/Dashboard.vue';

import Alertas from './admin/Alertas.vue';

import DetalleIncidente from './admin/DetalleIncidente.vue';

const incidenteSeleccionado = ref(null);
const origenDetalle = ref('alertas');

function abrirDetalleIncidente(incidente, origen = 'alertas') {
    incidenteSeleccionado.value = incidente;
    origenDetalle.value = origen;
    vistaAdmin.value = 'detalle-incidente';
}

function volverDeDetalle() {
    vistaAdmin.value = origenDetalle.value;
    incidenteSeleccionado.value = null;
}

function volverAAlertas() {
    vistaAdmin.value = 'alertas';
    incidenteSeleccionado.value = null;
}

const pantalla = ref('login');
const vistaAdmin = ref('dashboard');

const titulosAdmin = {
    alertas: 'Gestión de alertas',
    incidentes: 'Gestión de incidentes',
    historial: 'Historial de incidentes',
    usuarios: 'Usuarios y roles',
    reportes: 'Reportes y estadísticas',
    configuracion: 'Configuración del sistema',
};

function iniciarSesion() {
    pantalla.value = 'panico';
}

function entrarAdmin() {
    vistaAdmin.value = 'dashboard';
    pantalla.value = 'admin';
}

function cerrarPanelAdmin() {
    pantalla.value = 'login';
}

function cambiarVistaAdmin(vista) {
    vistaAdmin.value = vista;
}
</script>

<template>
    <Login
        v-if="pantalla === 'login'"
        @iniciar="iniciarSesion"
        @admin="entrarAdmin"
    />

    <BotonPanico
        v-else-if="pantalla === 'panico'"
    />

    <AdminLayout
        v-else-if="pantalla === 'admin'"
        :active-view="vistaAdmin"
        @navigate="cambiarVistaAdmin"
        @logout="cerrarPanelAdmin"
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
