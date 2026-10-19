
<script setup>
import { ref } from 'vue';

const mostrarConfirmacion = ref(false);
const mostrarFormulario = ref(false);
const enviado = ref(false);

const tipo = ref('Emergencia médica');
const ubicacion = ref('');
const descripcion = ref('');

const tipos = [
    'Emergencia médica',
    'Situación de riesgo',
    'Altercado',
    'Persona intoxicada',
    'Otro incidente'
];

function abrirConfirmacion() {
    mostrarConfirmacion.value = true;
}

function cancelar() {
    mostrarConfirmacion.value = false;
}

function continuar() {
    mostrarConfirmacion.value = false;
    mostrarFormulario.value = true;
}

function enviarAlerta() {
    if (!ubicacion.value.trim()) {
        alert('Selecciona la ubicación de la emergencia.');
        return;
    }

    // Prototipo visual: todavía no envía una alerta real.
    enviado.value = true;
    mostrarFormulario.value = false;
}

function reiniciar() {
    mostrarConfirmacion.value = false;
    mostrarFormulario.value = false;
    enviado.value = false;
    tipo.value = 'Emergencia médica';
    ubicacion.value = '';
    descripcion.value = '';
}
</script>

<template>
    <main class="panic-page">
        <header class="panic-header">
            <div class="brand-mark">+</div>

            <div>
                <h1>SIGEI</h1>
                <p>Sistema de Gestión de Emergencias</p>
            </div>

            <span class="system-status">
                <i></i> Sistema disponible
            </span>
        </header>

        <section class="panic-content">
            <template v-if="!enviado">
                <div class="intro">
                    <span class="eyebrow">CENTRO DE EMERGENCIAS</span>
                    <h2>¿Necesitas ayuda inmediata?</h2>
                    <p>
                        Activa una alerta para informar al personal
                        responsable de una situación de emergencia.
                    </p>
                </div>

                <div class="panic-card">
                    <button
                        class="panic-button"
                        type="button"
                        @click="abrirConfirmacion"
                        aria-label="Solicitar ayuda de emergencia"
                    >
                        <span class="panic-icon">!</span>
                        <strong>BOTÓN DE PÁNICO</strong>
                        <small>PRESIONA PARA SOLICITAR AYUDA</small>
                    </button>

                    <div class="panic-info">
                        <span class="info-icon">i</span>
                        <p>
                            Utiliza este botón únicamente cuando exista
                            una situación que requiera atención urgente.
                        </p>
                    </div>
                </div>

                <div class="emergency-note">
                    <span>⚠</span>
                    <p>
                        <strong>Importante</strong>
                        <br>
                        Si existe peligro inmediato, busca un lugar seguro
                        y contacta a los servicios de emergencia
                        correspondientes.
                    </p>
                </div>
            </template>

            <section v-else class="result-card">
                <div class="result-icon">✓</div>
                <h2>Reporte preparado</h2>
                <p>
                    El reporte se registró únicamente en esta demostración.
                    No se ha enviado ninguna notificación real.
                </p>

                <div class="result-details">
                    <p><strong>Tipo:</strong> {{ tipo }}</p>
                    <p><strong>Ubicación:</strong> {{ ubicacion }}</p>
                    <p>
                        <strong>Descripción:</strong>
                        {{ descripcion || 'Sin descripción adicional' }}
                    </p>
                </div>

                <button class="btn btn-danger w-100"
                        @click="reiniciar">
                    Volver al inicio
                </button>
            </section>
        </section>

        <footer class="panic-footer">
            SIGEI · Prototipo de botón de pánico
            <span>Interfaz de demostración</span>
        </footer>

        <!-- Confirmación -->
        <div
            v-if="mostrarConfirmacion"
            class="modal-backdrop-custom"
            @click.self="cancelar"
        >
            <section
                class="dialog-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
            >
                <div class="dialog-icon">!</div>

                <h2 id="confirm-title">¿Confirmas la emergencia?</h2>

                <p>
                    Se abrirá el formulario para describir el incidente
                    y proporcionar su ubicación.
                </p>

                <div class="dialog-actions">
                    <button class="btn btn-light"
                            @click="cancelar">
                        Cancelar
                    </button>

                    <button class="btn btn-danger"
                            @click="continuar">
                        Sí, continuar
                    </button>
                </div>
            </section>
        </div>

        <!-- Formulario de alerta -->
        <div
            v-if="mostrarFormulario"
            class="modal-backdrop-custom"
        >
            <section class="dialog-card form-card">
                <span class="eyebrow">NUEVO REPORTE</span>
                <h2>Información de la emergencia</h2>

                <p>
                    Proporciona los datos necesarios para identificar
                    la situación.
                </p>

                <form @submit.prevent="enviarAlerta">
                    <label for="tipo">Tipo de incidente</label>
                    <select id="tipo"
                            class="form-select"
                            v-model="tipo">
                        <option v-for="t in tipos" :key="t">
                            {{ t }}
                        </option>
                    </select>

                    <label for="ubicacion">Ubicación *</label>
                    <select id="ubicacion"
                            class="form-select"
                            v-model="ubicacion"
                            required>
                        <option disabled value="">
                            Selecciona una ubicación
                        </option>
                        <option>Edificio A · Planta baja</option>
                        <option>Edificio A · Planta alta</option>
                        <option>Edificio B · Aulas</option>
                        <option>Edificio C · Pasillos</option>
                        <option>Laboratorio</option>
                        <option>Cafetería</option>
                        <option>Estacionamiento</option>
                        <option>Cancha deportiva</option>
                        <option>Otro lugar</option>
                    </select>

                    <label for="descripcion">
                        Descripción adicional
                    </label>
                    <textarea
                        id="descripcion"
                        class="form-control"
                        v-model="descripcion"
                        rows="3"
                        maxlength="500"
                        placeholder="Describe brevemente lo ocurrido..."
                    ></textarea>

                    <div class="dialog-actions">
                        <button
                            type="button"
                            class="btn btn-light"
                            @click="mostrarFormulario = false"
                        >
                            Cancelar
                        </button>

                        <button type="submit" class="btn btn-danger">
                            Registrar reporte
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</template>