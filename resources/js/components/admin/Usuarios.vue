<script setup>
import { computed, ref, toRef } from 'vue';

const props = defineProps({
    usuarios: {
        type: Array,
        default: () => [],
    },
});

const usuarios = toRef(props, 'usuarios');



const busqueda = ref('');
const filtroRol = ref('');
const filtroEstado = ref('');

const mostrarFormulario = ref(false);
const editandoId = ref(null);
const error = ref('');
const mensaje = ref('');

function formularioVacio() {
    return {
        nombre: '',
        correo: '',
        rol: 'Responsable',
        area: '',
        activo: true,
    };
}

const formulario = ref(formularioVacio());

const activos = computed(
    () => usuarios.value.filter(usuario => usuario.activo).length
);

const responsables = computed(
    () => usuarios.value.filter(
        usuario => usuario.rol === 'Responsable'
    ).length
);

const usuariosFiltrados = computed(() => {
    const texto = busqueda.value.trim().toLocaleLowerCase('es');

    return usuarios.value.filter((usuario) => {
        const coincideTexto = [
            usuario.nombre,
            usuario.correo,
            usuario.area,
        ].some(valor =>
            valor.toLocaleLowerCase('es').includes(texto)
        );

        const coincideRol =
            !filtroRol.value || usuario.rol === filtroRol.value;

        const coincideEstado =
            !filtroEstado.value ||
            usuario.activo === (filtroEstado.value === 'activo');

        return coincideTexto && coincideRol && coincideEstado;
    });
});

function limpiarFiltros() {
    busqueda.value = '';
    filtroRol.value = '';
    filtroEstado.value = '';
}

function nuevoUsuario() {
    editandoId.value = null;
    formulario.value = formularioVacio();
    error.value = '';
    mensaje.value = '';
    mostrarFormulario.value = true;
}

function editarUsuario(usuario) {
    editandoId.value = usuario.id;
    formulario.value = {
        nombre: usuario.nombre,
        correo: usuario.correo,
        rol: usuario.rol,
        area: usuario.area,
        activo: usuario.activo,
    };

    error.value = '';
    mensaje.value = '';
    mostrarFormulario.value = true;
}

function cerrarFormulario() {
    mostrarFormulario.value = false;
    error.value = '';
}

function esUltimoAdministradorActivo(usuario) {
    return (
        usuario.rol === 'Administrador' &&
        usuario.activo &&
        !usuarios.value.some(otro =>
            otro.id !== usuario.id &&
            otro.rol === 'Administrador' &&
            otro.activo
        )
    );
}

function guardarUsuario() {
    error.value = '';

    const datos = {
        nombre: formulario.value.nombre.trim(),
        correo: formulario.value.correo.trim().toLowerCase(),
        rol: formulario.value.rol,
        area: formulario.value.area.trim(),
        activo: formulario.value.activo,
    };

    if (!datos.nombre || !datos.correo || !datos.area) {
        error.value = 'Completa el nombre, correo y área.';
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.correo)) {
        error.value = 'Escribe un correo válido.';
        return;
    }

    if (!['Administrador', 'Responsable'].includes(datos.rol)) {
        error.value = 'Selecciona un rol válido.';
        return;
    }

    const correoRepetido = usuarios.value.some(usuario =>
        usuario.id !== editandoId.value &&
        usuario.correo.toLowerCase() === datos.correo
    );

    if (correoRepetido) {
        error.value = 'Ese correo ya está registrado.';
        return;
    }

    if (editandoId.value !== null) {
        const usuario = usuarios.value.find(
            item => item.id === editandoId.value
        );

        if (!usuario) {
            error.value = 'No se encontró el usuario.';
            return;
        }

        if (
            esUltimoAdministradorActivo(usuario) &&
            (datos.rol !== 'Administrador' || !datos.activo)
        ) {
            error.value = 'Debe permanecer al menos un administrador activo.';
            return;
        }

        Object.assign(usuario, datos);
        mensaje.value = 'Usuario actualizado en la demostración.';
    } else {
        const siguienteId = Math.max(
            0,
            ...usuarios.value.map(usuario => usuario.id)
        ) + 1;

        usuarios.value.push({
            id: siguienteId,
            ...datos,
        });

        mensaje.value = 'Usuario agregado en la demostración.';
    }

    cerrarFormulario();
}

function cambiarEstado(usuario) {
    if (esUltimoAdministradorActivo(usuario)) {
        mensaje.value = 'No puedes desactivar al último administrador activo.';
        return;
    }

    usuario.activo = !usuario.activo;

    mensaje.value = usuario.activo
        ? 'Usuario activado en la demostración.'
        : 'Usuario desactivado en la demostración.';
}
</script>

<template>
    <div class="users-page">
        <div class="demo-notice">
            <strong>Modo demostración:</strong>
            estas cuentas son ficticias y no permiten iniciar sesión.
            Los cambios se pierden al recargar la aplicación.
        </div>

        <section class="stats-grid" aria-label="Resumen de usuarios">
            <article class="stat-card">
                <span>Total de usuarios</span>
                <strong>{{ usuarios.length }}</strong>
                <small>Cuentas registradas</small>
            </article>

            <article class="stat-card">
                <span>Activos</span>
                <strong class="green">{{ activos }}</strong>
                <small>Cuentas habilitadas</small>
            </article>

            <article class="stat-card">
                <span>Responsables</span>
                <strong class="blue">{{ responsables }}</strong>
                <small>Personal de atención</small>
            </article>
        </section>

        <div v-if="mensaje" class="feedback" role="status">
            {{ mensaje }}
        </div>

        <section class="users-panel">
            <header class="panel-heading">
                <div>
                    <h2>Usuarios y roles</h2>
                    <p>Administra las cuentas del personal institucional.</p>
                </div>

                <button
                    type="button"
                    class="primary-button"
                    @click="nuevoUsuario"
                >
                    + Agregar usuario
                </button>
            </header>

            <div class="filters">
                <input
                    v-model="busqueda"
                    type="search"
                    aria-label="Buscar usuarios"
                    placeholder="Buscar por nombre, correo o área..."
                />

                <select v-model="filtroRol" aria-label="Filtrar por rol">
                    <option value="">Todos los roles</option>
                    <option>Administrador</option>
                    <option>Responsable</option>
                </select>

                <select
                    v-model="filtroEstado"
                    aria-label="Filtrar por estado"
                >
                    <option value="">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>

                <button
                    type="button"
                    class="secondary-button"
                    @click="limpiarFiltros"
                >
                    Limpiar
                </button>
            </div>

            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th scope="col">Usuario</th>
                            <th scope="col">Rol</th>
                            <th scope="col">Área</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="usuario in usuariosFiltrados"
                            :key="usuario.id"
                        >
                            <td>
                                <strong>{{ usuario.nombre }}</strong>
                                <small>{{ usuario.correo }}</small>
                            </td>

                            <td>
                                <span
                                    class="badge-role"
                                    :class="{
                                        administrator:
                                            usuario.rol === 'Administrador',
                                    }"
                                >
                                    {{ usuario.rol }}
                                </span>
                            </td>

                            <td>{{ usuario.area }}</td>

                            <td>
                                <span
                                    class="badge-state"
                                    :class="{
                                        active: usuario.activo,
                                        inactive: !usuario.activo,
                                    }"
                                >
                                    {{ usuario.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="edit-button"
                                        :aria-label="`Editar a ${usuario.nombre}`"
                                        @click="editarUsuario(usuario)"
                                    >
                                        Editar
                                    </button>

                                    <button
                                        type="button"
                                        class="secondary-button"
                                        :aria-label="`${usuario.activo ? 'Desactivar' : 'Activar'} a ${usuario.nombre}`"
                                        @click="cambiarEstado(usuario)"
                                    >
                                        {{ usuario.activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="usuariosFiltrados.length === 0">
                            <td colspan="5" class="empty-state">
                                No se encontraron usuarios con esos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="table-footer">
                Mostrando {{ usuariosFiltrados.length }}
                de {{ usuarios.length }} usuarios.
            </footer>
        </section>

        <section class="roles-note">
            <strong>Permisos previstos por rol</strong>
            <p>
                <b>Administrador:</b> gestión de incidentes, usuarios,
                reportes y configuración.
            </p>
            <p>
                <b>Responsable:</b> consulta de casos asignados,
                atención y seguimiento.
            </p>
        </section>

        <div v-if="mostrarFormulario" class="form-overlay">
            <section
                class="user-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="user-form-title"
                @keydown.esc="cerrarFormulario"
            >
                <header class="dialog-heading">
                    <div>
                        <span class="eyebrow">CUENTA INSTITUCIONAL</span>
                        <h2 id="user-form-title">
                            {{ editandoId !== null ? 'Editar usuario' : 'Agregar usuario' }}
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="close-button"
                        aria-label="Cerrar formulario"
                        @click="cerrarFormulario"
                    >
                        ×
                    </button>
                </header>

                <form @submit.prevent="guardarUsuario">
                    <div class="form-fields">
                        <div class="form-field">
                            <label for="usuario-nombre">Nombre completo *</label>
                            <input
                                id="usuario-nombre"
                                v-model="formulario.nombre"
                                type="text"
                                autocomplete="off"
                                maxlength="150"
                                required
                            />
                        </div>

                        <div class="form-field">
                            <label for="usuario-correo">Correo electrónico *</label>
                            <input
                                id="usuario-correo"
                                v-model="formulario.correo"
                                type="email"
                                autocomplete="off"
                                maxlength="254"
                                required
                            />
                        </div>

                        <div class="form-field">
                            <label for="usuario-rol">Rol *</label>
                            <select
                                id="usuario-rol"
                                v-model="formulario.rol"
                                required
                            >
                                <option>Administrador</option>
                                <option>Responsable</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="usuario-area">Área o departamento *</label>
                            <input
                                id="usuario-area"
                                v-model="formulario.area"
                                type="text"
                                maxlength="100"
                                placeholder="Ej. Seguridad, Atención médica..."
                                required
                            />
                        </div>

                        <label class="checkbox-field">
                            <input
                                v-model="formulario.activo"
                                type="checkbox"
                            />
                            Cuenta activa
                        </label>

                        <p class="form-note">
                            En esta demostración no se crean contraseñas
                            ni credenciales de acceso reales.
                        </p>

                        <div v-if="error" class="error-message" role="alert">
                            {{ error }}
                        </div>
                    </div>

                    <footer class="dialog-footer">
                        <button
                            type="button"
                            class="secondary-button"
                            @click="cerrarFormulario"
                        >
                            Cancelar
                        </button>

                        <button type="submit" class="primary-button">
                            {{ editandoId !== null ? 'Guardar cambios' : 'Agregar usuario' }}
                        </button>
                    </footer>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
.users-page {
    display: grid;
    gap: 22px;
    color: #26364a;
}

.demo-notice,
.feedback,
.roles-note {
    padding: 14px 17px;
    border: 1px solid #cbdcfb;
    border-radius: 9px;
    background: #eef5ff;
    color: #415b80;
    font-size: 12px;
    line-height: 1.7;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
    font-size: 12px;
}

.stat-card strong {
    font-size: 29px;
}

.green {
    color: #198754;
}

.blue {
    color: #285bd1;
}

.users-panel {
    overflow: hidden;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: white;
}

.panel-heading,
.dialog-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px;
}

.panel-heading h2,
.dialog-heading h2 {
    margin: 0 0 6px;
    font-size: 17px;
    font-weight: 750;
}

.panel-heading p {
    margin: 0;
    color: #8491a3;
    font-size: 12px;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0 22px 20px;
}

.filters input,
.filters select,
.form-field input,
.form-field select {
    min-width: 0;
    padding: 11px 12px;
    border: 1px solid #dfe6ef;
    border-radius: 7px;
    background: white;
    color: #536277;
    font: inherit;
    font-size: 12px;
}

.filters input {
    flex: 1;
    min-width: 200px;
}

button {
    cursor: pointer;
}

.primary-button,
.secondary-button,
.edit-button {
    padding: 9px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
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

.edit-button {
    border: 1px solid #d5e1f8;
    background: #eef5ff;
    color: #285bd1;
}

.table-responsive {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.users-table th {
    padding: 14px 17px;
    background: #f8fafc;
    color: #7f8da0;
    text-align: left;
    font-size: 11px;
}

.users-table td {
    padding: 16px 17px;
    border-top: 1px solid #edf1f6;
    color: #566579;
    font-size: 12px;
}

.users-table td strong {
    color: #26364a;
}

.users-table td small {
    display: block;
    margin-top: 5px;
    color: #8491a3;
    font-size: 11px;
}

.badge-role,
.badge-state {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 650;
}

.badge-role {
    background: #eaf1ff;
    color: #285bd1;
}

.badge-role.administrator {
    background: #f1ebff;
    color: #7c4bd1;
}

.badge-state.active {
    background: #e8f8ef;
    color: #198754;
}

.badge-state.inactive {
    background: #eef2f7;
    color: #64748b;
}

.row-actions {
    display: flex;
    gap: 8px;
}

.empty-state {
    padding: 35px !important;
    text-align: center;
}

.table-footer {
    padding: 14px 18px;
    border-top: 1px solid #edf1f6;
    color: #8491a3;
    font-size: 11px;
}

.roles-note {
    border-color: #e6ebf2;
    background: white;
    color: #64748b;
}

.roles-note > strong {
    color: #26364a;
}

.roles-note p {
    margin: 8px 0 0;
}

.form-overlay {
    position: fixed;
    z-index: 1050;
    inset: 0;
    display: grid;
    place-items: center;
    overflow-y: auto;
    padding: 20px;
    background: rgb(15 23 42 / 55%);
}

.user-dialog {
    width: min(100%, 540px);
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    border-radius: 14px;
    background: white;
    box-shadow: 0 20px 70px rgb(0 0 0 / 20%);
}

.dialog-heading {
    border-bottom: 1px solid #edf1f6;
}

.eyebrow {
    display: block;
    margin-bottom: 7px;
    color: #8491a3;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
}

.close-button {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 7px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 24px;
}

.form-fields {
    display: grid;
    gap: 17px;
    padding: 22px;
}

.form-field {
    display: grid;
    gap: 7px;
}

.form-field label,
.checkbox-field {
    color: #536277;
    font-size: 12px;
}

.form-field input,
.form-field select {
    width: 100%;
    box-sizing: border-box;
}

.checkbox-field {
    display: flex;
    align-items: center;
    gap: 9px;
}

.form-note {
    margin: 0;
    color: #8491a3;
    font-size: 11px;
    line-height: 1.6;
}

.error-message {
    padding: 11px 13px;
    border: 1px solid #fecaca;
    border-radius: 7px;
    background: #fff0f0;
    color: #b91c1c;
    font-size: 12px;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 17px 22px;
    border-top: 1px solid #edf1f6;
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 3px;
}

@media (max-width: 650px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .panel-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .filters {
        flex-direction: column;
    }

    .filters input {
        min-width: 0;
    }

    .dialog-footer {
        flex-direction: column-reverse;
    }

    .dialog-footer button {
        width: 100%;
    }
}
</style>