
<script setup>
import { computed, ref } from 'vue';

const busqueda = ref('');
const filtroEstado = ref('Todos');
const filtroPrioridad = ref('Todas');
const alertaSeleccionada = ref(null);
const emit = defineEmits(['gestionar']);

const alertas = ref([
    {
        folio: 'SIG-2026-001',
        tipo: 'Emergencia médica',
        ubicacion: 'Edificio A · Planta baja',
        fecha: '19/10/2026',
        hora: '10:42',
        reportante: 'Usuario de demostración',
        descripcion: 'Persona que requiere asistencia médica.',
        prioridad: 'Alta',
        estado: 'Pendiente',
    },
    {
        folio: 'SIG-2026-002',
        tipo: 'Incidente de seguridad',
        ubicacion: 'Acceso principal',
        fecha: '19/10/2026',
        hora: '10:28',
        reportante: 'Usuario de demostración',
        descripcion: 'Se solicita revisar una situación en el acceso.',
        prioridad: 'Media',
        estado: 'En atención',
    },
    {
        folio: 'SIG-2026-003',
        tipo: 'Accidente',
        ubicacion: 'Laboratorio 2',
        fecha: '19/10/2026',
        hora: '09:55',
        reportante: 'Usuario de demostración',
        descripcion: 'Se reporta un accidente dentro del laboratorio.',
        prioridad: 'Alta',
        estado: 'Pendiente',
    },
    {
        folio: 'SIG-2026-004',
        tipo: 'Situación de riesgo',
        ubicacion: 'Edificio B · Segundo piso',
        fecha: '19/10/2026',
        hora: '09:30',
        reportante: 'Usuario de demostración',
        descripcion: 'Situación revisada por el personal responsable.',
        prioridad: 'Baja',
        estado: 'Atendida',
    },
    {
        folio: 'SIG-2026-005',
        tipo: 'Emergencia médica',
        ubicacion: 'Cancha deportiva',
        fecha: '19/10/2026',
        hora: '09:12',
        reportante: 'Usuario de demostración',
        descripcion: 'Se solicita apoyo en el área deportiva.',
        prioridad: 'Media',
        estado: 'Cerrada',
    },
]);

const alertasFiltradas = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return alertas.value.filter((alerta) => {
        const coincideTexto = [
            alerta.folio,
            alerta.tipo,
            alerta.ubicacion,
        ].some((valor) => valor.toLowerCase().includes(texto));

        const coincideEstado =
            filtroEstado.value === 'Todos' ||
            alerta.estado === filtroEstado.value;

        const coincidePrioridad =
            filtroPrioridad.value === 'Todas' ||
            alerta.prioridad === filtroPrioridad.value;

        return coincideTexto && coincideEstado && coincidePrioridad;
    });
});

const activas = computed(() =>
    alertas.value.filter((a) =>
        ['Pendiente', 'En atención', 'Recibida'].includes(a.estado)
    ).length
);

const pendientes = computed(() =>
    alertas.value.filter((a) => a.estado === 'Pendiente').length
);

const enAtencion = computed(() =>
    alertas.value.filter((a) => a.estado === 'En atención').length
);

function gestionarAlerta(alerta) {
    alertaSeleccionada.value = null;
    emit('gestionar', alerta);
}


function cambiarEstado(alerta, evento) {
    alerta.estado = evento.target.value;
}

function abrirDetalle(alerta) {
    alertaSeleccionada.value = alerta;
}

function limpiarFiltros() {
    busqueda.value = '';
    filtroEstado.value = 'Todos';
    filtroPrioridad.value = 'Todas';
}
</script>

<template>
    <div class="alerts-page">
        <div class="demo-notice">
            <span class="notice-icon">i</span>
            <span>
                <strong>Modo demostración:</strong>
                las alertas mostradas son ficticias. Los cambios no se guardan
                en una base de datos.
            </span>
        </div>

        <section class="summary-grid">
            <article class="summary-card">
                <span class="summary-label">Alertas activas</span>
                <strong>{{ activas }}</strong>
                <span class="summary-description">Requieren seguimiento</span>
                <span class="summary-symbol red">!</span>
            </article>

            <article class="summary-card">
                <span class="summary-label">Pendientes</span>
                <strong>{{ pendientes }}</strong>
                <span class="summary-description">Sin iniciar atención</span>
                <span class="summary-symbol amber">◷</span>
            </article>

            <article class="summary-card">
                <span class="summary-label">En atención</span>
                <strong>{{ enAtencion }}</strong>
                <span class="summary-description">En proceso</span>
                <span class="summary-symbol blue">↗</span>
            </article>
        </section>

        <section class="alerts-panel">
            <header class="panel-header">
                <div>
                    <h2>Alertas registradas</h2>
                    <p>Consulta y actualiza el seguimiento de cada alerta.</p>
                </div>

                <span class="results-count">
                    {{ alertasFiltradas.length }} resultados
                </span>
            </header>

            <div class="filters">
                <div class="search-box">
                    <span>⌕</span>
                    <input
                        v-model="busqueda"
                        type="search"
                        placeholder="Buscar por folio, incidente o ubicación..."
                    />
                </div>

                <select v-model="filtroEstado" aria-label="Filtrar por estado">
                    <option>Todos</option>
                    <option>Pendiente</option>
                    <option>Recibida</option>
                    <option>En atención</option>
                    <option>Atendida</option>
                    <option>Cerrada</option>
                </select>

                <select
                    v-model="filtroPrioridad"
                    aria-label="Filtrar por prioridad"
                >
                    <option>Todas</option>
                    <option>Alta</option>
                    <option>Media</option>
                    <option>Baja</option>
                </select>

                <button class="clear-button" @click="limpiarFiltros">
                    Limpiar
                </button>
            </div>

            <div class="table-responsive">
                <table class="alerts-table">
                    <thead>
                        <tr>
                            <th>Folio / Hora</th>
                            <th>Tipo de incidente</th>
                            <th>Ubicación</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="alerta in alertasFiltradas" :key="alerta.folio">
                            <td>
                                <strong class="folio">{{ alerta.folio }}</strong>
                                <small>{{ alerta.fecha }} · {{ alerta.hora }}</small>
                            </td>

                            <td class="incident-name">{{ alerta.tipo }}</td>
                            <td>{{ alerta.ubicacion }}</td>

                            <td>
                                <span
                                    class="pill"
                                    :class="'priority-' + alerta.prioridad.toLowerCase()"
                                >
                                    {{ alerta.prioridad }}
                                </span>
                            </td>

                            <td>
                                <span
                                    class="pill"
                                    :class="'state-' + alerta.estado.toLowerCase().replaceAll(' ', '-')"
                                >
                                    {{ alerta.estado }}
                                </span>
                            </td>

                            <td>
                                <button
                                    class="detail-button"
                                    @click="gestionarAlerta(alerta)"
                                >
                                    Gestionar
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
                <span>Mostrando {{ alertasFiltradas.length }} de {{ alertas.length }} alertas de demostración</span>
            </footer>
        </section>

        <div
            v-if="alertaSeleccionada"
            class="detail-overlay"
            @click.self="alertaSeleccionada = null"
        >
            <section
                class="detail-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="alert-detail-title"
            >
                <header class="detail-header">
                    <div>
                        <span class="detail-eyebrow">DETALLE DE ALERTA</span>
                        <h2 id="alert-detail-title">
                            {{ alertaSeleccionada.folio }}
                        </h2>
                    </div>

                    <button
                        class="close-button"
                        aria-label="Cerrar detalle"
                        @click="alertaSeleccionada = null"
                    >
                        ×
                    </button>
                </header>

                <div class="detail-body">
                    <div class="detail-type">
                        <span class="summary-symbol red">!</span>
                        <div>
                            <strong>{{ alertaSeleccionada.tipo }}</strong>
                            <p>{{ alertaSeleccionada.fecha }} · {{ alertaSeleccionada.hora }}</p>
                        </div>
                    </div>

                    <div class="detail-grid">
                        <div>
                            <label>Ubicación</label>
                            <strong>{{ alertaSeleccionada.ubicacion }}</strong>
                        </div>

                        <div>
                            <label>Persona que reporta</label>
                            <strong>{{ alertaSeleccionada.reportante }}</strong>
                        </div>

                        <div>
                            <label>Prioridad</label>
                            <strong>{{ alertaSeleccionada.prioridad }}</strong>
                        </div>

                        <div>
                            <label>Estado actual</label>
                            <strong>{{ alertaSeleccionada.estado }}</strong>
                        </div>
                    </div>

                    <div class="description-box">
                        <label>Descripción del reporte</label>
                        <p>{{ alertaSeleccionada.descripcion }}</p>
                    </div>

                    <div class="state-editor">
                        <label for="estado-alerta">Actualizar estado</label>
                        <select
                            id="estado-alerta"
                            :value="alertaSeleccionada.estado"
                            @change="cambiarEstado(alertaSeleccionada, $event)"
                        >
                            <option>Pendiente</option>
                            <option>Recibida</option>
                            <option>En atención</option>
                            <option>Atendida</option>
                            <option>Cerrada</option>
                        </select>
                    </div>
                </div>

                <footer class="detail-footer">
                    <button
                        class="secondary-button"
                        @click="alertaSeleccionada = null"
                    >
                        Cerrar
                    </button>
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.alerts-page {
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

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.summary-card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 21px;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.summary-label {
    color: #68778b;
    font-size: 12px;
}

.summary-card > strong {
    color: #1b2b40;
    font-size: 30px;
}

.summary-description {
    color: #8a97a7;
    font-size: 11px;
}

.summary-symbol {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 35px;
    height: 35px;
    border-radius: 9px;
    font-size: 18px;
    font-weight: 800;
}

.summary-card > .summary-symbol {
    position: absolute;
    top: 18px;
    right: 18px;
}

.red {
    background: #fff0f0;
    color: #d93636;
}

.amber {
    background: #fff5df;
    color: #b77910;
}

.blue {
    background: #eaf1ff;
    color: #285bd1;
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
    color: #8491a3;
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
    align-items: center;
    gap: 10px;
    padding: 0 22px 20px;
}

.search-box {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 9px;
    min-width: 200px;
    padding: 0 12px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    color: #8491a3;
}

.search-box input {
    width: 100%;
    min-width: 0;
    padding: 10px 0;
    border: 0;
    outline: 0;
    color: #26364a;
    font-size: 11px;
}

.filters select,
.state-editor select {
    padding: 10px 11px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    background: white;
    color: #536277;
    font-size: 11px;
}

.clear-button {
    padding: 10px 12px;
    border: 0;
    border-radius: 7px;
    background: #eef2f7;
    color: #536277;
    font-size: 11px;
}

.clear-button:hover {
    background: #e1e8f1;
}

.alerts-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.alerts-table th {
    padding: 13px 16px;
    background: #f8fafc;
    color: #7f8da0;
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
    color: #94a0af;
    font-size: 10px;
}

.folio {
    color: #285bd1;
    font-weight: 750;
}

.incident-name {
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
.state-cerrada,
.state-atendida {
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

.detail-overlay {
    position: fixed;
    z-index: 1000;
    inset: 0;
    display: grid;
    place-items: center;
    overflow-y: auto;
    padding: 20px;
    background: rgb(15 23 42 / 55%);
}

.detail-modal {
    width: min(100%, 600px);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border-radius: 14px;
    background: white;
    box-shadow: 0 20px 70px rgb(0 0 0 / 20%);
}

.detail-header,
.detail-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 24px;
    border-bottom: 1px solid #edf1f6;
}

.detail-eyebrow {
    color: #8491a3;
    font-size: 9px;
    font-weight: 750;
    letter-spacing: 1px;
}

.detail-header h2 {
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

.detail-body {
    display: grid;
    gap: 22px;
    padding: 24px;
}

.detail-type {
    display: flex;
    align-items: center;
    gap: 12px;
}

.detail-type strong {
    color: #26364a;
    font-size: 14px;
}

.detail-type p {
    margin: 5px 0 0;
    color: #8491a3;
    font-size: 11px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.detail-grid label,
.description-box label,
.state-editor label {
    display: block;
    margin-bottom: 7px;
    color: #8491a3;
    font-size: 10px;
}

.detail-grid strong {
    color: #26364a;
    font-size: 12px;
    font-weight: 650;
}

.description-box {
    padding: 15px;
    border-radius: 8px;
    background: #f8fafc;
}

.description-box p {
    margin: 0;
    color: #536277;
    font-size: 12px;
    line-height: 1.7;
}

.state-editor {
    display: grid;
    gap: 5px;
}

.state-editor select {
    width: 100%;
}

.detail-footer {
    justify-content: flex-end;
    border-top: 1px solid #edf1f6;
    border-bottom: 0;
}

.secondary-button {
    padding: 10px 18px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    background: white;
    color: #536277;
    font-size: 12px;
}

@media (max-width: 760px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .filters {
        padding: 0 15px 16px;
    }

    .panel-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 18px;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>
