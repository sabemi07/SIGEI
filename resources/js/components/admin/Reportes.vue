<script setup>
import { computed, ref } from 'vue';

const incidentes = [
    {
        folio: 'SIG-2026-001',
        fecha: '2026-10-19',
        hora: '10:42',
        tipo: 'Emergencia médica',
        ubicacion: 'Edificio A · Planta baja',
        prioridad: 'Alta',
        estado: 'Pendiente',
        responsable: 'Sin asignar',
    },
    {
        folio: 'SIG-2026-002',
        fecha: '2026-10-19',
        hora: '10:28',
        tipo: 'Incidente de seguridad',
        ubicacion: 'Acceso principal',
        prioridad: 'Media',
        estado: 'En atención',
        responsable: 'Personal de seguridad',
    },
    {
        folio: 'SIG-2026-003',
        fecha: '2026-10-19',
        hora: '09:55',
        tipo: 'Accidente',
        ubicacion: 'Laboratorio 2',
        prioridad: 'Alta',
        estado: 'Pendiente',
        responsable: 'Sin asignar',
    },
    {
        folio: 'SIG-2026-004',
        fecha: '2026-10-19',
        hora: '09:30',
        tipo: 'Situación de riesgo',
        ubicacion: 'Edificio B · Segundo piso',
        prioridad: 'Baja',
        estado: 'Atendida',
        responsable: 'Personal administrativo',
    },
    {
        folio: 'SIG-2026-005',
        fecha: '2026-10-19',
        hora: '09:12',
        tipo: 'Emergencia médica',
        ubicacion: 'Cancha deportiva',
        prioridad: 'Media',
        estado: 'Cerrada',
        responsable: 'Personal médico',
    },
];

const fechaInicio = ref('');
const fechaFin = ref('');
const filtroTipo = ref('');
const filtroEstado = ref('');
const filtroPrioridad = ref('');

const estados = [
    'Pendiente',
    'Recibida',
    'En atención',
    'Atendida',
    'Cerrada',
];

const prioridades = ['Alta', 'Media', 'Baja'];

const tipos = [...new Set(incidentes.map(item => item.tipo))];

const errorFechas = computed(() => {
    if (
        fechaInicio.value &&
        fechaFin.value &&
        fechaInicio.value > fechaFin.value
    ) {
        return 'La fecha de inicio no puede ser posterior a la fecha final.';
    }

    return '';
});

const resultados = computed(() => {
    if (errorFechas.value) return [];

    return incidentes.filter(item =>
        (!fechaInicio.value || item.fecha >= fechaInicio.value) &&
        (!fechaFin.value || item.fecha <= fechaFin.value) &&
        (!filtroTipo.value || item.tipo === filtroTipo.value) &&
        (!filtroEstado.value || item.estado === filtroEstado.value) &&
        (!filtroPrioridad.value || item.prioridad === filtroPrioridad.value)
    );
});

const indicadores = computed(() => [
    {
        titulo: 'Total de incidentes',
        valor: resultados.value.length,
        detalle: 'Según los filtros',
        color: 'blue',
    },
    {
        titulo: 'Activos',
        valor: resultados.value.filter(item =>
            ['Pendiente', 'Recibida', 'En atención'].includes(item.estado)
        ).length,
        detalle: 'Requieren seguimiento',
        color: 'red',
    },
    {
        titulo: 'Atendidos',
        valor: resultados.value.filter(
            item => item.estado === 'Atendida'
        ).length,
        detalle: 'Pendientes de cierre',
        color: 'amber',
    },
    {
        titulo: 'Cerrados',
        valor: resultados.value.filter(
            item => item.estado === 'Cerrada'
        ).length,
        detalle: 'Casos concluidos',
        color: 'green',
    },
]);

function agruparPor(campo, categorias) {
    const total = resultados.value.length;

    return categorias.map(nombre => {
        const cantidad = resultados.value.filter(
            item => item[campo] === nombre
        ).length;

        return {
            nombre,
            cantidad,
            porcentaje: total ? (cantidad / total) * 100 : 0,
        };
    });
}

const porTipo = computed(() => agruparPor('tipo', tipos));
const porEstado = computed(() => agruparPor('estado', estados));

function limpiarFiltros() {
    fechaInicio.value = '';
    fechaFin.value = '';
    filtroTipo.value = '';
    filtroEstado.value = '';
    filtroPrioridad.value = '';
}

function formatearFecha(fecha) {
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${anio}`;
}

function celdaCsv(valor) {
    let texto = String(valor ?? '');

    // Evita que una hoja de cálculo interprete el contenido como fórmula.
    if (/^\s*[=+\-@]/.test(texto) || /^[\t\r\n]/.test(texto)) {
        texto = `'${texto}`;
    }

    return `"${texto.replaceAll('"', '""')}"`;
}

function exportarCsv() {
    if (errorFechas.value || resultados.value.length === 0) return;

    const filas = [
        [
            'Folio',
            'Fecha',
            'Hora',
            'Tipo',
            'Ubicación',
            'Prioridad',
            'Estado',
            'Responsable',
        ],
        ...resultados.value.map(item => [
            item.folio,
            item.fecha,
            item.hora,
            item.tipo,
            item.ubicacion,
            item.prioridad,
            item.estado,
            item.responsable,
        ]),
    ];

    const contenido = filas
        .map(fila => fila.map(celdaCsv).join(','))
        .join('\r\n');

    const archivo = new Blob(
        ['\uFEFF', contenido],
        { type: 'text/csv;charset=utf-8;' }
    );

    const url = URL.createObjectURL(archivo);
    const enlace = document.createElement('a');

    enlace.href = url;
    enlace.download = 'SIGEI_reporte_demostracion.csv';

    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();

    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
</script>

<template>
    <div class="reports-page">
        <div class="demo-notice">
            <strong>Modo demostración:</strong>
            este reporte utiliza registros ficticios independientes
            de los cambios realizados en Incidentes.
        </div>

        <section class="panel">
            <header class="panel-heading">
                <div>
                    <h2>Reportes y estadísticas</h2>
                    <p>Consulta la distribución de los incidentes registrados.</p>
                </div>

                <button
                    type="button"
                    class="primary-button"
                    :disabled="!!errorFechas || resultados.length === 0"
                    @click="exportarCsv"
                >
                    Exportar CSV
                </button>
            </header>

            <div class="filters">
                <div class="filter-field">
                    <label for="reporte-inicio">Fecha de inicio</label>
                    <input
                        id="reporte-inicio"
                        v-model="fechaInicio"
                        type="date"
                        :aria-invalid="!!errorFechas"
                        :aria-describedby="errorFechas ? 'error-fechas' : undefined"
                    />
                </div>

                <div class="filter-field">
                    <label for="reporte-fin">Fecha final</label>
                    <input
                        id="reporte-fin"
                        v-model="fechaFin"
                        type="date"
                        :aria-invalid="!!errorFechas"
                        :aria-describedby="errorFechas ? 'error-fechas' : undefined"
                    />
                </div>

                <div class="filter-field">
                    <label for="reporte-tipo">Tipo de incidente</label>
                    <select id="reporte-tipo" v-model="filtroTipo">
                        <option value="">Todos los tipos</option>
                        <option v-for="tipo in tipos" :key="tipo">
                            {{ tipo }}
                        </option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="reporte-estado">Estado</label>
                    <select id="reporte-estado" v-model="filtroEstado">
                        <option value="">Todos los estados</option>
                        <option v-for="estado in estados" :key="estado">
                            {{ estado }}
                        </option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="reporte-prioridad">Prioridad</label>
                    <select id="reporte-prioridad" v-model="filtroPrioridad">
                        <option value="">Todas las prioridades</option>
                        <option
                            v-for="prioridad in prioridades"
                            :key="prioridad"
                        >
                            {{ prioridad }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="filter-footer">
                <p>Los filtros se aplican automáticamente.</p>

                <button
                    type="button"
                    class="secondary-button"
                    @click="limpiarFiltros"
                >
                    Limpiar filtros
                </button>
            </div>

            <p
                v-if="errorFechas"
                id="error-fechas"
                class="error-message"
                role="alert"
            >
                {{ errorFechas }}
            </p>
        </section>

        <template v-if="!errorFechas">
            <section class="stats-grid" aria-label="Resumen del reporte">
                <article
                    v-for="item in indicadores"
                    :key="item.titulo"
                    class="stat-card"
                >
                    <span>{{ item.titulo }}</span>
                    <strong :class="item.color">{{ item.valor }}</strong>
                    <small>{{ item.detalle }}</small>
                </article>
            </section>

            <div class="charts-grid">
                <section class="panel chart-panel">
                    <h3>Incidentes por tipo</h3>
                    <p class="chart-description">
                        Porcentaje del total filtrado.
                    </p>

                    <div v-if="resultados.length" class="bars">
                        <div
                            v-for="item in porTipo"
                            :key="item.nombre"
                            class="bar-item"
                        >
                            <div class="bar-heading">
                                <span>{{ item.nombre }}</span>
                                <strong>
                                    {{ item.cantidad }}
                                    · {{ item.porcentaje.toFixed(1) }}%
                                </strong>
                            </div>

                            <div class="bar-track" aria-hidden="true">
                                <div
                                    class="bar-fill"
                                    :style="{ width: `${item.porcentaje}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <p v-else class="empty-chart">
                        No hay datos para graficar.
                    </p>
                </section>

                <section class="panel chart-panel">
                    <h3>Incidentes por estado</h3>
                    <p class="chart-description">
                        Porcentaje del total filtrado.
                    </p>

                    <div v-if="resultados.length" class="bars">
                        <div
                            v-for="item in porEstado"
                            :key="item.nombre"
                            class="bar-item"
                        >
                            <div class="bar-heading">
                                <span>{{ item.nombre }}</span>
                                <strong>
                                    {{ item.cantidad }}
                                    · {{ item.porcentaje.toFixed(1) }}%
                                </strong>
                            </div>

                            <div class="bar-track" aria-hidden="true">
                                <div
                                    class="bar-fill state-fill"
                                    :style="{ width: `${item.porcentaje}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <p v-else class="empty-chart">
                        No hay datos para graficar.
                    </p>
                </section>
            </div>

            <section class="panel">
                <header class="panel-heading">
                    <div>
                        <h2>Detalle de resultados</h2>
                        <p>Incidentes que coinciden con los filtros seleccionados.</p>
                    </div>

                    <span class="result-count" role="status">
                        {{ resultados.length }} resultados
                    </span>
                </header>

                <div class="table-responsive">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th scope="col">Folio / Fecha</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Ubicación</th>
                                <th scope="col">Prioridad</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Responsable</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="item in resultados" :key="item.folio">
                                <td>
                                    <strong class="folio">{{ item.folio }}</strong>
                                    <small>
                                        {{ formatearFecha(item.fecha) }}
                                        · {{ item.hora }}
                                    </small>
                                </td>

                                <td>{{ item.tipo }}</td>
                                <td>{{ item.ubicacion }}</td>

                                <td>
                                    <span
                                        class="pill"
                                        :class="{
                                            'priority-high': item.prioridad === 'Alta',
                                            'priority-medium': item.prioridad === 'Media',
                                            'priority-low': item.prioridad === 'Baja',
                                        }"
                                    >
                                        {{ item.prioridad }}
                                    </span>
                                </td>

                                <td>{{ item.estado }}</td>
                                <td>{{ item.responsable }}</td>
                            </tr>

                            <tr v-if="resultados.length === 0">
                                <td colspan="6" class="empty-state">
                                    No se encontraron incidentes.
                                    Prueba con otros filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer class="table-footer">
                    La exportación CSV incluye únicamente los resultados filtrados.
                </footer>
            </section>
        </template>
    </div>
</template>

<style scoped>
.reports-page {
    display: grid;
    gap: 22px;
    color: #26364a;
}

.demo-notice {
    padding: 14px 17px;
    border: 1px solid #cbdcfb;
    border-radius: 9px;
    background: #eef5ff;
    color: #415b80;
    font-size: 12px;
    line-height: 1.7;
}

.panel {
    overflow: hidden;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px;
}

.panel-heading h2,
.chart-panel h3 {
    margin: 0 0 7px;
    font-size: 16px;
    font-weight: 750;
}

.panel-heading p,
.chart-description {
    margin: 0;
    color: #718096;
    font-size: 12px;
    line-height: 1.6;
}

.filters {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 13px;
    padding: 0 22px;
}

.filter-field {
    display: grid;
    align-content: start;
    gap: 8px;
}

.filter-field label {
    color: #64748b;
    font-size: 11px;
}

.filter-field input,
.filter-field select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    background: white;
    color: #536277;
    font: inherit;
    font-size: 12px;
}

.filter-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
}

.filter-footer p {
    margin: 0;
    color: #8491a3;
    font-size: 11px;
}

.primary-button,
.secondary-button {
    padding: 10px 13px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.primary-button {
    border: 1px solid #2563eb;
    background: #2563eb;
    color: white;
}

.primary-button:hover:not(:disabled) {
    background: #1d4ed8;
}

.primary-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.secondary-button {
    border: 1px solid #dfe6ef;
    background: #f8fafc;
    color: #536277;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.stat-card {
    display: grid;
    gap: 10px;
    padding: 20px;
    border: 1px solid #e6ebf2;
    border-radius: 11px;
    background: white;
}

.stat-card span,
.stat-card small {
    color: #718096;
    font-size: 11px;
}

.stat-card strong {
    font-size: 29px;
}

.blue { color: #285bd1; }
.red { color: #d93636; }
.amber { color: #a76c08; }
.green { color: #198754; }

.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.chart-panel {
    padding: 22px;
}

.bars {
    display: grid;
    gap: 20px;
    margin-top: 25px;
}

.bar-heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
    font-size: 12px;
}

.bar-heading strong {
    flex-shrink: 0;
    color: #64748b;
    font-size: 11px;
}

.bar-track {
    height: 10px;
    overflow: hidden;
    border-radius: 8px;
    background: #eef2f7;
}

.bar-fill {
    height: 100%;
    border-radius: inherit;
    background: #2563eb;
    transition: width 0.2s;
}

.state-fill {
    background: #198754;
}

.empty-chart,
.empty-state {
    padding: 30px !important;
    color: #8491a3;
    text-align: center;
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

.table-responsive {
    overflow-x: auto;
}

.reports-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.reports-table th {
    padding: 14px 16px;
    background: #f8fafc;
    color: #7f8da0;
    text-align: left;
    font-size: 11px;
}

.reports-table td {
    padding: 16px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 12px;
}

.reports-table td small {
    display: block;
    margin-top: 5px;
    color: #8491a3;
    font-size: 10px;
}

.folio {
    color: #285bd1;
}

.pill {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 650;
}

.priority-high {
    background: #fff0f0;
    color: #cf3434;
}

.priority-medium {
    background: #fff5df;
    color: #a76c08;
}

.priority-low {
    background: #e8f8ef;
    color: #198754;
}

.table-footer {
    padding: 14px 18px;
    border-top: 1px solid #edf1f6;
    color: #8491a3;
    font-size: 11px;
}

.error-message {
    margin: 0 22px 20px;
    padding: 12px;
    border: 1px solid #fecaca;
    border-radius: 7px;
    background: #fff0f0;
    color: #b91c1c;
    font-size: 12px;
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 3px;
}

@media (max-width: 1100px) {
    .filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .filters {
        grid-template-columns: 1fr;
    }

    .panel-heading,
    .filter-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .stat-card {
        padding: 15px;
    }

    .bar-heading {
        flex-wrap: wrap;
        gap: 5px;
    }
}
</style>