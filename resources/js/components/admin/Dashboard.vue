
<script setup>
import { computed } from 'vue';

const props = defineProps({
    incidentes: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['navigate']);

const estadosActivos = ['Pendiente', 'Recibida', 'En atención'];

const indicadores = computed(() => [
    {
        titulo: 'Incidentes activos',
        valor: props.incidentes.filter(
            item => estadosActivos.includes(item.estado)
        ).length,
        detalle: 'Requieren atención o seguimiento',
        color: 'red',
        icono: '!',
    },
    {
        titulo: 'Pendientes',
        valor: props.incidentes.filter(
            item => item.estado === 'Pendiente'
        ).length,
        detalle: 'Esperando recepción',
        color: 'amber',
        icono: '◷',
    },
    {
        titulo: 'En atención',
        valor: props.incidentes.filter(
            item => item.estado === 'En atención'
        ).length,
        detalle: 'Actualmente en proceso',
        color: 'blue',
        icono: '↗',
    },
    {
        titulo: 'Atendidos',
        valor: props.incidentes.filter(
            item => item.estado === 'Atendida'
        ).length,
        detalle: 'Pendientes de cierre',
        color: 'green',
        icono: '✓',
    },
]);

function claveFecha(incidente) {
    const [dia, mes, anio] = incidente.fecha.split('/');

    return `${anio}-${mes}-${dia}T${incidente.hora}`;
}

const incidentes = computed(() =>
    [...props.incidentes]
        .sort((a, b) => claveFecha(b).localeCompare(claveFecha(a)))
        .slice(0, 5)
);

function colorPrioridad(prioridad) {
    if (prioridad === 'Alta') return 'red';
    if (prioridad === 'Media') return 'amber';
    if (prioridad === 'Baja') return 'green';

    return 'neutral';
}

function colorEstado(estado) {
    if (estado === 'Pendiente') return 'red';
    if (estado === 'Recibida') return 'amber';
    if (estado === 'En atención') return 'blue';

    return 'green';
}
</script>

<template>
    <div class="dashboard">
        <section class="welcome">
            <div>
                <h2>Resumen general</h2>
                <p>
                    Consulta el estado de las emergencias y los incidentes
                    registrados en el sistema.
                </p>
            </div>

            <span class="demo-label">DATOS DE DEMOSTRACIÓN</span>
        </section>

        <section class="indicator-grid">
            <article
                v-for="item in indicadores"
                :key="item.titulo"
                class="indicator-card"
            >
                <div class="indicator-top">
                    <span>{{ item.titulo }}</span>
                    <div :class="['indicator-icon', item.color]">
                        {{ item.icono }}
                    </div>
                </div>

                <div class="indicator-value">{{ item.valor }}</div>
                <div class="indicator-detail">{{ item.detalle }}</div>
            </article>
        </section>

        <section class="dashboard-panel">
            <div class="panel-heading">
                <div>
                    <h3>Incidentes recientes</h3>
                    <p>Últimos reportes registrados en SIGEI</p>
                </div>

                <button
                    class="view-all"
                    type="button"
                    @click="emit('navigate', 'incidentes')"
                >
                    Ir a incidentes →
                </button>
            </div>

            <div class="table-responsive">
                <table class="incident-table">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Incidente</th>
                            <th>Ubicación</th>
                            <th>Hora</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in incidentes" :key="item.folio">
                            <td class="folio">{{ item.folio }}</td>
                            <td>{{ item.tipo }}</td>
                            <td>{{ item.ubicacion }}</td>
                            <td>{{ item.hora }}</td>

                            <td>
                                <span
                                    :class="[
                                        'status-badge',
                                        colorPrioridad(item.prioridad),
                                    ]"
                                >
                                    {{ item.prioridad }}
                                </span>
                            </td>

                            <td>
                                <span
                                    :class="[
                                        'status-badge',
                                        colorEstado(item.estado),
                                    ]"
                                >
                                    {{ item.estado }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="incidentes.length === 0">
                            <td colspan="6" class="dashboard-empty">
                                Todavía no hay incidentes registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-note">
                Los registros mostrados son ficticios y solo sirven para
                visualizar la interfaz.
            </div>
        </section>

        <section class="quick-grid">
            <button
                type="button"
                class="quick-card"
                @click="emit('navigate', 'incidentes')"
            >
                <span class="quick-icon blue">▤</span>
                <span>
                    <strong>Gestionar incidentes</strong>
                    <small>Consultar y dar seguimiento</small>
                </span>
                <span class="quick-arrow">→</span>
            </button>

            <button
                type="button"
                class="quick-card"
                @click="emit('navigate', 'usuarios')"
            >
                <span class="quick-icon purple">♙</span>
                <span>
                    <strong>Usuarios y roles</strong>
                    <small>Administrar cuentas y accesos</small>
                </span>
                <span class="quick-arrow">→</span>
            </button>

            <button
                type="button"
                class="quick-card"
                @click="emit('navigate', 'reportes')"
            >
                <span class="quick-icon green">▥</span>
                <span>
                    <strong>Ver reportes</strong>
                    <small>Consultar estadísticas</small>
                </span>
                <span class="quick-arrow">→</span>
            </button>
        </section>
    </div>
</template>

<style scoped>
.dashboard {
    display: grid;
    gap: 24px;
}

.welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.welcome h2 {
    margin: 0 0 7px;
    color: #1b2b40;
    font-size: 20px;
    font-weight: 750;
}

.welcome p,
.panel-heading p {
    margin: 0;
    color: #8491a3;
    font-size: 12px;
    line-height: 1.6;
}

.demo-label {
    flex-shrink: 0;
    padding: 7px 10px;
    border: 1px solid #f3d58b;
    border-radius: 6px;
    background: #fff8e6;
    color: #96630c;
    font-size: 9px;
    font-weight: 750;
    letter-spacing: 0.5px;
}

.indicator-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.indicator-card {
    padding: 21px;
    border: 1px solid #e8edf4;
    border-radius: 12px;
    background: white;
    box-shadow: 0 3px 10px rgb(27 43 64 / 3%);
}

.indicator-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: #68778b;
    font-size: 12px;
}

.indicator-icon,
.quick-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 35px;
    height: 35px;
    border-radius: 9px;
    font-size: 17px;
    font-weight: 700;
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

.green {
    background: #e8f8ef;
    color: #198754;
}

.purple {
    background: #f1ebff;
    color: #7c4bd1;
}

.indicator-value {
    margin-top: 16px;
    color: #1b2b40;
    font-size: 31px;
    font-weight: 750;
}

.indicator-detail {
    margin-top: 5px;
    color: #94a0af;
    font-size: 10px;
}

.dashboard-panel {
    overflow: hidden;
    border: 1px solid #e8edf4;
    border-radius: 12px;
    background: white;
    box-shadow: 0 3px 10px rgb(27 43 64 / 3%);
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px;
}

.panel-heading h3 {
    margin: 0 0 6px;
    color: #1b2b40;
    font-size: 15px;
    font-weight: 750;
}

.view-all {
    border: 0;
    background: transparent;
    color: #285bd1;
    font-size: 11px;
    font-weight: 650;
    white-space: nowrap;
}

.view-all:hover {
    text-decoration: underline;
}

.incident-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.incident-table th {
    padding: 13px 17px;
    background: #f8fafc;
    color: #7f8da0;
    text-align: left;
    font-size: 10px;
    font-weight: 700;
}

.incident-table td {
    padding: 15px 17px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 11px;
}

.incident-table tbody tr:hover {
    background: #fbfdff;
}

.incident-table .folio {
    color: #285bd1;
    font-weight: 700;
}

.status-badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 650;
}

.table-note {
    padding: 12px 18px;
    border-top: 1px solid #edf1f6;
    color: #8995a5;
    font-size: 10px;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.quick-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 17px;
    border: 1px solid #e8edf4;
    border-radius: 10px;
    background: white;
    text-align: left;
    transition: border-color 0.2s, transform 0.2s;
}

.quick-card:hover {
    transform: translateY(-2px);
    border-color: #b7c9ee;
}

.quick-card > span:nth-child(2) {
    display: grid;
    gap: 5px;
    min-width: 0;
}

.quick-card strong {
    color: #26364a;
    font-size: 11px;
}

.quick-card small {
    color: #8995a5;
    font-size: 10px;
}

.quick-arrow {
    margin-left: auto;
    color: #8a98aa;
}

@media (max-width: 1100px) {
    .indicator-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .quick-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .welcome {
        align-items: flex-start;
        flex-direction: column;
    }

    .indicator-grid {
        gap: 10px;
    }

    .indicator-card {
        padding: 15px;
    }

    .indicator-top {
        align-items: flex-start;
    }

    .indicator-top > span {
        max-width: 90px;
    }

    .panel-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}
.dashboard-empty {
    padding: 30px !important;
    color: #8491a3;
    text-align: center;
}
.neutral {
    background: #eef2f7;
    color: #64748b;
}
</style>
