<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    notificaciones: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['abrir', 'leer', 'leer-todas']);

const contenedor = ref(null);
const botonCampana = ref(null);
const abierto = ref(false);
const soloSinLeer = ref(false);

const panelId = 'panel-notificaciones';

const sinLeer = computed(() =>
    props.notificaciones.filter(item => !item.leida).length
);

const visibles = computed(() => {
    const lista = soloSinLeer.value
        ? props.notificaciones.filter(item => !item.leida)
        : props.notificaciones;

    return lista.slice(0, 5);
});

function cerrar(devolverFoco = false) {
    abierto.value = false;

    if (devolverFoco) {
        botonCampana.value?.focus();
    }
}

function marcarTodas() {
    emit('leer-todas');
}

function abrirListado(notificacion) {
    cerrar(true);
    emit('abrir', notificacion.id);
}

function manejarClickExterior(evento) {
    if (!contenedor.value?.contains(evento.target)) {
        cerrar();
    }
}

function manejarTeclado(evento) {
    if (abierto.value && evento.key === 'Escape') {
        cerrar(true);
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', manejarClickExterior);
    document.addEventListener('keydown', manejarTeclado);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', manejarClickExterior);
    document.removeEventListener('keydown', manejarTeclado);
});
</script>

<template>
    <div ref="contenedor" class="notifications">
        <button
            ref="botonCampana"
            type="button"
            class="bell-button"
            :aria-label="`Notificaciones: ${sinLeer} sin leer`"
            :aria-expanded="abierto"
            :aria-controls="panelId"
            @click="abierto = !abierto"
        >
            <svg
                width="21"
                height="21"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                <path d="M10 21h4" />
            </svg>

            <span v-if="sinLeer" class="bell-count" aria-hidden="true">
                {{ sinLeer > 9 ? '9+' : sinLeer }}
            </span>
        </button>

        <section
            v-if="abierto"
            :id="panelId"
            class="notifications-panel"
            :aria-labelledby="`${panelId}-titulo`"
        >
            <header class="notifications-heading">
                <div>
                    <h2 :id="`${panelId}-titulo`">Notificaciones</h2>
                    <p>{{ sinLeer }} sin leer</p>
                </div>

                <button
                    type="button"
                    class="close-button"
                    aria-label="Cerrar notificaciones"
                    @click="cerrar(true)"
                >
                    ×
                </button>
            </header>

            <div class="notifications-tools">
                <label class="unread-filter">
                    <input v-model="soloSinLeer" type="checkbox" />
                    Solo sin leer
                </label>

                <button
                    type="button"
                    class="text-button"
                    :disabled="sinLeer === 0"
                    @click="marcarTodas"
                >
                    Leer todas
                </button>
            </div>

            <ul v-if="visibles.length" class="notifications-list">
                <li
                    v-for="item in visibles"
                    :key="item.id"
                    :class="{ unread: !item.leida }"
                >
                    <div class="notification-title">
                        <strong>{{ item.titulo }}</strong>
                        <span v-if="!item.leida" class="unread-label">
                            Sin leer
                        </span>
                    </div>

                    <p>{{ item.descripcion }}</p>

                    <span class="notification-folio">{{ item.folio }}</span>
                    <small>{{ item.fecha }}</small>

                    <div class="notification-actions">
                        <button
                            type="button"
                            class="text-button"
                            @click="abrirListado(item)"
                        >
                        Ver incidente
                        </button>

                        <button
                            v-if="!item.leida"
                            type="button"
                            class="read-button"
                            :aria-label="`Marcar como leída: ${item.titulo}, ${item.folio}`"
                            @click="emit('leer', item.id)"
                        >
                            Marcar leída
                        </button>
                    </div>
                </li>
            </ul>

            <div v-else class="empty-notifications" role="status">
                {{
                    soloSinLeer
                        ? 'No tienes notificaciones recientes sin leer.'
                        : 'No hay notificaciones recientes.'
                }}
            </div>

            <footer class="notifications-footer">
                Hasta 5 avisos recientes de esta cuenta · Demostración
            </footer>
        </section>
    </div>
</template>

<style scoped>
.notifications {
    position: relative;
    flex-shrink: 0;
}

.bell-button {
    position: relative;
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: white;
    color: #536277;
    cursor: pointer;
}

.bell-button:hover {
    background: #eef5ff;
    color: #2563eb;
}

.bell-count {
    position: absolute;
    top: -5px;
    right: -5px;
    display: grid;
    place-items: center;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    box-sizing: border-box;
    border: 2px solid white;
    border-radius: 20px;
    background: #dc3545;
    color: white;
    font-size: 9px;
    font-weight: 700;
}

.notifications-panel {
    position: absolute;
    z-index: 1100;
    top: calc(100% + 12px);
    right: 0;
    width: min(370px, calc(100vw - 40px));
    max-height: min(580px, 70dvh);
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
    color: #26364a;
    box-shadow: 0 14px 45px rgb(15 23 42 / 16%);
    text-align: left;
}

.notifications-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 17px;
}

.notifications-heading h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 750;
}

.notifications-heading p {
    margin: 5px 0 0;
    color: #718096;
    font-size: 11px;
}

.close-button {
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 23px;
    cursor: pointer;
}

.notifications-tools {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 17px;
    border-top: 1px solid #edf1f6;
    border-bottom: 1px solid #edf1f6;
}

.unread-filter {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #64748b;
    font-size: 11px;
    cursor: pointer;
}

.unread-filter input {
    accent-color: #2563eb;
}

.text-button,
.read-button {
    padding: 4px 0;
    border: 0;
    background: transparent;
    font-size: 11px;
    cursor: pointer;
}

.text-button {
    color: #2563eb;
    font-weight: 650;
}

.text-button:disabled {
    color: #94a3b8;
    cursor: default;
}

.read-button {
    color: #64748b;
}

.notifications-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.notifications-list li {
    padding: 17px;
    border-bottom: 1px solid #edf1f6;
}

.notifications-list li.unread {
    background: #f3f7ff;
}

.notification-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.notification-title strong {
    font-size: 12px;
}

.unread-label {
    flex-shrink: 0;
    color: #2563eb;
    font-size: 9px;
    font-weight: 700;
}

.notifications-list p {
    margin: 8px 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
}

.notification-folio {
    display: block;
    color: #285bd1;
    font-size: 11px;
    font-weight: 650;
}

.notifications-list small {
    display: block;
    margin-top: 5px;
    color: #8491a3;
    font-size: 10px;
}

.notification-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
}

.empty-notifications {
    padding: 32px 20px;
    color: #718096;
    text-align: center;
    font-size: 12px;
    line-height: 1.7;
}

.notifications-footer {
    padding: 12px 17px;
    background: #f8fafc;
    color: #8491a3;
    font-size: 10px;
}

button:focus-visible,
input:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 3px;
}

@media (max-width: 760px) {
    .notifications-panel {
        position: fixed;
        top: auto;
        right: 20px;
        margin-top: 12px;
        max-height: 65dvh;
    }
}
</style>