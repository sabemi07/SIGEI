<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
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

const busqueda = ref('');
const filtroEstado = ref('Todos');
const folioSeleccionado = ref(null);

const estadosActivos = ['Pendiente', 'Recibida', 'En atención'];

const descripcionesPublicas = {
    Pendiente: 'El reporte está pendiente de recepción.',
    Recibida: 'Se registró la recepción del incidente.',
    'En atención': 'El personal asignado está atendiendo el incidente.',
    Atendida: 'La atención fue registrada. El caso queda pendiente de cierre.',
    Cerrada: 'El Administrador dio por concluido el caso.',
};

const reportes = computed(() => {
    const usuario = props.usuario;

    if (!usuario?.activo) return [];

    return props.incidentes.filter(
        incidente => incidente.reportanteId === usuario.id
    );
});

const reporteSeleccionado = computed({
    get() {
        return reportes.value.find(
            reporte => reporte.folio === folioSeleccionado.value
        ) ?? null;
    },
    set(reporte) {
        folioSeleccionado.value = reporte?.folio ?? null;
    },
});

const indicadores = computed(() => [
    {
        titulo: 'Mis reportes',
        total: reportes.value.length,
        descripcion: 'Emergencias que has reportado',
    },
    {
        titulo: 'En seguimiento',
        total: reportes.value.filter(
            reporte => estadosActivos.includes(reporte.estado)
        ).length,
        descripcion: 'Pendientes, recibidas o en atención',
    },
    {
        titulo: 'Finalizados',
        total: reportes.value.filter(
            reporte => ['Atendida', 'Cerrada'].includes(reporte.estado)
        ).length,
        descripcion: 'Atendidos o cerrados',
    },
]);

const reportesFiltrados = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return reportes.value.filter(reporte => {
        const coincideTexto = [
            reporte.folio,
            reporte.tipo,
            reporte.ubicacion,
        ].some(valor => valor.toLowerCase().includes(texto));

        return coincideTexto &&
            (filtroEstado.value === 'Todos' ||
                reporte.estado === filtroEstado.value);
    });
});

const avancesSeleccionados = computed(() => {
    const reporte = reporteSeleccionado.value;

    if (!reporte) return [];

    return [
        {
            id: 'registro',
            titulo: 'Reporte registrado',
            fecha: `${reporte.fecha} · ${reporte.hora}`,
            descripcion: 'La emergencia fue registrada para su revisión.',
        },
        ...(reporte.seguimientos ?? []).map((registro, indice) => ({
            id: registro.id ?? `avance-${indice}`,
            titulo: registro.estado,
            fecha: registro.fecha,
            descripcion:
                descripcionesPublicas[registro.estado] ??
                'Se registró un avance en la atención del incidente.',
        })),
    ];
});

function claseEstado(estado) {
    return {
        Pendiente: 'estado-pendiente',
        Recibida: 'estado-recibida',
        'En atención': 'estado-atencion',
        Atendida: 'estado-atendida',
        Cerrada: 'estado-cerrada',
    }[estado];
}

function limpiarFiltros() {
    busqueda.value = '';
    filtroEstado.value = 'Todos';
}
watch(
    () => props.solicitudDetalle,
    solicitud => {
        if (!solicitud) return;

        const reporte = reportes.value.find(
            item => item.folio === solicitud.folio
        );

        reporteSeleccionado.value = reporte ?? null;
    },
    { immediate: true }
);
</script>

<template>
    <div class="mis-reportes-page">
        <div class="demo-notice">
            <strong>Modo demostración:</strong>
            aquí aparecen los incidentes registrados por
            {{ usuario?.nombre || 'tu cuenta' }}.
            Su estado se comparte con el panel administrativo.
            Los datos se pierden al recargar.
        </div>

        <section class="summary-grid" aria-label="Resumen de mis reportes">
            <article
                v-for="indicador in indicadores"
                :key="indicador.titulo"
                class="summary-card"
            >
                <span>{{ indicador.titulo }}</span>
                <strong>{{ indicador.total }}</strong>
                <small>{{ indicador.descripcion }}</small>
            </article>
        </section>

        <section class="reports-panel">
            <header class="panel-header">
                <div>
                    <h2>Emergencias que he reportado</h2>
                    <p>Consulta el estado y los avances de tus reportes.</p>
                </div>

                <span class="results-count" role="status">
                    {{ reportesFiltrados.length }} resultados
                </span>
            </header>

            <div class="filters">
                <input
                    v-model="busqueda"
                    type="search"
                    class="form-control"
                    aria-label="Buscar en mis reportes"
                    placeholder="Buscar por folio, tipo o ubicación..."
                />

                <select
                    v-model="filtroEstado"
                    class="form-select"
                    aria-label="Filtrar mis reportes por estado"
                >
                    <option value="Todos">Todos los estados</option>
                    <option>Pendiente</option>
                    <option>Recibida</option>
                    <option>En atención</option>
                    <option>Atendida</option>
                    <option>Cerrada</option>
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
                <table class="reports-table">
                    <caption class="visually-hidden">
                        Reportes personales del responsable de demostración
                    </caption>

                    <thead>
                        <tr>
                            <th scope="col">Folio / Fecha</th>
                            <th scope="col">Tipo de emergencia</th>
                            <th scope="col">Ubicación</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="reporte in reportesFiltrados"
                            :key="reporte.folio"
                        >
                            <td>
                                <strong class="folio">{{ reporte.folio }}</strong>
                                <small>
                                    {{ reporte.fecha }} · {{ reporte.hora }}
                                </small>
                            </td>

                            <td>{{ reporte.tipo }}</td>
                            <td>{{ reporte.ubicacion }}</td>

                            <td>
                                <span
                                    class="state-pill"
                                    :class="claseEstado(reporte.estado)"
                                >
                                    {{ reporte.estado }}
                                </span>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="detail-button"
                                    :aria-label="`Ver mi reporte ${reporte.folio}`"
                                    :aria-expanded="
                                        reporteSeleccionado?.folio === reporte.folio
                                    "
                                    aria-controls="detalle-mi-reporte"
                                    @click="reporteSeleccionado = reporte"
                                >
                                    Ver detalle
                                </button>
                            </td>
                        </tr>

                        <tr v-if="reportesFiltrados.length === 0">
                            <td colspan="5" class="empty-state">
                                No se encontraron reportes con esos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="table-footer">
                Mostrando {{ reportesFiltrados.length }}
                de {{ reportes.length }} reportes personales
            </footer>
        </section>

        <section
            id="detalle-mi-reporte"
            class="reports-panel"
            aria-label="Detalle de mi reporte"
            aria-live="polite"
        >
            <template v-if="reporteSeleccionado">
                <header class="panel-header">
                    <div>
                        <h2>{{ reporteSeleccionado.folio }}</h2>
                        <p>Consulta de tu reporte</p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-light btn-sm"
                        @click="reporteSeleccionado = null"
                    >
                        Cerrar detalle
                    </button>
                </header>

                <div class="detail-body">
                    <dl class="detail-grid">
                        <div>
                            <dt>Tipo de emergencia</dt>
                            <dd>{{ reporteSeleccionado.tipo }}</dd>
                        </div>

                        <div>
                            <dt>Ubicación</dt>
                            <dd>{{ reporteSeleccionado.ubicacion }}</dd>
                        </div>

                        <div>
                            <dt>Fecha y hora del reporte</dt>
                            <dd>
                                {{ reporteSeleccionado.fecha }} ·
                                {{ reporteSeleccionado.hora }}
                            </dd>
                        </div>

                        <div>
                            <dt>Estado actual</dt>
                            <dd>
                                <span
                                    class="state-pill"
                                    :class="claseEstado(reporteSeleccionado.estado)"
                                >
                                    {{ reporteSeleccionado.estado }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>Responsable asignado</dt>
                            <dd>{{ reporteSeleccionado.responsable }}</dd>
                        </div>
                    </dl>

                    <div class="description-box">
                        <h3>Descripción que enviaste</h3>
                        <p>{{ reporteSeleccionado.descripcion }}</p>
                    </div>

                    <section aria-labelledby="titulo-avances">
                        <h3 id="titulo-avances" class="timeline-title">
                            Avances de tu reporte
                        </h3>
                        <ol class="report-timeline">
                            <li
                                v-for="avance in avancesSeleccionados"
                                :key="avance.id"
                            >
                                <div class="timeline-heading">
                                    <strong>{{ avance.titulo }}</strong>
                                    <span>{{ avance.fecha }}</span>
                                </div>

                                <p>{{ avance.descripcion }}</p>
                            </li>
                        </ol>
                    </section>

                    <p class="consultation-note">
                        Esta vista es de consulta. La atención y los cambios
                        de estado corresponden al personal asignado.
                    </p>
                </div>
            </template>

            <p v-else class="detail-placeholder">
                Selecciona “Ver detalle” para consultar el estado
                y los avances de uno de tus reportes.
            </p>
        </section>
    </div>
</template>

<style scoped>
.mis-reportes-page {
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
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 21px;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.summary-card > span {
    color: #68778b;
    font-size: 12px;
}

.summary-card > strong {
    color: #1b2b40;
    font-size: 30px;
}

.summary-card > small {
    color: #68778b;
    font-size: 11px;
}

.reports-panel {
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
    flex: 0 1 190px;
    width: auto;
}

.filters input,
.filters select,
.filters button {
    font-size: 12px;
}

.reports-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.reports-table th {
    padding: 13px 16px;
    background: #f8fafc;
    color: #68778b;
    text-align: left;
    font-size: 10px;
    font-weight: 750;
}

.reports-table td {
    padding: 15px 16px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 11px;
}

.reports-table tbody tr:hover {
    background: #fbfdff;
}

.reports-table td:first-child small {
    display: block;
    margin-top: 5px;
    color: #68778b;
    font-size: 10px;
}

.folio {
    color: #285bd1;
}

.state-pill {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.estado-pendiente {
    background: #fff0f0;
    color: #cf3434;
}

.estado-recibida {
    background: #fff5df;
    color: #a76c08;
}

.estado-atencion {
    background: #eaf1ff;
    color: #285bd1;
}

.estado-atendida {
    background: #e8f8ef;
    color: #198754;
}

.estado-cerrada {
    background: #eef2f7;
    color: #536277;
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
    gap: 22px;
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
    font-size: 12px;
    font-weight: 650;
    overflow-wrap: anywhere;
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
.consultation-note,
.detail-placeholder {
    margin: 0;
    color: #536277;
    font-size: 12px;
    line-height: 1.7;
}

.detail-placeholder {
    padding: 22px;
}

.timeline-title {
    margin: 0 0 20px;
    font-size: 14px;
    font-weight: 700;
}

.report-timeline {
    margin: 0;
    padding: 0;
    list-style: none;
}

.report-timeline li {
    position: relative;
    margin-left: 6px;
    padding: 0 0 24px 22px;
    border-left: 2px solid #e0eaff;
}

.report-timeline li:last-child {
    padding-bottom: 0;
}

.report-timeline li::before {
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
    justify-content: space-between;
    gap: 8px;
}

.timeline-heading strong {
    font-size: 12px;
}

.timeline-heading span {
    color: #68778b;
    font-size: 10px;
}

.report-timeline p {
    margin: 8px 0 0;
    color: #536277;
    font-size: 12px;
    line-height: 1.7;
}

.consultation-note {
    padding-top: 16px;
    border-top: 1px solid #e6ebf2;
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
</style>