<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    real: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    configuracion: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['guardar', 'recargar']);

function copiar(configuracion) {
    return {
        ...configuracion,
        tipos: configuracion.tipos.map(item => typeof item === 'string' ? item : { ...item }),
        ubicaciones: configuracion.ubicaciones.map(item => typeof item === 'string' ? item : { ...item }),
    };
}

const guardado = ref(copiar(props.configuracion));
const formulario = ref(copiar(guardado.value));
const procesando = ref(false);
const bloqueado = computed(() => props.saving || procesando.value);

const nuevoTipo = ref('');
const nuevaUbicacion = ref('');
const error = ref('');
const mensaje = ref('');

const hayCambios = computed(() =>
    JSON.stringify(formulario.value) !==
    JSON.stringify(guardado.value)
);
const hayVersionNueva = computed(() => props.real && props.configuracion.version !== guardado.value.version);

watch(() => props.configuracion, nueva => {
    if (!hayCambios.value && !procesando.value) {
        guardado.value = copiar(nueva);
        formulario.value = copiar(nueva);
    }
});

function limpiarMensajes() {
    error.value = '';
    mensaje.value = '';
}

function normalizar(texto) {
    return texto.trim().toLocaleLowerCase('es');
}

function agregarElemento(catalogo, campo) {
    if (bloqueado.value) return;
    limpiarMensajes();

    const entrada = campo === 'tipo' ? nuevoTipo : nuevaUbicacion;
    const texto = entrada.value.trim();

    if (!texto) {
        error.value = 'Escribe un nombre antes de agregarlo.';
        return;
    }

    const limite = props.real && catalogo === 'ubicaciones' ? 150 : 100;
    if ([...texto].length > limite) {
        error.value = `El nombre debe tener como máximo ${limite} caracteres.`;
        return;
    }

    const repetido = formulario.value[catalogo].some(
        item => normalizar(props.real ? item.nombre : item) === normalizar(texto)
    );

    if (repetido) {
        error.value = props.real ? 'Ese nombre ya existe. Revisa también las opciones inactivas.' : 'Ese nombre ya existe en el catálogo.';
        return;
    }

    if (props.real && formulario.value[catalogo].length >= 200) {
        error.value = 'El catálogo admite hasta 200 opciones. Edita o reactiva una opción existente.';
        return;
    }
    formulario.value[catalogo].push(props.real
        ? { id: null, nombre: texto, activo: true, ...(catalogo === 'ubicaciones' ? { requiereDetalle: false } : {}) }
        : texto);
    entrada.value = '';
}

function eliminarElemento(catalogo, indice) {
    if (bloqueado.value) return;
    limpiarMensajes();

    if (formulario.value[catalogo].length === 1) {
        error.value = 'Conserva al menos una opción en cada catálogo.';
        return;
    }

    formulario.value[catalogo].splice(indice, 1);
}

function cambiarActividad(catalogo, indice) {
    if (bloqueado.value) return;
    limpiarMensajes();
    const elemento = formulario.value[catalogo][indice];
    if (elemento.activo && formulario.value[catalogo].filter(item => item.activo).length <= 1) {
        error.value = 'Conserva al menos una opción activa en cada catálogo.';
        return;
    }
    if (elemento.id === null) formulario.value[catalogo].splice(indice, 1);
    else elemento.activo = !elemento.activo;
}

function textoActividad(elemento) {
    return elemento.id === null ? 'Quitar nuevo' : (elemento.activo ? 'Desactivar' : 'Reactivar');
}

function guardarConfiguracion() {
    if (bloqueado.value) return;
    limpiarMensajes();

    const institucion = formulario.value.institucion.trim();
    const siglas = formulario.value.siglas.trim();

    if (!institucion || !siglas) {
        error.value = 'Completa el nombre de la institución y las siglas.';
        return;
    }

    if (nuevoTipo.value.trim() || nuevaUbicacion.value.trim()) {
        error.value = 'Agrega o borra el texto pendiente en los catálogos antes de guardar.';
        return;
    }

    if (
        !formulario.value.tipos.length ||
        !formulario.value.ubicaciones.length
    ) {
        error.value = 'Cada catálogo debe tener al menos una opción.';
        return;
    }

    formulario.value.institucion = institucion;
    formulario.value.siglas = siglas;

    if (props.real) {
        for (const catalogo of ['tipos', 'ubicaciones']) {
            const nombres = new Set();
            if (!formulario.value[catalogo].some(item => item.activo)) {
                error.value = 'Conserva al menos una opción activa en cada catálogo.';
                return;
            }
            for (const item of formulario.value[catalogo]) {
                item.nombre = item.nombre.trim();
                if (!item.nombre || nombres.has(normalizar(item.nombre))) {
                    error.value = 'Revisa los nombres vacíos o repetidos, también entre las opciones inactivas.';
                    return;
                }
                nombres.add(normalizar(item.nombre));
            }
        }
    }

    procesando.value = true;
    emit('guardar', copiar(formulario.value), resultado => {
        procesando.value = false;
        if (!resultado?.ok) {
            error.value = resultado?.error ?? 'No se pudo guardar la configuración.';
            return;
        }

        guardado.value = copiar(resultado.data ?? formulario.value);
        formulario.value = copiar(guardado.value);
        mensaje.value = props.real
            ? 'Configuración guardada correctamente.'
            : 'Configuración aplicada durante esta sesión de demostración.';
    });
}

function descartarCambios() {
    if (bloqueado.value) return;
    if (props.real) {
        procesando.value = true;
        limpiarMensajes();
        emit('recargar', resultado => {
            procesando.value = false;
            if (!resultado?.ok) {
                error.value = resultado?.error ?? 'No se pudo recargar. Tus cambios se conservaron.';
                return;
            }
            restablecerFormulario(resultado.data);
        });
        return;
    }
    restablecerFormulario(props.configuracion);
}

function restablecerFormulario(datos) {
    guardado.value = copiar(datos);
    formulario.value = copiar(guardado.value);
    nuevoTipo.value = '';
    nuevaUbicacion.value = '';
    limpiarMensajes();

    mensaje.value = 'Se recuperó la última configuración guardada.';
}
</script>

<template>
    <fieldset class="settings-page settings-fields" :disabled="bloqueado" :aria-busy="bloqueado" aria-label="Configuración de la institución">
        <div v-if="!real" class="demo-notice">
            <strong>Modo demostración:</strong>
            al guardar, los catálogos se aplican al botón de pánico
            y las preferencias controlan los nuevos avisos de la campana.
            La configuración se pierde al recargar.
        </div>
        <div v-else class="demo-notice">
            Los cambios guardados se aplican a los nuevos reportes y avisos de la institución.
            Desactivar una opción la retira del formulario público y conserva los incidentes anteriores.
        </div>
        <div v-if="hayVersionNueva" class="error-message" role="status">
            Otra persona guardó cambios en la configuración. Conserva tus ediciones antes de pulsar «Descartar y recargar».
        </div>

        <section class="settings-panel">
            <header class="panel-heading">
                <span class="section-icon">⚙</span>
                <div>
                    <h2>Datos de la institución</h2>
                    <p>Información general para identificar el sistema.</p>
                </div>
            </header>

            <div class="general-fields">
                <div class="form-field">
                    <label for="config-institucion">
                        Nombre de la institución *
                    </label>
                    <input
                        id="config-institucion"
                        v-model="formulario.institucion"
                        type="text"
                        maxlength="150"
                        required
                        @input="limpiarMensajes"
                    />
                </div>

                <div class="form-field">
                    <label for="config-siglas">Siglas del sistema *</label>
                    <input
                        id="config-siglas"
                        v-model="formulario.siglas"
                        type="text"
                        maxlength="20"
                        required
                        @input="limpiarMensajes"
                    />
                </div>
            </div>
        </section>

        <div class="catalog-grid">
            <section class="settings-panel">
                <header class="panel-heading">
                    <span class="section-icon">!</span>
                    <div>
                        <h2>Tipos de emergencia</h2>
                        <p>Categorías previstas para nuevos reportes.</p>
                    </div>
                </header>

                <div class="catalog-body">
                    <form
                        class="add-form"
                        @submit.prevent="agregarElemento('tipos', 'tipo')"
                    >
                        <div class="form-field">
                            <label for="nuevo-tipo">Nuevo tipo</label>
                            <input
                                id="nuevo-tipo"
                                v-model="nuevoTipo"
                                type="text"
                                maxlength="100"
                                placeholder="Escribe una categoría..."
                                required
                                @input="limpiarMensajes"
                            />
                        </div>

                        <button type="submit" class="secondary-button">
                            Agregar
                        </button>
                    </form>

                    <ul class="catalog-list">
                        <li
                            v-for="(tipo, indice) in formulario.tipos"
                            :key="real ? (tipo.id ?? `nuevo-${indice}`) : tipo"
                            :class="{ 'catalog-entry-row': real, 'catalog-inactive': real && !tipo.activo }"
                        >
                            <div v-if="real" class="form-field catalog-entry">
                                <label :for="`config-tipo-${indice}`">Tipo {{ indice + 1 }} · {{ tipo.activo ? 'Activo' : 'Inactivo' }}</label>
                                <input :id="`config-tipo-${indice}`" v-model="tipo.nombre" type="text" maxlength="100" @input="limpiarMensajes" />
                            </div>
                            <span v-else>{{ tipo }}</span>

                            <button
                                type="button"
                                class="remove-button"
                                :aria-label="real ? `${textoActividad(tipo)} ${tipo.nombre}` : `Quitar ${tipo}`"
                                @click="real ? cambiarActividad('tipos', indice) : eliminarElemento('tipos', indice)"
                            >
                                {{ real ? textoActividad(tipo) : 'Quitar' }}
                            </button>
                        </li>
                    </ul>

                    <small class="catalog-count">
                        {{ real ? formulario.tipos.filter(item => item.activo).length + ' activos de ' : '' }}{{ formulario.tipos.length }} tipos configurados
                    </small>
                </div>
            </section>

            <section class="settings-panel">
                <header class="panel-heading">
                    <span class="section-icon">⌖</span>
                    <div>
                        <h2>Ubicaciones</h2>
                        <p>Edificios, zonas y áreas de la institución.</p>
                    </div>
                </header>

                <div class="catalog-body">
                    <form
                        class="add-form"
                        @submit.prevent="
                            agregarElemento('ubicaciones', 'ubicacion')
                        "
                    >
                        <div class="form-field">
                            <label for="nueva-ubicacion">Nueva ubicación</label>
                            <input
                                id="nueva-ubicacion"
                                v-model="nuevaUbicacion"
                                type="text"
                                :maxlength="real ? 150 : 100"
                                placeholder="Ej. Edificio D · Planta baja"
                                required
                                @input="limpiarMensajes"
                            />
                        </div>

                        <button type="submit" class="secondary-button">
                            Agregar
                        </button>
                    </form>

                    <ul class="catalog-list">
                        <li
                            v-for="(ubicacion, indice) in formulario.ubicaciones"
                            :key="real ? (ubicacion.id ?? `nueva-${indice}`) : ubicacion"
                            :class="{ 'catalog-entry-row': real, 'catalog-inactive': real && !ubicacion.activo }"
                        >
                            <div v-if="real" class="form-field catalog-entry">
                                <label :for="`config-ubicacion-${indice}`">Ubicación {{ indice + 1 }} · {{ ubicacion.activo ? 'Activa' : 'Inactiva' }}</label>
                                <input :id="`config-ubicacion-${indice}`" v-model="ubicacion.nombre" type="text" maxlength="150" @input="limpiarMensajes" />
                                <label class="detail-option">
                                    <input v-model="ubicacion.requiereDetalle" type="checkbox" @change="limpiarMensajes" />
                                    Pedir que se especifique el lugar
                                </label>
                            </div>
                            <span v-else>{{ ubicacion }}</span>

                            <button
                                type="button"
                                class="remove-button"
                                :aria-label="real ? `${textoActividad(ubicacion)} ${ubicacion.nombre}` : `Quitar ${ubicacion}`"
                                @click="real ? cambiarActividad('ubicaciones', indice) : eliminarElemento('ubicaciones', indice)"
                            >
                                {{ real ? textoActividad(ubicacion) : 'Quitar' }}
                            </button>
                        </li>
                    </ul>

                    <small class="catalog-count">
                        {{ real ? formulario.ubicaciones.filter(item => item.activo).length + ' activas de ' : '' }}{{ formulario.ubicaciones.length }}
                        ubicaciones configuradas
                    </small>
                </div>
            </section>
        </div>

        <section class="settings-panel">
            <header class="panel-heading">
                <span class="section-icon">♧</span>
                <div>
                    <h2>{{ real ? 'Preferencias de avisos' : 'Notificaciones dentro del sistema' }}</h2>
                    <p>
                        {{ real ? 'Estas preferencias se aplican a toda la institución y controlan los nuevos avisos de la campana y los envíos Web Push a dispositivos vinculados. Los avisos anteriores se conservan.' : 'Preferencias previstas para la campana de avisos, según los permisos de cada rol.' }}
                    </p>
                </div>
            </header>

            <div class="notification-list">
                <label class="notification-option">
                    <span>
                        <strong>Nuevos incidentes</strong>
                        <small>
                            Avisar al Administrador cuando se registre un caso.
                        </small>
                    </span>

                    <input
                        v-model="formulario.notificarNuevos"
                        type="checkbox"
                        @change="limpiarMensajes"
                    />
                </label>

                <label class="notification-option">
                    <span>
                        <strong>Asignación de responsables</strong>
                        <small>
                            Avisar al Responsable cuando se le asigne un caso.
                        </small>
                    </span>

                    <input
                        v-model="formulario.notificarAsignaciones"
                        type="checkbox"
                        @change="limpiarMensajes"
                    />
                </label>

                <label class="notification-option">
                    <span>
                        <strong>Cambios de seguimiento</strong>
                        <small>
                            Avisar al Administrador, al Responsable asignado y a la cuenta
                            que reportó el incidente cuando se registre una actualización.
                        </small>
                    </span>

                    <input
                        v-model="formulario.notificarSeguimiento"
                        type="checkbox"
                        @change="limpiarMensajes"
                    />
                </label>
            </div>
        </section>

        <div v-if="error" class="error-message" role="alert">
            {{ error }}
        </div>

        <div v-if="mensaje" class="feedback" role="status">
            {{ mensaje }}
        </div>

        <footer class="settings-actions">
            <span role="status">
                {{ hayCambios ? 'Tienes cambios sin guardar' : 'Sin cambios pendientes' }}
            </span>

            <div class="action-buttons">
                <button
                    type="button"
                    class="secondary-button"
                    @click="descartarCambios"
                >
                    {{ real ? 'Descartar y recargar' : 'Descartar cambios' }}
                </button>

                <button
                    type="button"
                    class="primary-button"
                    @click="guardarConfiguracion"
                >
                    {{ bloqueado ? 'Procesando…' : 'Guardar configuración' }}
                </button>
            </div>
        </footer>
    </fieldset>
</template>

<style scoped>
.settings-page {
    display: grid;
    gap: 22px;
    color: #26364a;
}

.settings-fields {
    display: grid;
    gap: 22px;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.catalog-entry {
    flex: 1;
}

.catalog-inactive {
    background: #f8fafc;
}

.detail-option {
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.6;
}

.form-field .detail-option input {
    width: 16px;
    height: 16px;
    accent-color: #2563eb;
}

button:disabled {
    opacity: .65;
    cursor: wait;
}

.demo-notice,
.feedback,
.error-message {
    padding: 14px 17px;
    border: 1px solid #cbdcfb;
    border-radius: 9px;
    background: #eef5ff;
    color: #415b80;
    font-size: 12px;
    line-height: 1.7;
}

.error-message {
    border-color: #fecaca;
    background: #fff0f0;
    color: #b91c1c;
}

.feedback {
    border-color: #b9e6cc;
    background: #e8f8ef;
    color: #198754;
}

.settings-panel {
    overflow: hidden;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.panel-heading {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    padding: 22px;
}

.section-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: #eaf1ff;
    color: #285bd1;
    font-size: 20px;
    font-weight: 700;
}

.panel-heading h2 {
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 750;
}

.panel-heading p {
    margin: 0;
    color: #718096;
    font-size: 12px;
    line-height: 1.6;
}

.general-fields {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 18px;
    padding: 0 22px 22px;
}

.form-field {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.form-field label {
    color: #64748b;
    font-size: 12px;
}

.form-field input {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    background: white;
    color: #26364a;
    font: inherit;
    font-size: 12px;
}

.catalog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    align-items: start;
}

.catalog-body {
    padding: 0 22px 22px;
}

.add-form {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.add-form .form-field {
    flex: 1;
}

.primary-button,
.secondary-button {
    padding: 11px 13px;
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

.primary-button:hover {
    background: #1d4ed8;
}

.secondary-button {
    border: 1px solid #dfe6ef;
    background: #f8fafc;
    color: #536277;
}

.catalog-list {
    display: grid;
    gap: 0;
    margin: 20px 0 12px;
    padding: 0;
    list-style: none;
}

.catalog-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #edf1f6;
    font-size: 12px;
}

.catalog-list li > span {
    overflow-wrap: anywhere;
}

.remove-button {
    flex-shrink: 0;
    padding: 6px 8px;
    border: 1px solid #fecaca;
    border-radius: 6px;
    background: #fff7f7;
    color: #b91c1c;
    font-size: 11px;
    cursor: pointer;
}

.catalog-count {
    color: #8491a3;
    font-size: 11px;
}

.notification-list {
    padding: 0 22px 10px;
}

.notification-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 17px 0;
    border-top: 1px solid #edf1f6;
    cursor: pointer;
}

.notification-option strong,
.notification-option small {
    display: block;
}

.notification-option strong {
    font-size: 12px;
}

.notification-option small {
    margin-top: 6px;
    color: #718096;
    font-size: 11px;
    line-height: 1.6;
}

.notification-option input {
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    accent-color: #2563eb;
    cursor: pointer;
}

.settings-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 22px;
    border: 1px solid #e6ebf2;
    border-radius: 10px;
    background: white;
}

.settings-actions > span {
    color: #718096;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

button:focus-visible,
input:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 3px;
}

@media (max-width: 1000px) {
    .catalog-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .catalog-entry-row {
        align-items: stretch;
        flex-direction: column;
    }
    .general-fields {
        grid-template-columns: 1fr;
    }

    .settings-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .action-buttons {
        flex-direction: column-reverse;
    }

    .add-form {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
