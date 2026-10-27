<script setup>
import { ref } from 'vue';
const emit = defineEmits(['registrar']);
const mostrarConfirmacion = ref(false);
const mostrarFormulario = ref(false);
const enviado = ref(false);

const tipo = ref('Emergencia médica');
const ubicacion = ref('');
const descripcion = ref('');
const comprobante = ref(null);

const tipos = [
    'Emergencia médica',
    'Situación de riesgo',
    'Altercado',
    'Persona intoxicada',
    'Otro incidente',
];

const ubicaciones = [
    'Edificio A · Planta baja',
    'Edificio A · Planta alta',
    'Edificio B · Aulas',
    'Edificio C · Pasillos',
    'Laboratorio',
    'Biblioteca',
    'Cafetería',
    'Estacionamiento',
    'Cancha deportiva',
    'Otro lugar',
];

const otroLugar = ref('');

function abrirConfirmacion() {
    mostrarConfirmacion.value = true;
}

function cancelarConfirmacion() {
    mostrarConfirmacion.value = false;
}

function continuar() {
    mostrarConfirmacion.value = false;
    mostrarFormulario.value = true;
}

function cancelarFormulario() {
    mostrarFormulario.value = false;
}

function enviarAlerta() {
    if (enviado.value || !ubicacion.value) return;

    const lugar = ubicacion.value === 'Otro lugar'
        ? otroLugar.value.trim()
        : ubicacion.value;

    if (!lugar || !tipos.includes(tipo.value)) return;

    const ahora = new Date();

    const identificador = globalThis.crypto?.randomUUID
        ? globalThis.crypto.randomUUID()
        : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

    const fecha = new Intl.DateTimeFormat('es-MX', {
        timeZone: 'America/Cancun',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(ahora);

    const hora = new Intl.DateTimeFormat('es-MX', {
        timeZone: 'America/Cancun',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(ahora);

    const nuevoIncidente = {
        folio: `DEMO-${identificador.toUpperCase()}`,
        fecha,
        hora,
        tipo: tipo.value,
        ubicacion: lugar,
        descripcion: descripcion.value.trim() || 'Sin descripción adicional',
        prioridad: 'Por evaluar',
        estado: 'Pendiente',
        responsable: 'Sin asignar',
        observaciones: '',
    };

    // App.vue incorpora el reporte a los registros compartidos.
    emit('registrar', nuevoIncidente);

    comprobante.value = {
        ...nuevoIncidente,
        fecha: `${fecha} · ${hora}`,
    };

    enviado.value = true;
    mostrarFormulario.value = false;
}
function reiniciar() {
    mostrarConfirmacion.value = false;
    mostrarFormulario.value = false;
    enviado.value = false;

    tipo.value = 'Emergencia médica';
    ubicacion.value = '';
    otroLugar.value = '';
    descripcion.value = '';
    comprobante.value = null;
}
</script>

<template>
    <div class="panic-page">
        <header class="panic-header">
            <div class="brand-mark">+</div>

            <div>
                <h1>SIGEI</h1>
                <p>Sistema de Gestión de Emergencias</p>
            </div>

            <div class="panic-header-actions">
                <span class="system-status">
                    <i></i> Modo demostración
                </span>

                <slot name="acceso" />
            </div>
        </header>

        <section class="panic-content">
            <template v-if="!enviado">
                <div class="intro">
                    <span class="eyebrow">CENTRO DE EMERGENCIAS</span>

                    <h2>¿Necesitas ayuda inmediata?</h2>

                    <p>
                        Reporta una emergencia dentro de la institución.
                        No necesitas iniciar sesión.
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
                    <span aria-hidden="true">⚠</span>

                    <p>
                        <strong>Importante</strong>
                        <br />
                        Si existe peligro inmediato, busca un lugar seguro
                        y contacta a los servicios de emergencia
                        correspondientes.
                    </p>
                </div>
            </template>

            <section v-else-if="comprobante" class="result-card">
                <div class="result-icon" aria-hidden="true">✓</div>

                <h2>Reporte registrado en la demostración</h2>

                <p>
                    El reporte ya aparece en el panel de esta demostración.
                    No se ha enviado ninguna notificación real.
                    Los datos se pierden al recargar la página.
                </p>

                <div class="result-details">
                    <p>
                        <strong>Folio de demostración:</strong>
                        {{ comprobante.folio }}
                    </p>

                    <p>
                        <strong>Fecha:</strong>
                        {{ comprobante.fecha }}
                    </p>

                    <p>
                        <strong>Tipo:</strong>
                        {{ comprobante.tipo }}
                    </p>

                    <p>
                        <strong>Ubicación:</strong>
                        {{ comprobante.ubicacion }}
                    </p>

                    <p>
                        <strong>Descripción:</strong>
                        {{ comprobante.descripcion }}
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-danger w-100"
                    @click="reiniciar"
                >
                    Volver al inicio
                </button>
            </section>
        </section>

        <footer class="panic-footer">
            SIGEI · Prototipo de botón de pánico
            <span>Interfaz de demostración</span>
        </footer>

        <!-- Confirmación breve -->
        <div
            v-if="mostrarConfirmacion"
            class="modal-backdrop-custom"
            @click.self="cancelarConfirmacion"
        >
            <section
                class="dialog-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
            >
                <div class="dialog-icon">!</div>

                <h2 id="confirm-title">¿Deseas reportar una emergencia?</h2>

                <p>
                    Indica el tipo de emergencia y dónde ocurre.
                </p>

                <div class="dialog-actions">
                    <button
                        type="button"
                        class="btn btn-light"
                        @click="cancelarConfirmacion"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-danger"
                        @click="continuar"
                    >
                        Sí, continuar
                    </button>
                </div>
            </section>
        </div>

        <!-- Formulario único -->
        <div
            v-if="mostrarFormulario"
            class="modal-backdrop-custom"
        >
            <section
                class="dialog-card form-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="report-title"
            >
                <span class="eyebrow">NUEVO REPORTE</span>

                <h2 id="report-title">Información de la emergencia</h2>

                <p>
                    Selecciona qué ocurre y su ubicación.
                </p>

                <form @submit.prevent="enviarAlerta">
                    <label for="tipo">Tipo de emergencia</label>

                    <select
                        id="tipo"
                        v-model="tipo"
                        class="form-select"
                    >
                        <option v-for="opcion in tipos" :key="opcion">
                            {{ opcion }}
                        </option>
                    </select>

                    <label for="ubicacion">Ubicación *</label>

                    <select
                        id="ubicacion"
                        v-model="ubicacion"
                        class="form-select"
                        required
                    >
                        <option disabled value="">
                            Selecciona una ubicación
                        </option>

                        <option v-for="lugar in ubicaciones" :key="lugar">
                            {{ lugar }}
                        </option>
                    </select>

                    <template v-if="ubicacion === 'Otro lugar'">
                        <label for="otro-lugar">Indica el lugar *</label>

                        <input
                            id="otro-lugar"
                            v-model="otroLugar"
                            type="text"
                            class="form-control"
                            maxlength="150"
                            pattern=".*\S.*"
                            placeholder="Escribe dónde ocurre la emergencia..."
                            required
                        />
                    </template>

                    <label for="descripcion">
                        Descripción adicional (opcional)
                    </label>

                    <textarea
                        id="descripcion"
                        v-model="descripcion"
                        class="form-control"
                        rows="3"
                        maxlength="500"
                        placeholder="Ej. Aula 203, persona que requiere apoyo..."
                    ></textarea>

                    <small class="d-block text-secondary mt-3">
                        Demostración: no se enviará una alerta real.
                    </small>

                    <div class="dialog-actions">
                        <button
                            type="button"
                            class="btn btn-light"
                            @click="cancelarFormulario"
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
    </div>
</template>

<style scoped>
.form-card {
    width: min(100%, 560px);
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
}

.result-details {
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

@media (max-width: 576px) {
    .form-card .dialog-actions {
        display: flex;
        flex-direction: column-reverse;
        gap: 10px;
    }

    .form-card .dialog-actions .btn {
        width: 100%;
    }
}
</style>