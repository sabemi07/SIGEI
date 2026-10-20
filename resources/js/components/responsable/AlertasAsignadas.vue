<script setup>
import { computed, ref } from 'vue';

const responsableDemoId = 'responsable-01';

const busqueda = ref('');
const filtroEstado = ref('Todos');
const filtroPrioridad = ref('Todas');
const alertaSeleccionada = ref(null);
const estadoSeguimiento = ref('Pendiente');
const observacion = ref('');
const mensajeSeguimiento = ref('');
const errorSeguimiento = ref('');
const seguimientos = ref({});

const alertas = ref([
    {
        folio: 'SIG-2026-001',
        responsableId: 'responsable-01',
        tipo: 'Emergencia médica',
        ubicacion: 'Edificio A · Planta baja',
        fecha: '20/10/2026',
        hora: '10:42',
        descripcion: 'Persona que requiere asistencia médica.',
        prioridad: 'Alta',
        estado: 'Pendiente',
    },
    {
        folio: 'SIG-2026-002',
        responsableId: 'responsable-01',
        tipo: 'Incidente de seguridad',
        ubicacion: 'Acceso principal',
        fecha: '20/10/2026',
        hora: '10:28',
        descripcion: 'Se solicita revisar una situación en el acceso.',
        prioridad: 'Media',
        estado: 'En atención',
    },
    {
        folio: 'SIG-2026-003',
        responsableId: 'responsable-01',
        tipo: 'Accidente',
        ubicacion: 'Laboratorio 2',
        fecha: '20/10/2026',
        hora: '09:55',
        descripcion: 'Se reporta un accidente dentro del laboratorio.',
        prioridad: 'Alta',
        estado: 'Recibida',
    },
    {
        folio: 'SIG-2026-004',
        responsableId: 'responsable-02',
        tipo: 'Situación de riesgo',
        ubicacion: 'Edificio B · Segundo piso',
        fecha: '20/10/2026',
        hora: '09:30',
        descripcion: 'Se solicita una revisión del área.',
        prioridad: 'Media',
        estado: 'Pendiente',
    },
]);

const asignadas = computed(() =>
    alertas.value.filter((alerta) =>
        alerta.responsableId === responsableDemoId &&
        ['Pendiente', 'Recibida', 'En atención'].includes(alerta.estado)
    )
);

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
            (alerta) => alerta.estado === 'Pendiente'
        ).length,
        descripcion: 'Pendientes de recepción',
        simbolo: '◷',
        color: 'ambar',
    },
    {
        titulo: 'En atención',
        total: asignadas.value.filter(
            (alerta) => alerta.estado === 'En atención'
        ).length,
        descripcion: 'Con atención en proceso',
        simbolo: '↗',
        color: 'azul',
    },
]);

const alertasFiltradas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return asignadas.value.filter((alerta) => {
        const coincideTexto = [
            alerta.folio,
            alerta.tipo,
            alerta.ubicacion,
        ].some((valor) => valor.toLowerCase().includes(texto));

        return coincideTexto &&
            (filtroEstado.value === 'Todos' ||
                alerta.estado === filtroEstado.value) &&
            (filtroPrioridad.value === 'Todas' ||
                alerta.prioridad === filtroPrioridad.value);
    });
});

function clasePrioridad(prioridad) {
    return {
        Alta: 'etiqueta-roja',
        Media: 'etiqueta-ambar',
        Baja: 'etiqueta-verde',
    }[prioridad];
}

function claseEstado(estado) {
    return {
        Pendiente: 'etiqueta-roja',
        Recibida: 'etiqueta-ambar',
        'En atención': 'etiqueta-azul',
    }[estado];
}

function limpiarFiltros() {
    busqueda.value = '';
    filtroEstado.value = 'Todos';
    filtroPrioridad.value = 'Todas';
}
const historialSeleccionado = computed(() => {
    if (!alertaSeleccionada.value) return [];

    const alerta = alertaSeleccionada.value;

    return [
        {
            id: 'asignacion',
            estado: 'Asignación de emergencia',
            fecha: `${alerta.fecha} · ${alerta.hora}`,
            autor: 'Sistema de demostración',
            observacion: 'Caso asignado al responsable de demostración.',
        },
        ...(seguimientos.value[alerta.folio] || []),
    ];
});

function abrirDetalle(alerta) {
    alertaSeleccionada.value = alerta;
    estadoSeguimiento.value = alerta.estado;
    observacion.value = '';
    mensajeSeguimiento.value = '';
    errorSeguimiento.value = '';
}

function registrarSeguimiento() {
    const alerta = alertaSeleccionada.value;

    if (!alerta || alerta.estado === 'Atendida') return;

    errorSeguimiento.value = '';
    mensajeSeguimiento.value = '';

    const texto = observacion.value.trim();

    if (!texto) {
        errorSeguimiento.value =
            'Escribe una observación para registrar el seguimiento.';
        return;
    }

    const estadosPermitidos = ['Pendiente', 'Recibida', 'En atención', 'Atendida'];

    if (!estadosPermitidos.includes(estadoSeguimiento.value)) {
        errorSeguimiento.value = 'Selecciona un estado válido.';
        return;
    }

    // Evita retroceder de estado en esta demostración.
    if (
        estadosPermitidos.indexOf(estadoSeguimiento.value) <
        estadosPermitidos.indexOf(alerta.estado)
    ) {
        errorSeguimiento.value =
            'Selecciona el estado actual o uno posterior.';
        return;
    }

    const registros = seguimientos.value[alerta.folio] || [];

    const fecha = new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date());

    seguimientos.value[alerta.folio] = [
        ...registros,
        {
            id: `seguimiento-${registros.length + 1}`,
            estado: estadoSeguimiento.value,
            fecha,
            autor: 'Responsable de demostración',
            observacion: texto,
        },
    ];

    alerta.estado = estadoSeguimiento.value;
    observacion.value = '';

    mensajeSeguimiento.value = alerta.estado === 'Atendida'
        ? 'Atención registrada en la demostración. El caso queda pendiente de cierre por el Administrador.'
        : 'Seguimiento registrado en esta vista de demostración.';
}
</script>

<template>
    <div class="asignadas-page">
        <div class="demo-notice">
            <strong>Modo demostración:</strong>
            datos ficticios del responsable de ejemplo.
            Esta vista todavía no está conectada con el administrador.
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
                </select>

                <select
                    v-model="filtroPrioridad"
                    class="form-select"
                    aria-label="Filtrar por prioridad"
                >
                    <option value="Todas">Todas las prioridades</option>
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
                        Alertas activas asignadas al responsable de demostración
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
                            <dd>Responsable de demostración</dd>
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
                            v-if="alertaSeleccionada.estado === 'Atendida'"
                            class="alert alert-info small mb-0"
                        >
                            La emergencia fue marcada como atendida y salió de
                            la lista de alertas activas. El cierre corresponde
                            al Administrador.
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
                                >
                                    <option
                                        :disabled="alertaSeleccionada.estado !== 'Pendiente'"
                                    >
                                        Pendiente
                                    </option>

                                    <option
                                        :disabled="alertaSeleccionada.estado === 'En atención'"
                                    >
                                        Recibida
                                    </option>

                                    <option>En atención</option>
                                    <option>Atendida</option>
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
                                    placeholder="Describe la revisión o el apoyo realizado..."
                                    aria-describedby="observacion-ayuda"
                                    required
                                ></textarea>

                                <small
                                    id="observacion-ayuda"
                                    class="d-block text-secondary mt-2"
                                >
                                    Utiliza información ficticia para esta demostración.
                                </small>
                            </div>

                            <div
                                v-if="errorSeguimiento"
                                class="alert alert-danger small mb-0"
                                role="alert"
                            >
                                {{ errorSeguimiento }}
                            </div>

                            <div class="seguimiento-actions">
                                <small>
                                    Simulación local, sin guardar en una base de datos.
                                </small>

                                <button type="submit" class="btn btn-primary btn-sm">
                                    Registrar seguimiento
                                </button>
                            </div>
                        </form>
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
</style>