
<script setup>
import { computed, ref, toRef } from 'vue';
const props = defineProps({
    demo: { type: Boolean, default: true },
    incidentes: {
        type: Array,
        default: () => [],
    },
});

const incidentes = toRef(props, 'incidentes');

const emit = defineEmits(['gestionar']);

const busqueda = ref('');
const filtroEstado = ref('Todos');
const filtroPrioridad = ref('Todas');

const vistaSeleccionada = ref('activos');

const estadosActivos = ['Pendiente', 'Recibida', 'En atención'];

const vistas = [
    { id: 'activos', label: 'Activos' },
    { id: 'atendidos', label: 'Atendidos' },
    { id: 'cerrados', label: 'Cerrados' },
    { id: 'todos', label: 'Todos' },
];

function perteneceAVista(incidente, vista) {
    if (vista === 'activos') {
        return estadosActivos.includes(incidente.estado);
    }

    if (vista === 'atendidos') {
        return incidente.estado === 'Atendida';
    }

    if (vista === 'cerrados') {
        return incidente.estado === 'Cerrada';
    }

    return true;
}

const conteosVistas = computed(() =>
    Object.fromEntries(
        vistas.map((vista) => [
            vista.id,
            incidentes.value.filter(
                (incidente) => perteneceAVista(incidente, vista.id)
            ).length,
        ])
    )
);

const estadosDisponibles = computed(() => {
    if (vistaSeleccionada.value === 'activos') {
        return estadosActivos;
    }

    if (vistaSeleccionada.value === 'atendidos') {
        return ['Atendida'];
    }

    if (vistaSeleccionada.value === 'cerrados') {
        return ['Cerrada'];
    }

    return estados;
});

function cambiarVista(vista) {
    vistaSeleccionada.value = vista;

    // Evita conservar un estado incompatible con la nueva vista.
    filtroEstado.value = 'Todos';
}



const estados = [
    'Pendiente',
    'Recibida',
    'En atención',
    'Atendida',
    'Cerrada',
];

const incidentesFiltrados = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return incidentes.value.filter((incidente) => {
        const coincideTexto = [
            incidente.folio,
            incidente.tipo,
            incidente.ubicacion,
        ].some((valor) => valor.toLowerCase().includes(texto));

        const coincideEstado =
            filtroEstado.value === 'Todos' ||
            incidente.estado === filtroEstado.value;

        const coincidePrioridad =
            filtroPrioridad.value === 'Todas' ||
            incidente.prioridad === filtroPrioridad.value;

            const coincideVista = perteneceAVista(
                incidente,
                vistaSeleccionada.value
            );

            return coincideVista &&
                coincideTexto &&
                coincideEstado &&
                coincidePrioridad;
            });
});



function limpiarFiltros() {
    busqueda.value = '';
    filtroEstado.value = 'Todos';
    filtroPrioridad.value = 'Todas';
}

function gestionarIncidente(incidente) {
    emit('gestionar', incidente);
}
</script>

<template>
    <div class="incidents-page">
        <div v-if="demo" class="demo-notice">
            <span class="notice-icon">i</span>
            <span>
                <strong>Modo demostración:</strong>
                los incidentes son ficticios. Los datos no se guardan
                en una base de datos.
            </span> 
        </div>

            <section class="stats-grid">
        <article class="stat-card">
            <span>Total de incidentes</span>
            <strong>{{ incidentes.length }}</strong>
            <small>Todos los registros</small>
        </article>

        <article class="stat-card">
            <span>Activos</span>
            <strong class="red">{{ conteosVistas.activos }}</strong>
            <small>Requieren atención o seguimiento</small>
        </article>

        <article class="stat-card">
            <span>Atendidos</span>
            <strong class="blue">{{ conteosVistas.atendidos }}</strong>
            <small>Pendientes de cierre administrativo</small>
        </article>

        <article class="stat-card">
            <span>Cerrados</span>
            <strong class="green">{{ conteosVistas.cerrados }}</strong>
            <small>Casos concluidos</small>
        </article>
    </section>

        <section class="incidents-panel">
            <header class="panel-header">
                <div>
                    <h2>Registro de incidentes</h2>
                    <p>
                        Gestiona las emergencias activas y consulta los casos atendidos
                        o cerrados desde una misma pantalla.
                    </p>
                </div>

                <span class="result-count">
                    {{ incidentesFiltrados.length }} resultados
                </span>
            </header>
            <div class="incident-views" role="group" aria-label="Vista de incidentes">
                <button
                    v-for="vista in vistas"
                    :key="vista.id"
                    type="button"
                    class="incident-view-button"
                    :class="{ selected: vistaSeleccionada === vista.id }"
                    :aria-pressed="vistaSeleccionada === vista.id"
                    @click="cambiarVista(vista.id)"
                >
                    {{ vista.label }}

                    <span>{{ conteosVistas[vista.id] }}</span>
                </button>
            </div>

            <div class="filters">
                <input
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar por folio, incidente o ubicación..."
                    aria-label="Buscar incidentes"
                />

                <select v-model="filtroEstado" aria-label="Filtrar por estado">
                    <option>Todos</option>
                    <option v-for="estado in estadosDisponibles" :key="estado">
                        {{ estado }}
                    </option>
                </select>

                <select
                    v-model="filtroPrioridad"
                    aria-label="Filtrar por prioridad"
                >
                    <option>Todas</option>
                    <option>Por Evaluar</option>
                    <option>Alta</option>
                    <option>Media</option>
                    <option>Baja</option>
                </select>

                <button class="clear-button" @click="limpiarFiltros">
                    Limpiar
                </button>
            </div>

            <div class="table-responsive">
                <table class="incidents-table">
                    <thead>
                        <tr>
                            <th>Folio / Fecha</th>
                            <th>Tipo de incidente</th>
                            <th>Ubicación</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="incidente in incidentesFiltrados"
                            :key="incidente.folio"
                        >
                            <td>
                                <strong class="folio">{{ incidente.folio }}</strong>
                                <small>
                                    {{ incidente.fecha }} · {{ incidente.hora }}
                                </small>
                            </td>

                            <td class="incident-title">{{ incidente.tipo }}</td>

                            <td>{{ incidente.ubicacion }}</td>

                            <td>
                                <span
                                    class="pill"
                                    :class="'priority-' + incidente.prioridad.toLowerCase().replaceAll(' ', '-')"
                                >
                                    {{ incidente.prioridad }}
                                </span>
                            </td>

                            <td>
                                <span
                                    class="pill"
                                    :class="'state-' + incidente.estado.toLowerCase().replaceAll(' ', '-')"
                                >
                                    {{ incidente.estado }}
                                </span>
                            </td>

                            <td>
                                <button
                                    class="detail-button"
                                    @click="gestionarIncidente(incidente)"
                                >
                                    {{ incidente.estado === 'Cerrada' ? 'Ver detalle' : 'Gestionar' }}
                                </button>
                            </td>
                        </tr>

                        <tr v-if="incidentesFiltrados.length === 0">
                            <td colspan="6" class="empty-state">
                                No se encontraron incidentes con esos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="table-footer">
                Mostrando {{ incidentesFiltrados.length }}
                de {{ incidentes.length }} incidentes{{ demo ? ' de demostración' : '' }}.
            </footer>
        </section>
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

.stat-card span {
    color: #718096;
    font-size: 11px;
}

.stat-card strong {
    font-size: 29px;
}

.stat-card small {
    color: #94a0af;
    font-size: 10px;
}

.red { color: #d93636; }
.blue { color: #285bd1; }
.green { color: #198754; }

.incidents-panel {
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
    flex-wrap: wrap;
    gap: 10px;
    padding: 0 22px 20px;
}

.filters input,
.filters select {
    min-width: 130px;
    padding: 10px 12px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    background: white;
    color: #536277;
    font-size: 11px;
}

.filters input {
    flex: 1;
    min-width: 200px;
}

.clear-button {
    padding: 10px 12px;
    border: 0;
    border-radius: 7px;
    background: #eef2f7;
    color: #536277;
    font-size: 11px;
    cursor: pointer;
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
    font-weight: 750;
}

.incidents-table td {
    padding: 15px 16px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 11px;
}

.incidents-table tbody tr:hover {
    background: #fbfdff;
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
    color: #26364a !important;
    font-weight: 650;
}

.pill {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.priority-alta,
.state-pendiente {
    background: #fff0f0;
    color: #cf3434;
}

.priority-media,
.state-recibida {
    background: #fff5df;
    color: #a76c08;
}

.priority-baja,
.state-atendida,
.state-cerrada {
    background: #e8f8ef;
    color: #198754;
}

.state-en-atención {
    background: #eaf1ff;
    color: #285bd1;
}

.detail-button {
    padding: 7px 10px;
    border: 1px solid #d5e1f8;
    border-radius: 6px;
    background: white;
    color: #285bd1;
    font-size: 10px;
    font-weight: 650;
    cursor: pointer;
}

.detail-button:hover {
    background: #eef5ff;
}

.empty-state {
    padding: 35px !important;
    color: #8491a3 !important;
    text-align: center;
}

.table-footer {
    padding: 13px 18px;
    border-top: 1px solid #edf1f6;
    color: #8995a5;
    font-size: 10px;
}

@media (max-width: 1000px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .panel-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 18px;
    }

    .filters {
        padding: 0 15px 16px;
    }

    .filters input {
        min-width: 100%;
    }
}
.incident-views {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0 22px 20px;
}

.incident-view-button {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 13px;
    border: 1px solid #dfe6ef;
    border-radius: 8px;
    background: white;
    color: #536277;
    font-size: 12px;
    font-weight: 600;
}

.incident-view-button > span {
    min-width: 22px;
    padding: 2px 6px;
    border-radius: 5px;
    background: #eef2f7;
    text-align: center;
    font-size: 10px;
}

.incident-view-button:hover {
    background: #f4f7fb;
}

.incident-view-button.selected {
    border-color: #2563eb;
    background: #eef5ff;
    color: #2454bd;
}

.incident-view-button.selected > span {
    background: #dce8ff;
    color: #2454bd;
}

.incident-view-button:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 3px;
}

@media (max-width: 650px) {
    .incident-views {
        padding: 0 15px 16px;
    }

    .incident-view-button {
        flex: 1 1 calc(50% - 8px);
        justify-content: space-between;
    }
}
.priority-por-evaluar {
    background: #eef2f7;
    color: #64748b;
}
</style>
