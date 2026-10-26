
<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    incidente: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['volver', 'guardar']);

const incidenteSeleccionado = ref(null);
const mensajeGuardado = ref(false);
const error = ref('');

const estados = [
    'Pendiente',
    'Recibida',
    'En atención',
    'Atendida',
    'Cerrada',
];

watch(
    () => props.incidente,
    (nuevoIncidente) => {
        incidenteSeleccionado.value = nuevoIncidente
            ? {
                ...nuevoIncidente,
                responsable: nuevoIncidente.responsable ?? 'Sin asignar',
                observaciones: nuevoIncidente.observaciones ?? '',
            }
            : null;

        mensajeGuardado.value = false;
        error.value = '';
    },
    { immediate: true }
);

const soloLectura = computed(
    () => props.incidente?.estado === 'Cerrada'
);

const estadosDisponibles = computed(() => {
    const actual = props.incidente?.estado;
    const posicion = estados.indexOf(actual);

    if (posicion === -1) return [];

    // Permite conservar el estado o avanzar al siguiente.
    return estados.slice(posicion, posicion + 2);
});

function marcarCambio() {
    mensajeGuardado.value = false;
    error.value = '';
}

function guardarSeguimiento() {
    const incidente = incidenteSeleccionado.value;

    if (!incidente || soloLectura.value) return;

    mensajeGuardado.value = false;
    error.value = '';

    if (!estadosDisponibles.value.includes(incidente.estado)) {
        error.value = 'Selecciona un estado válido.';
        return;
    }

    const responsable = incidente.responsable.trim();
    const observaciones = incidente.observaciones.trim();

    const requiereResponsable = [
        'En atención',
        'Atendida',
        'Cerrada',
    ].includes(incidente.estado);

    if (
        requiereResponsable &&
        (!responsable || responsable.toLowerCase() === 'sin asignar')
    ) {
        error.value = 'Asigna un responsable antes de guardar este estado.';
        return;
    }

    if (
        ['Atendida', 'Cerrada'].includes(incidente.estado) &&
        !observaciones
    ) {
        error.value = 'Describe las acciones realizadas antes de atender o cerrar el caso.';
        return;
    }

    const actualizado = {
        ...incidente,
        responsable: responsable || 'Sin asignar',
        observaciones,
    };

    emit('guardar', actualizado);

    incidenteSeleccionado.value = { ...actualizado };
    mensajeGuardado.value = true;
}

function cerrarDetalle() {
    emit('volver');
}
</script>

<template>
    <div class="incidents-page">
        <div class="demo-notice">
            <span class="notice-icon">i</span>
            <span>
                <strong>Modo demostración:</strong>
                los incidentes son ficticios. El seguimiento no se guarda
                en una base de datos.
            </span>
        </div>

        <div
            v-if="incidenteSeleccionado"
            class="modal-overlay"
            @click.self="cerrarDetalle"
        >
            <section
                class="incident-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="incident-detail-title"
            >
                <header class="modal-header">
                    <div>
                        <span class="eyebrow">GESTIÓN DEL INCIDENTE</span>
                        <h2 id="incident-detail-title">
                            {{ incidenteSeleccionado.folio }}
                        </h2>
                    </div>

                    <button
                        class="close-button"
                        aria-label="Cerrar"
                        @click="cerrarDetalle"
                    >
                        ×
                    </button>
                </header>

                <div class="modal-body">
                    <div class="incident-intro">
                        <div class="intro-icon">!</div>
                        <div>
                            <h3>{{ incidenteSeleccionado.tipo }}</h3>
                            <p>
                                {{ incidenteSeleccionado.fecha }}
                                · {{ incidenteSeleccionado.hora }}
                            </p>
                        </div>
                    </div>

                    <h4 class="section-label">Información del reporte</h4>

                    <div class="info-grid">
                        <div class="info-field">
                            <label>Ubicación</label>
                            <strong>{{ incidenteSeleccionado.ubicacion }}</strong>
                        </div>

                        <div class="info-field">
                            <label>Persona que reporta</label>
                            <strong>{{ incidenteSeleccionado.reportante }}</strong>
                        </div>

                        <div class="info-field">
                            <label>Prioridad</label>
                            <strong>{{ incidenteSeleccionado.prioridad }}</strong>
                        </div>

                        <div class="info-field">
                            <label>Estado actual</label>
                            <strong>{{ props.incidente?.estado }}</strong>
                        </div>
                    </div>

                    <div class="description-box">
                        <label>Descripción</label>
                        <p>{{ incidenteSeleccionado.descripcion }}</p>
                    </div>

                    <div class="form-field">
                        <label for="responsable">Responsable de atención</label>
                        <input
                            id="responsable"
                            v-model="incidenteSeleccionado.responsable"
                            type="text"
                            maxlength="150"
                            :disabled="soloLectura"
                            placeholder="Nombre o área responsable"
                            @input="marcarCambio"
                        />
                    </div>

                    <div class="form-field">
                        <label for="estado-incidente">Estado del incidente</label>
                        <select
                            id="estado-incidente"
                            v-model="incidenteSeleccionado.estado"
                            :disabled="soloLectura"
                            @change="marcarCambio"
                        >
                            <option
                                v-for="estado in estadosDisponibles"
                                :key="estado"
                                :value="estado"
                            >
                                {{ estado }}
                            </option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="observaciones">Observaciones de seguimiento</label>
                        <textarea
                            id="observaciones"
                            v-model="incidenteSeleccionado.observaciones"
                            rows="4"
                            maxlength="2000"
                            :disabled="soloLectura"
                            placeholder="Describe las acciones realizadas o pendientes..."
                            @input="marcarCambio"
                        ></textarea>
                    </div>
                    <div v-if="soloLectura" class="readonly-message">
                        Este incidente está cerrado y solo se puede consultar.
                    </div>

                    <div v-if="error" class="error-message" role="alert">
                        {{ error }}
                    </div>
                    <div
                        v-if="mensajeGuardado"
                        class="success-message"
                        role="status"
                    >
                        ✓ Seguimiento actualizado durante esta sesión de demostración.
                    </div>
                </div>

                <footer class="modal-footer">
                    <button
                        v-if="!soloLectura"
                        type="button"
                        class="save-button"
                        @click="guardarSeguimiento"
                    >
                        Guardar seguimiento
                    </button>

                    <button class="save-button" @click="guardarSeguimiento">
                        Guardar seguimiento
                    </button>
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.incidents-page {
    display: grid;
    gap: 22px;
    color: #26364a;
}

.demo-notice {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 16px;
    border: 1px solid #cbdcfb;
    border-radius: 9px;
    background: #eef5ff;
    color: #415b80;
    font-size: 12px;
    line-height: 1.6;
}

.notice-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #d7e6ff;
    color: #285bd1;
    font-weight: 800;
}

.incident-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.stat-card {
    display: grid;
    gap: 12px;
    padding: 20px;
    border: 1px solid #e6ebf2;
    border-radius: 11px;
    background: white;
}

.stat-card span {
    color: #718096;
    font-size: 11px;
}

.stat-card strong {
    color: #1b2b40;
    font-size: 29px;
}

.text-red {
    color: #d93636 !important;
}

.text-blue {
    color: #285bd1 !important;
}

.text-green {
    color: #198754 !important;
}

.incidents-panel {
    overflow: hidden;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px;
}

.panel-heading h2 {
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 750;
}

.panel-heading p {
    margin: 0;
    color: #8491a3;
    font-size: 12px;
}

.result-count {
    padding: 7px 10px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    white-space: nowrap;
}

.filters {
    display: flex;
    gap: 12px;
    padding: 0 22px 20px;
}

.filters input,
.filters select,
.form-field input,
.form-field select,
.form-field textarea {
    min-width: 0;
    padding: 11px 12px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    outline: none;
    background: white;
    color: #26364a;
    font: inherit;
    font-size: 12px;
}

.filters input {
    flex: 1;
}

.filters input:focus,
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #8baef5;
    box-shadow: 0 0 0 3px rgb(37 99 235 / 9%);
}

.incidents-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.incidents-table th {
    padding: 13px 16px;
    background: #f8fafc;
    color: #7f8da0;
    text-align: left;
    font-size: 10px;
}

.incidents-table td {
    padding: 15px 16px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 11px;
}

.incidents-table td small {
    display: block;
    margin-top: 5px;
    color: #94a0af;
    font-size: 10px;
}

.folio {
    color: #285bd1;
    font-weight: 750;
}

.incident-title {
    color: #26364a;
}

.status-pill {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.status-pendiente {
    background: #fff0f0;
    color: #cf3434;
}

.status-recibida {
    background: #fff5df;
    color: #a76c08;
}

.status-en-atención {
    background: #eaf1ff;
    color: #285bd1;
}

.status-atendida,
.status-cerrada {
    background: #e8f8ef;
    color: #198754;
}

.detail-button {
    padding: 7px 10px;
    border: 1px solid #d5e1f8;
    border-radius: 6px;
    background: white;
    color: #285bd1;
    font-size: 10px;
    font-weight: 650;
}

.detail-button:hover {
    background: #eef5ff;
}

.empty-state {
    padding: 35px !important;
    text-align: center;
}

.modal-overlay {
    position: fixed;
    z-index: 1000;
    inset: 0;
    display: grid;
    place-items: center;
    overflow-y: auto;
    padding: 20px;
    background: rgb(15 23 42 / 55%);
}

.incident-modal {
    width: min(100%, 650px);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border-radius: 14px;
    background: white;
    box-shadow: 0 20px 70px rgb(0 0 0 / 20%);
}

.modal-header,
.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 24px;
    border-bottom: 1px solid #edf1f6;
}

.eyebrow {
    color: #8491a3;
    font-size: 9px;
    font-weight: 750;
    letter-spacing: 1px;
}

.modal-header h2 {
    margin: 6px 0 0;
    color: #1b2b40;
    font-size: 19px;
}

.close-button {
    width: 33px;
    height: 33px;
    border: 0;
    border-radius: 7px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 24px;
}

.modal-body {
    display: grid;
    gap: 20px;
    padding: 24px;
}

.incident-intro {
    display: flex;
    align-items: center;
    gap: 13px;
}

.intro-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #fff0f0;
    color: #d93636;
    font-size: 22px;
    font-weight: 800;
}

.incident-intro h3 {
    margin: 0;
    color: #26364a;
    font-size: 14px;
}

.incident-intro p {
    margin: 5px 0 0;
    color: #8491a3;
    font-size: 11px;
}

.section-label {
    margin: 0;
    color: #526177;
    font-size: 11px;
    font-weight: 750;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.info-field label,
.form-field label,
.description-box label {
    display: block;
    margin-bottom: 7px;
    color: #8491a3;
    font-size: 10px;
}

.info-field strong {
    color: #26364a;
    font-size: 12px;
}

.description-box {
    padding: 14px;
    border-radius: 8px;
    background: #f8fafc;
}

.description-box p {
    margin: 0;
    color: #536277;
    font-size: 12px;
    line-height: 1.7;
}

.form-field {
    display: grid;
    gap: 5px;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    box-sizing: border-box;
}

.form-field textarea {
    resize: vertical;
    line-height: 1.6;
}

.success-message {
    padding: 11px 13px;
    border: 1px solid #b9e6cc;
    border-radius: 7px;
    background: #e8f8ef;
    color: #198754;
    font-size: 11px;
}

.modal-footer {
    justify-content: flex-end;
    border-top: 1px solid #edf1f6;
    border-bottom: 0;
}

.cancel-button,
.save-button {
    padding: 10px 15px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 650;
}

.cancel-button {
    border: 1px solid #dfe6ef;
    background: white;
    color: #536277;
}

.save-button {
    border: 1px solid #2563eb;
    background: #2563eb;
    color: white;
}

.save-button:hover {
    background: #1d4ed8;
}

@media (max-width: 900px) {
    .incident-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .filters {
        flex-direction: column;
        padding: 0 15px 16px;
    }

    .panel-heading {
        align-items: flex-start;
        flex-direction: column;
        padding: 18px;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 17px;
    }
}
.error-message,
.readonly-message {
    padding: 11px 13px;
    border-radius: 7px;
    font-size: 12px;
    line-height: 1.6;
}

.error-message {
    border: 1px solid #fecaca;
    background: #fff0f0;
    color: #b91c1c;
}

.readonly-message {
    border: 1px solid #cbdcfb;
    background: #eef5ff;
    color: #415b80;
}

.form-field input:disabled,
.form-field select:disabled,
.form-field textarea:disabled {
    background: #f8fafc;
    color: #64748b;
    opacity: 1;
    cursor: default;
}
</style>
