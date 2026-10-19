
<script setup>
import { ref } from 'vue';

import Login from './Login.vue';
import BotonPanico from './BotonPanico.vue';

const pantalla = ref('login');

function iniciarSesion() {
    pantalla.value = 'panico';
}

function entrarAdmin() {
    pantalla.value = 'admin';
}

function cerrarPanelAdmin() {
    pantalla.value = 'login';
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

    <main
        v-else-if="pantalla === 'admin'"
        class="container py-5"
    >
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>SIGEI</h1>
                <p>Panel administrativo</p>
            </div>

            <button
                class="btn btn-outline-secondary"
                @click="cerrarPanelAdmin"
            >
                Volver al login
            </button>
        </div>

        <div class="alert alert-info">
            Bienvenido al panel administrativo temporal.
            Aquí desarrollaremos la gestión de alertas e incidentes.
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5>Alertas activas</h5>
                        <h2>3</h2>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5>Incidentes pendientes</h5>
                        <h2>2</h2>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5>Incidentes atendidos</h5>
                        <h2>8</h2>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
