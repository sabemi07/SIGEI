<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

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
    real: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    errorText: { type: String, default: '' },
});

const emit = defineEmits(['iniciar', 'credentials']);

const usuarioId = ref('');
const error = ref('');
const correo = ref('');
const password = ref('');

function iniciarSesionReal() {
    if (props.busy) return;
    emit('credentials', { email: correo.value.trim(), password: password.value });
    password.value = '';
}

watch(() => props.errorText, () => { password.value = ''; });
onBeforeUnmount(() => { password.value = ''; });

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

            <h2>{{ real ? 'Iniciar sesión' : 'Acceso de demostración' }}</h2>
            <p>{{ real ? 'Ingresa con tu cuenta institucional.' : 'Selecciona una cuenta para probar su panel.' }}</p>

            <form v-if="real" @submit.prevent="iniciarSesionReal" :aria-busy="busy">
                <label for="correo-institucional">Correo electrónico</label>
                <input id="correo-institucional" v-model="correo" type="email" autocomplete="username" maxlength="254" :disabled="busy" required />
                <label for="password-institucional">Contraseña</label>
                <input id="password-institucional" v-model="password" type="password" autocomplete="current-password" maxlength="255" :disabled="busy" required />
                <div v-if="errorText" class="alert alert-danger small" role="alert">{{ errorText }}</div>
                <button type="submit" :disabled="busy">{{ busy ? 'Verificando…' : 'Iniciar sesión' }}</button>
            </form>

            <form v-else @submit.prevent="iniciarSesionDemo">
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

            <footer>{{ configuracion.siglas }} · {{ real ? 'Acceso institucional' : 'Acceso de demostración' }}</footer>
            <slot />
        </section>
    </main>
</template>
