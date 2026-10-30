<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { requireIncidentReceipt } from '../incidentClient';
const props = defineProps({
    demo: { type: Boolean, default: true },
    ready: { type: Boolean, default: true },
    configuracion: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(['registrar']);
const mostrarConfirmacion = ref(false);
const mostrarFormulario = ref(false);
const enviado = ref(false);

const tipo = ref('Emergencia médica');
const ubicacion = ref('');
const descripcion = ref('');
const comprobante = ref(null);
const enviando = ref(false);
const errorEnvio = ref('');
const solicitudId = ref(null);
let desmontado = false;
onBeforeUnmount(() => { desmontado = true; });

const tipos = computed(() => props.configuracion.tipos ?? []);
const ubicaciones = computed(() => props.configuracion.ubicaciones ?? []);

const otroLugar = ref('');
const requiereDetalle = computed(() => props.demo
    ? ubicacion.value === 'Otro lugar'
    : ubicaciones.value.find(item => item.id === ubicacion.value)?.requiereDetalle === true);
watch(
    tipos,
    opciones => {
        const valores = opciones.map(item => props.demo ? item : item.id);
        if (!valores.includes(tipo.value)) {
            tipo.value = valores[0] ?? '';
        }
    },
    { immediate: true }
);

watch(
    ubicaciones,
    opciones => {
        if (!opciones.map(item => props.demo ? item : item.id).includes(ubicacion.value)) {
            ubicacion.value = '';
            otroLugar.value = '';
        }
    },
    { immediate: true }
);

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
    if (enviando.value) return;
    mostrarFormulario.value = false;
    if (!props.demo) {
        solicitudId.value = null;
        errorEnvio.value = '';
    }
}

function enviarAlerta() {
    if (enviando.value) return;
    if (!props.demo) {
        errorEnvio.value = '';
        if (!props.ready) {
            errorEnvio.value = 'Todavía no se pudo cargar la conexión y los catálogos. Vuelve a intentarlo.';
            return;
        }
        if (!tipo.value || !ubicacion.value || (requiereDetalle.value && !otroLugar.value.trim())) return;
        if (!solicitudId.value) {
            if (typeof globalThis.crypto?.randomUUID !== 'function') {
                errorEnvio.value = 'Este navegador necesita una conexión segura para registrar el reporte.';
                return;
            }
            solicitudId.value = globalThis.crypto.randomUUID();
        }
        enviando.value = true;
        emit('registrar', {
            tipo_id: tipo.value,
            ubicacion_id: ubicacion.value,
            otro_lugar: requiereDetalle.value ? otroLugar.value.trim() : null,
            descripcion: descripcion.value.trim() || null,
            solicitud_id: solicitudId.value,
            website: '',
        }, resultado => {
            if (desmontado) return;
            enviando.value = false;
            if (!resultado?.ok) {
                errorEnvio.value = resultado?.error ?? 'No se pudo registrar. Conserva los datos y vuelve a intentarlo.';
                return;
            }
            try {
                const recibo = requireIncidentReceipt(resultado.data);
                comprobante.value = { ...recibo, fecha: `${recibo.fecha} · ${recibo.hora}` };
                enviado.value = true;
                mostrarFormulario.value = false;
            } catch (error) {
                errorEnvio.value = error.message;
            }
        });
        return;
    }
    if (enviado.value || !ubicacion.value) return;

    const lugar = ubicacion.value === 'Otro lugar'
        ? otroLugar.value.trim()
        : ubicacion.value;

        if (
            !lugar ||
            !tipos.value.includes(tipo.value) ||
            !ubicaciones.value.includes(ubicacion.value)
        ) {
            return;
        }

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
    if (enviando.value) return;
    mostrarConfirmacion.value = false;
    mostrarFormulario.value = false;
    enviado.value = false;

    tipo.value = props.demo ? (tipos.value[0] ?? '') : (tipos.value[0]?.id ?? '');
    ubicacion.value = '';
    otroLugar.value = '';
    descripcion.value = '';
    comprobante.value = null;
    solicitudId.value = null;
    errorEnvio.value = '';
}
</script>

<template>
    <div class="panic-page">
        <header class="panic-header">
            <div class="brand-mark">+</div>

            <div>
                <h1>{{ configuracion.siglas }}</h1>
                <p>{{ configuracion.institucion }}</p>
            </div>

            <div class="panic-header-actions">
                <span class="system-status">
                    <i></i> {{ demo ? 'Modo demostración' : 'Registro de emergencias' }}
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

                <h2>{{ demo ? 'Reporte registrado en la demostración' : 'Reporte registrado' }}</h2>

                <p v-if="demo">
                    El reporte ya aparece en el panel de esta demostración.
                    No se ha enviado ninguna notificación real.
                    Los datos se pierden al recargar la página.
                </p>
                <p v-else>Tu reporte quedó guardado para revisión. Conserva el folio. Registrar el reporte no significa que el personal haya confirmado su recepción.</p>

                <div class="result-details">
                    <p>
                        <strong>{{ demo ? 'Folio de demostración:' : 'Folio:' }}</strong>
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
            {{ configuracion.siglas }} · {{ demo ? 'Prototipo de botón de pánico' : 'Gestión de emergencias' }}
            <span>{{ demo ? 'Interfaz de demostración' : 'El reporte se guarda en el sistema. El personal asignado confirma la recepción del caso.' }}</span>
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
                    <fieldset :disabled="enviando" class="report-fields">
                    <label for="tipo">Tipo de emergencia</label>

                    <select
                        id="tipo"
                        v-model="tipo"
                        class="form-select"
                    >
                        <option v-for="opcion in tipos" :key="demo ? opcion : opcion.id" :value="demo ? opcion : opcion.id">
                            {{ demo ? opcion : opcion.nombre }}
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

                        <option v-for="lugar in ubicaciones" :key="demo ? lugar : lugar.id" :value="demo ? lugar : lugar.id">
                            {{ demo ? lugar : lugar.nombre }}
                        </option>
                    </select>

                    <template v-if="requiereDetalle">
                        <label for="otro-lugar">Indica el lugar *</label>

                        <input
                            id="otro-lugar"
                            v-model="otroLugar"
                            type="text"
                            class="form-control"
                            :maxlength="demo ? 150 : 255"
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
                        :maxlength="demo ? 500 : 1000"
                        placeholder="Ej. Aula 203, persona que requiere apoyo..."
                    ></textarea>

                    <small v-if="demo" class="d-block text-secondary mt-3">
                        Demostración: no se enviará una alerta real.
                    </small>
                    <small v-else class="d-block text-secondary mt-3">El reporte se guardará al confirmar. No necesitas una cuenta.</small>
                    <small v-if="!demo && !ready" class="d-block text-secondary mt-2" role="status">Comprobando la conexión y los catálogos. Si no cargan, vuelve al inicio y reintenta la conexión.</small>
                    <div v-if="errorEnvio" class="alert alert-danger mt-3" role="alert">{{ errorEnvio }}</div>

                    <div class="dialog-actions">
                        <button
                            type="button"
                            class="btn btn-light"
                            @click="cancelarFormulario"
                        >
                            Cancelar
                        </button>

                        <button type="submit" class="btn btn-danger" :disabled="!demo && (!ready || enviando)">
                            {{ enviando ? 'Registrando…' : 'Registrar reporte' }}
                        </button>
                    </div>
                    </fieldset>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
.report-fields { border: 0; padding: 0; margin: 0; min-width: 0; }
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
