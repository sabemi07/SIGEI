<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    configuracion: {
        type: Object,
        default: () => ({
            siglas: 'SIGEI',
            institucion: 'Institución de demostración',
        }),
    },
    usuarios: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['iniciar']);

const usuarioId = ref('');
const error = ref('');

const cuentasActivas = computed(() =>
    props.usuarios.filter(usuario =>
        usuario.activo &&
        ['Administrador', 'Responsable'].includes(usuario.rol)
    )
);

function iniciarSesionDemo() {
    error.value = '';

    const usuario = cuentasActivas.value.find(
        cuenta => cuenta.id === usuarioId.value
    );

    if (!usuario) {
        error.value = 'Selecciona una cuenta activa.';
        return;
    }

    emit('iniciar', usuario.id);
}
</script>

<template>
    <main class="login-page">
        <section class="login-card">
            <div class="login-logo">+</div>

            <h1>{{ configuracion.siglas }}</h1>

            <p class="login-subtitle">
                {{ configuracion.institucion }}
            </p>

            <h2>Acceso de demostración</h2>
            <p>Selecciona una cuenta para probar su panel.</p>

            <form @submit.prevent="iniciarSesionDemo">
                <label for="cuenta-demo">Cuenta de demostración</label>

                <select
                    id="cuenta-demo"
                    v-model="usuarioId"
                    class="form-select"
                    aria-describedby="cuenta-demo-ayuda"
                    required
                    @change="error = ''"
                >
                    <option disabled value="">
                        Selecciona una cuenta
                    </option>

                    <option
                        v-for="usuario in cuentasActivas"
                        :key="usuario.id"
                        :value="usuario.id"
                    >
                        {{ usuario.nombre }} · {{ usuario.rol }}
                    </option>
                </select>

                <small
                    id="cuenta-demo-ayuda"
                    class="d-block text-secondary mt-3 mb-3"
                >
                    Acceso temporal sin autenticación real.
                    Solo aparecen cuentas activas del módulo Usuarios.
                    No introduzcas contraseñas reales.
                </small>

                <div v-if="error" class="alert alert-danger small" role="alert">
                    {{ error }}
                </div>

                <p
                    v-if="cuentasActivas.length === 0"
                    class="text-secondary small"
                >
                    No hay cuentas activas disponibles.
                </p>

                <button
                    type="submit"
                    :disabled="cuentasActivas.length === 0"
                >
                    Entrar a la demostración
                </button>
            </form>

            <footer>{{ configuracion.siglas }} · Acceso de demostración</footer>
        </section>
    </main>
</template>
