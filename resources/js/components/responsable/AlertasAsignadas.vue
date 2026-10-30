<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    demo: { type: Boolean, default: true },
    saving: { type: Boolean, default: false },
    incidentes: {
        type: Array,
        default: () => [],
    },
    usuario: {
        type: Object,
        default: null,
    },
    solicitudDetalle: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['guardar']);

const busqueda = ref('');
const filtroEstado = ref('Todos');
const filtroPrioridad = ref('Todas');

const folioSeleccionado = ref(null);
const estadoSeguimiento = ref('Recibida');
const observacion = ref('');
const mensajeSeguimiento = ref('');
const errorSeguimiento = ref('');

const cuentaHabilitada = computed(() =>
    props.usuario?.activo &&
    props.usuario?.rol === 'Responsable'
);

const alertaSeleccionada = computed({
    get() {
        if (!cuentaHabilitada.value) return null;

        return props.incidentes.find(item =>
            item.folio === folioSeleccionado.value &&
            item.responsableId === props.usuario.id
        ) ?? null;
    },
    set(alerta) {
        folioSeleccionado.value = alerta?.folio ?? null;
    },
});

const asignadas = computed(() => {
    if (!cuentaHabilitada.value) return [];

    return props.incidentes.filter(item =>
        item.responsableId === props.usuario.id &&
        (!props.demo || ['Pendiente', 'Recibida', 'En atención'].includes(item.estado))
    );
});

const indicadores = computed(() => [
    {
        titulo: 'Alertas asignadas',
        total: asignadas.value.length,
        descripcion: 'Emergencias activas a tu cargo',
        simbolo: '!',
        color: 'rojo',
    },
    {
        titulo: 'Pendientes',
        total: asignadas.value.filter(
            item => props.demo ? item.estado === 'Pendiente' : !item.recepcionConfirmadaEn && !['Atendida', 'Cerrada'].includes(item.estado)
        ).length,
        descripcion: 'Pendientes de recepción',
        simbolo: '◷',
        color: 'ambar',
    },
    {
        titulo: 'En atención',
        total: asignadas.value.filter(
            item => item.estado === 'En atención'
        ).length,
        descripcion: 'Con atención en proceso',
        simbolo: '↗',
        color: 'azul',
    },
]);

const alertasFiltradas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return asignadas.value.filter(alerta => {
        const coincideTexto = [
            alerta.folio,
            alerta.tipo,
            alerta.ubicacion,
        ].some(valor => valor.toLowerCase().includes(texto));

        return coincideTexto &&
            (filtroEstado.value === 'Todos' ||
                alerta.estado === filtroEstado.value) &&
            (filtroPrioridad.value === 'Todas' ||
                alerta.prioridad === filtroPrioridad.value);
    });
});

const estadosDisponibles = computed(() => {
    const orden = ['Recibida', 'En atención', 'Atendida'];
    const posicion = orden.indexOf(alertaSeleccionada.value?.estado);

    return posicion >= 0 && posicion < 2
        ? orden.slice(posicion, posicion + 2)
        : [];
});

const historialSeleccionado = computed(
    () => alertaSeleccionada.value?.seguimientos ?? []
);

function clasePrioridad(prioridad) {
    return {
        Alta: 'etiqueta-roja',
        Media: 'etiqueta-ambar',
        Baja: 'etiqueta-verde',
    }[prioridad] ?? 'etiqueta-neutral';
}

function claseEstado(estado) {
    return {
        Pendiente: 'etiqueta-roja',
        Recibida: 'etiqueta-ambar',
        'En atención': 'etiqueta-azul',
        Atendida: 'etiqueta-verde',
        Cerrada: 'etiqueta-verde',
    }[estado] ?? 'etiqueta-neutral';
}

function limpiarFiltros() {
    busqueda.value = '';
    filtroEstado.value = 'Todos';
    filtroPrioridad.value = 'Todas';
}

function abrirDetalle(alerta) {
    alertaSeleccionada.value = alerta;
    estadoSeguimiento.value = alerta.estado;
    observacion.value = '';
    mensajeSeguimiento.value = '';
    errorSeguimiento.value = '';
}

function enviarCambio(estado, texto, accion = 'seguir') {
    if (props.saving) return;
    const alerta = alertaSeleccionada.value;

    if (!alerta) return;

    errorSeguimiento.value = '';
    mensajeSeguimiento.value = '';

    emit(
        'guardar',
        {
            folio: alerta.folio,
            version: alerta.version,
            accion,
            estado,
            observacion: texto,
        },
        resultado => {
            if (!resultado?.ok) {
                errorSeguimiento.value =
                    resultado?.error ?? 'No se pudo guardar el seguimiento.';
                return;
            }

            estadoSeguimiento.value = resultado.data?.estado ?? alerta.estado;
            observacion.value = '';

            mensajeSeguimiento.value = estado === 'Atendida'
                ? 'Atención registrada. El caso queda pendiente de cierre por el Administrador.'
                : estado === 'Recibida'
                  ? (props.demo ? 'Recepción confirmada en esta demostración.' : 'Recepción confirmada y guardada.')
                  : 'Seguimiento registrado y compartido con el Administrador.';
        }
    );
}

function confirmarRecepcion() {
    if (props.saving || !alertaSeleccionada.value) return;
    if (props.demo ? alertaSeleccionada.value.estado !== 'Pendiente' : !alertaSeleccionada.value.permisos?.confirmarRecepcion) return;

    enviarCambio(
        'Recibida',
        'El Responsable confirmó la recepción del incidente.',
        'recibir'
    );
}

function registrarSeguimiento() {
    if (!estadosDisponibles.value.includes(estadoSeguimiento.value)) {
        errorSeguimiento.value = 'Selecciona un estado válido.';
        return;
    }

    const texto = observacion.value.trim();

    if (!texto) {
        errorSeguimiento.value = 'Escribe una observación para registrar el seguimiento.';
        return;
    }

    enviarCambio(estadoSeguimiento.value, texto);
}
watch(
    () => props.solicitudDetalle,
    solicitud => {
        if (!solicitud || !cuentaHabilitada.value) return;

        const incidente = props.incidentes.find(item =>
            item.folio === solicitud.folio &&
            item.responsableId === props.usuario.id
        );

        if (incidente) abrirDetalle(incidente);
    },
    { immediate: true }
);
</script>

<template>
    <div class="asignadas-page">
        <div v-if="demo" class="demo-notice">
            <strong>Modo demostración:</strong>
            estás consultando las asignaciones de
            {{ usuario?.nombre || 'tu cuenta' }}.
            El seguimiento se comparte con el Administrador durante esta sesión.
            Los cambios se pierden al recargar.
        </div>

        <section class="summary-grid" aria-label="Resumen de alertas asignadas">
            <article
                v-for="indicador in indicadores"
                :key="indicador.titulo"
                class="summary-card"
            >
                <span class="summary-label">{{ indicador.titulo }}</span>
                <strong>{{ indicador.total }}</strong>
                <span class="summary-description">
                    {{ indicador.descripcion }}
                </span>

                <span
                    class="summary-symbol"
                    :class="indicador.color"
                    aria-hidden="true"
                >
                    {{ indicador.simbolo }}
                </span>
            </article>
        </section>

        <section class="alerts-panel">
            <header class="panel-header">
                <div>
                    <h2>Emergencias a tu cargo</h2>
                    <p>Consulta las alertas activas que tienes asignadas.</p>
                </div>

                <span class="results-count" role="status">
                    {{ alertasFiltradas.length }} resultados
                </span>
            </header>

            <div class="filters">
                <input
                    v-model="busqueda"
                    type="search"
                    class="form-control"
                    aria-label="Buscar alertas asignadas"
                    placeholder="Buscar por folio, tipo o ubicación..."
                />

                <select
                    v-model="filtroEstado"
                    class="form-select"
                    aria-label="Filtrar por estado"
                >
                    <option value="Todos">Todos los estados</option>
                    <option>Pendiente</option>
                    <option>Recibida</option>
                    <option>En atención</option>
                    <option v-if="!demo">Atendida</option>
                    <option v-if="!demo">Cerrada</option>
                </select>

                <select
                    v-model="filtroPrioridad"
                    class="form-select"
                    aria-label="Filtrar por prioridad"
                >
                    <option value="Todas">Todas las prioridades</option>
                    <option>Por evaluar</option>
                    <option>Alta</option>
                    <option>Media</option>
                    <option>Baja</option>
                </select>

                <button
                    type="button"
                    class="btn btn-light"
                    @click="limpiarFiltros"
                >
                    Limpiar
                </button>
            </div>

            <div class="table-responsive">
                <table class="alerts-table">
                    <caption class="visually-hidden">
                        {{ demo ? 'Alertas activas asignadas al responsable de demostración' : 'Casos asignados a tu cuenta' }}
                    </caption>

                    <thead>
                        <tr>
                            <th scope="col">Folio / Fecha</th>
                            <th scope="col">Tipo de incidente</th>
                            <th scope="col">Ubicación</th>
                            <th scope="col">Prioridad</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="alerta in alertasFiltradas"
                            :key="alerta.folio"
                        >
                            <td>
                                <strong class="folio">{{ alerta.folio }}</strong>
                                <small>{{ alerta.fecha }} · {{ alerta.hora }}</small>
                            </td>

                            <td class="incident-name">{{ alerta.tipo }}</td>
                            <td>{{ alerta.ubicacion }}</td>

                            <td>
                                <span
                                    class="pill"
                                    :class="clasePrioridad(alerta.prioridad)"
                                >
                                    {{ alerta.prioridad }}
                                </span>
                            </td>

                            <td>
                                <span
                                    class="pill"
                                    :class="claseEstado(alerta.estado)"
                                >
                                    {{ alerta.estado }}
                                </span>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="detail-button"
                                    :aria-label="`Ver detalle de ${alerta.folio}`"
                                    :aria-expanded="
                                        alertaSeleccionada?.folio === alerta.folio
                                    "
                                    aria-controls="detalle-asignada"
                                    @click="abrirDetalle(alerta)"
                                >
                                    Ver detalle
                                </button>
                            </td>
                        </tr>

                        <tr v-if="alertasFiltradas.length === 0">
                            <td colspan="6" class="empty-state">
                                No se encontraron alertas con esos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="table-footer">
                Mostrando {{ alertasFiltradas.length }}
                de {{ asignadas.length }} alertas asignadas activas
            </footer>
        </section>

        <section
            id="detalle-asignada"
            class="alerts-panel"
            aria-label="Detalle de alerta asignada"
            aria-live="polite"
        >
            <template v-if="alertaSeleccionada">
                <header class="panel-header">
                    <div>
                        <h2>{{ alertaSeleccionada.folio }}</h2>
                        <p>Detalle de la emergencia asignada</p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-light btn-sm"
                        @click="alertaSeleccionada = null"
                    >
                        Cerrar detalle
                    </button>
                </header>

                <div class="detail-body">
                    <dl class="detail-grid">
                        <div>
                            <dt>Tipo de emergencia</dt>
                            <dd>{{ alertaSeleccionada.tipo }}</dd>
                        </div>

                        <div>
                            <dt>Ubicación</dt>
                            <dd>{{ alertaSeleccionada.ubicacion }}</dd>
                        </div>

                        <div>
                            <dt>Fecha y hora</dt>
                            <dd>
                                {{ alertaSeleccionada.fecha }} ·
                                {{ alertaSeleccionada.hora }}
                            </dd>
                        </div>

                        <div>
                            <dt>Estado</dt>
                            <dd>{{ alertaSeleccionada.estado }}</dd>
                        </div>

                        <div>
                            <dt>Prioridad</dt>
                            <dd>{{ alertaSeleccionada.prioridad }}</dd>
                        </div>

                        <div>
                            <dt>Asignado a</dt>
                            <dd>{{ usuario?.nombre }}</dd>
                        </div>
                    </dl>

                    <div class="description-box">
                        <h3>Descripción del reporte</h3>
                        <p>{{ alertaSeleccionada.descripcion }}</p>
                    </div>

                    <section
                        class="seguimiento-section"
                        aria-labelledby="titulo-seguimiento"
                    >
                        <h3 id="titulo-seguimiento">Registrar seguimiento</h3>

                        <div
                            v-if="mensajeSeguimiento"
                            class="alert alert-success small mb-0"
                            role="status"
                        >
                            {{ mensajeSeguimiento }}
                        </div>

                        <div
                            v-if="['Atendida', 'Cerrada'].includes(alertaSeleccionada.estado)"
                            class="alert alert-info small mb-0"
                        >
                            Este caso ya no admite seguimiento desde el panel del Responsable.
                            El cierre corresponde al Administrador.
                        </div>

                        <div
                            v-else-if="demo ? alertaSeleccionada.estado === 'Pendiente' : alertaSeleccionada.permisos?.confirmarRecepcion"
                            class="d-grid gap-2"
                        >
                            <p class="small text-secondary mb-0">
                                Confirma que recibiste el incidente antes de registrar la atención.
                            </p>

                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="saving"
                                @click="confirmarRecepcion"
                            >
                                Confirmar recepción
                            </button>
                        </div>

                        <form
                            v-else
                            class="seguimiento-form"
                            @submit.prevent="registrarSeguimiento"
                        >
                            <div>
                                <label for="estado-seguimiento" class="form-label">
                                    Estado de la emergencia
                                </label>

                                <select
                                    id="estado-seguimiento"
                                    v-model="estadoSeguimiento"
                                    class="form-select"
                                    :disabled="saving"
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

                            <div>
                                <label for="observacion-seguimiento" class="form-label">
                                    Observaciones
                                </label>

                                <textarea
                                    id="observacion-seguimiento"
                                    v-model="observacion"
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    :disabled="saving"
                                    placeholder="Describe la revisión o el apoyo realizado..."
                                    aria-describedby="observacion-ayuda"
                                    required
                                ></textarea>

                                <small
                                    id="observacion-ayuda"
                                    class="d-block text-secondary mt-2"
                                >
                                    {{ demo ? 'Utiliza información ficticia para esta demostración.' : 'Las observaciones se guardan como seguimiento interno y no se muestran al reportante.' }}
                                </small>
                            </div>

                            <div class="seguimiento-actions">
                                <small>
                                    {{ demo ? 'Seguimiento compartido durante esta sesión de demostración.' : 'El seguimiento se guardará en el sistema.' }}
                                </small>

                                <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
                                    Registrar seguimiento
                                </button>
                            </div>
                        </form>

                        <div
                            v-if="errorSeguimiento"
                            class="alert alert-danger small mb-0"
                            role="alert"
                        >
                            {{ errorSeguimiento }}
                        </div>
                    </section>

                    <section
                        class="seguimiento-section"
                        aria-labelledby="titulo-cronologia"
                    >
                        <h3 id="titulo-cronologia">Seguimiento de la emergencia</h3>

                        <ol class="seguimiento-timeline">
                            <li
                                v-for="registro in historialSeleccionado"
                                :key="registro.id"
                                class="timeline-item"
                            >
                                <div class="timeline-heading">
                                    <strong>{{ registro.estado }}</strong>
                                    <span>{{ registro.fecha }}</span>
                                </div>

                                <small>{{ registro.autor }}</small>
                                <p>{{ registro.observacion }}</p>
                            </li>
                        </ol>
                        <p
                            v-if="historialSeleccionado.length === 0"
                            class="small text-secondary mb-0"
                        >
                            Todavía no hay seguimientos registrados.
                        </p>
                    </section>
                </div>
            </template>

            <p v-else class="detail-placeholder">
                Selecciona “Ver detalle” para consultar una alerta.
            </p>
        </section>
    </div>
</template>

<style scoped>
.asignadas-page {
    display: grid;
    gap: 22px;
    color: #26364a;
}

.demo-notice {
    padding: 13px 16px;
    border: 1px solid #cbdcfb;
    border-radius: 9px;
    background: #eef5ff;
    color: #415b80;
    font-size: 12px;
    line-height: 1.6;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.summary-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 21px;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.summary-label {
    padding-right: 35px;
    color: #68778b;
    font-size: 12px;
}

.summary-card > strong {
    color: #1b2b40;
    font-size: 30px;
}

.summary-description {
    color: #68778b;
    font-size: 11px;
}

.summary-symbol {
    position: absolute;
    top: 18px;
    right: 18px;
    display: grid;
    place-items: center;
    width: 35px;
    height: 35px;
    border-radius: 9px;
    font-size: 18px;
    font-weight: 800;
}

.rojo,
.etiqueta-roja {
    background: #fff0f0;
    color: #cf3434;
}

.ambar,
.etiqueta-ambar {
    background: #fff5df;
    color: #a76c08;
}

.azul,
.etiqueta-azul {
    background: #eaf1ff;
    color: #285bd1;
}

.etiqueta-verde {
    background: #e8f8ef;
    color: #198754;
}

.alerts-panel {
    overflow: hidden;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px;
}

.panel-header h2 {
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 750;
}

.panel-header p {
    margin: 0;
    color: #68778b;
    font-size: 12px;
}

.results-count {
    padding: 7px 10px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    white-space: nowrap;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0 22px 20px;
}

.filters .form-control {
    flex: 1 1 220px;
    width: auto;
    min-width: 0;
}

.filters .form-select {
    flex: 0 1 170px;
    width: auto;
}

.filters input,
.filters select,
.filters button {
    font-size: 12px;
}

.alerts-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.alerts-table th {
    padding: 13px 16px;
    background: #f8fafc;
    color: #68778b;
    text-align: left;
    font-size: 10px;
    font-weight: 750;
}

.alerts-table td {
    padding: 15px 16px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 11px;
}

.alerts-table tbody tr:hover {
    background: #fbfdff;
}

.alerts-table td:first-child small {
    display: block;
    margin-top: 5px;
    color: #68778b;
    font-size: 10px;
}

.folio {
    color: #285bd1;
    font-weight: 750;
}

.incident-name {
    font-weight: 650;
}

.pill {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.detail-button {
    padding: 7px 10px;
    border: 1px solid #d5e1f8;
    border-radius: 6px;
    background: white;
    color: #285bd1;
    font-size: 11px;
    font-weight: 650;
}

.detail-button:hover {
    background: #eef5ff;
}

.detail-button:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 3px;
}

.empty-state {
    padding: 35px !important;
    text-align: center;
}

.table-footer {
    padding: 13px 18px;
    border-top: 1px solid #edf1f6;
    color: #68778b;
    font-size: 11px;
}

.detail-body {
    display: grid;
    gap: 20px;
    padding: 0 22px 22px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin: 0;
}

.detail-grid dt {
    margin-bottom: 6px;
    color: #68778b;
    font-size: 11px;
    font-weight: 400;
}

.detail-grid dd {
    margin: 0;
    overflow-wrap: anywhere;
    font-size: 12px;
    font-weight: 650;
}

.description-box {
    padding: 15px;
    border-radius: 8px;
    background: #f8fafc;
}

.description-box h3 {
    margin: 0 0 8px;
    color: #68778b;
    font-size: 11px;
}

.description-box p,
.detail-note,
.detail-placeholder {
    margin: 0;
    color: #536277;
    font-size: 12px;
    line-height: 1.7;
}

.detail-placeholder {
    padding: 22px;
}

@media (max-width: 760px) {
    .summary-grid,
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .panel-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 18px;
    }

    .filters {
        padding: 0 18px 18px;
    }

    .filters .form-control,
    .filters .form-select {
        flex: 1 1 100%;
        width: 100%;
    }

    .detail-body {
        padding: 0 18px 18px;
    }
}
.seguimiento-section {
    display: grid;
    gap: 16px;
    padding-top: 22px;
    border-top: 1px solid #e6ebf2;
}

.seguimiento-section h3 {
    margin: 0;
    color: #26364a;
    font-size: 14px;
    font-weight: 700;
}

.seguimiento-form {
    display: grid;
    gap: 16px;
}

.seguimiento-form .form-label {
    color: #536277;
    font-size: 12px;
}

.seguimiento-form .form-select,
.seguimiento-form .form-control {
    font-size: 12px;
}

.seguimiento-form textarea {
    min-height: 110px;
    resize: vertical;
}

.seguimiento-form small {
    font-size: 11px;
}

.seguimiento-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.seguimiento-actions small {
    color: #68778b;
}

.seguimiento-timeline {
    margin: 0;
    padding: 0;
    list-style: none;
}

.timeline-item {
    position: relative;
    margin-left: 6px;
    padding: 0 0 24px 22px;
    border-left: 2px solid #e0eaff;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    position: absolute;
    top: 3px;
    left: -6px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #2563eb;
    content: '';
}

.timeline-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.timeline-heading strong {
    color: #26364a;
    font-size: 12px;
}

.timeline-heading span,
.timeline-item > small {
    color: #68778b;
    font-size: 10px;
}

.timeline-item > small {
    display: block;
    margin-top: 5px;
}

.timeline-item p {
    margin: 8px 0 0;
    color: #536277;
    font-size: 12px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.etiqueta-neutral {
    background: #eef2f7;
    color: #64748b;
}
</style>
