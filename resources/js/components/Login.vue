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
        <div class="login-shell">
            <nav v-if="$slots.navegacion" class="login-navigation" aria-label="Regresar al inicio">
                <slot name="navegacion" />
            </nav>

            <section class="login-card" aria-labelledby="titulo-acceso">
                <div class="login-brand">
                    <div class="login-identity">
                        <span class="login-logo" aria-hidden="true">+</span>
                        <div>
                            <strong>{{ configuracion.siglas }}</strong>
                            <p>{{ configuracion.institucion }}</p>
                        </div>
                    </div>

                    <div class="login-brand-description">
                        <p class="login-kicker">RESPUESTA INSTITUCIONAL</p>
                        <p class="login-brand-title">Gestión de emergencias</p>
                        <p>Consulta alertas y da seguimiento a los incidentes de tu institución.</p>
                    </div>

                    <svg class="login-signal" viewBox="0 0 320 140" fill="none" aria-hidden="true" focusable="false">
                        <path d="M0 30H320M0 70H320M0 110H320M40 0V140M120 0V140M200 0V140M280 0V140" stroke="#a5c8ff" opacity=".16" />
                        <circle cx="212" cy="70" r="28" stroke="#93c5fd" opacity=".32" />
                        <circle cx="212" cy="70" r="52" stroke="#93c5fd" opacity=".2" />
                        <path class="login-signal-line" pathLength="1" d="M0 92H55L74 72L94 102L118 48L140 92H183L212 70H320" stroke="#a9d4ff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="212" cy="70" r="5" fill="#dbeafe" />
                    </svg>
                </div>

                <div class="login-content">
                    <header class="login-heading">
                        <p class="login-kicker">{{ real ? 'ACCESO INSTITUCIONAL' : 'MODO DEMOSTRACIÓN' }}</p>
                        <h1 id="titulo-acceso">{{ real ? 'Iniciar sesión' : 'Acceso de demostración' }}</h1>
                        <p>{{ real ? 'Ingresa con tu cuenta institucional.' : 'Selecciona una cuenta para probar su panel.' }}</p>
                    </header>

                    <form v-if="real" class="login-form" @submit.prevent="iniciarSesionReal" :aria-busy="busy">
                        <div class="login-field">
                            <label for="correo-institucional">Correo electrónico</label>
                            <input id="correo-institucional" v-model="correo" class="form-control" type="email" autocomplete="username" maxlength="254" :disabled="busy" required />
                        </div>
                        <div class="login-field">
                            <label for="password-institucional">Contraseña</label>
                            <input id="password-institucional" v-model="password" class="form-control" type="password" autocomplete="current-password" maxlength="255" :disabled="busy" required />
                        </div>
                        <div v-if="errorText" class="alert alert-danger small mb-0" role="alert">{{ errorText }}</div>
                        <button class="btn login-submit" type="submit" :disabled="busy">
                            {{ busy ? 'Verificando…' : 'Iniciar sesión' }}
                            <span v-if="busy" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            <span v-else aria-hidden="true">→</span>
                        </button>
                    </form>

                    <form v-else class="login-form" @submit.prevent="iniciarSesionDemo">
                        <div class="login-field">
                            <label for="cuenta-demo">Cuenta de demostración</label>
                            <select
                                id="cuenta-demo"
                                v-model="usuarioId"
                                class="form-select"
                                aria-describedby="cuenta-demo-ayuda"
                                required
                                @change="error = ''"
                            >
                                <option disabled value="">Selecciona una cuenta</option>
                                <option v-for="usuario in cuentasActivas" :key="usuario.id" :value="usuario.id">
                                    {{ usuario.nombre }} · {{ usuario.rol }}
                                </option>
                            </select>
                        </div>
                        <p id="cuenta-demo-ayuda" class="login-help">
                            Elige una cuenta activa para recorrer el panel con datos de ejemplo.
                        </p>
                        <div v-if="error" class="alert alert-danger small mb-0" role="alert">{{ error }}</div>
                        <p v-if="cuentasActivas.length === 0" class="login-help">No hay cuentas activas disponibles.</p>
                        <button class="btn login-submit" type="submit" :disabled="cuentasActivas.length === 0">
                            Entrar a la demostración
                            <span aria-hidden="true">→</span>
                        </button>
                    </form>

                    <footer v-if="$slots.default" class="login-support">
                        <slot />
                    </footer>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    display: grid;
    min-height: 100vh;
    min-height: 100dvh;
    place-items: center;
    padding: clamp(16px, 4vw, 48px);
    background: radial-gradient(ellipse at 15% 8%, #dbeafe, transparent 58%), #f4f7fb;
    color: #17263b;
    font-family: 'DM Sans', system-ui, sans-serif;
}

.login-shell {
    width: min(100%, 940px);
}

.login-navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

.login-card {
    display: grid;
    grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
    overflow: hidden;
    border: 1px solid #dbe5f0;
    border-radius: 24px;
    background: white;
    box-shadow: 0 22px 60px rgb(15 35 66 / 13%);
    animation: login-enter 520ms ease-out both;
}

.login-brand {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 32px;
    padding: clamp(24px, 4vw, 42px);
    background: radial-gradient(ellipse at 92% 90%, #214b88, transparent 62%), #14243a;
    color: #c5d6ea;
}

.login-identity {
    display: flex;
    align-items: center;
    gap: 14px;
    overflow-wrap: anywhere;
}

.login-logo {
    display: grid;
    flex: 0 0 48px;
    height: 48px;
    place-items: center;
    border-radius: 13px;
    background: #2563eb;
    color: white;
    font-size: 32px;
    font-weight: 800;
    line-height: 1;
}

.login-identity strong {
    color: white;
    font-size: 1.2rem;
    letter-spacing: .04em;
}

.login-identity p,
.login-brand-description p,
.login-heading p,
.login-help {
    margin: 0;
}

.login-identity p {
    margin-top: 3px;
    font-size: .85rem;
}

.login-kicker {
    color: #2454bd;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .12em;
}

.login-brand-description .login-kicker {
    color: #a9d4ff;
}

.login-brand-description .login-brand-title {
    margin: 10px 0;
    color: white;
    font-family: 'Manrope', system-ui, sans-serif;
    font-size: clamp(1.8rem, 3vw, 2.6rem);
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -.04em;
}

.login-signal {
    width: 100%;
    max-height: 150px;
}

.login-signal-line {
    stroke-dasharray: 1;
    animation: login-draw-signal 1050ms 180ms ease-out both;
}

.login-content {
    display: grid;
    min-width: 0;
    align-content: center;
    gap: 22px;
    padding: clamp(24px, 5vw, 52px);
}

.login-heading h1 {
    margin: 8px 0 9px;
    font-family: 'Manrope', system-ui, sans-serif;
    font-size: clamp(1.65rem, 3.3vw, 2.3rem);
    font-weight: 800;
    line-height: 1.14;
    letter-spacing: -.04em;
}

.login-heading p:not(.login-kicker),
.login-help {
    color: #50627b;
    font-size: .9rem;
    line-height: 1.6;
}

.login-form,
.login-field {
    display: grid;
    min-width: 0;
}

.login-form {
    gap: 16px;
}

.login-field {
    gap: 6px;
}

.login-field label {
    color: #334155;
    font-size: .9rem;
    font-weight: 700;
}

.login-field .form-control,
.login-field .form-select {
    min-height: 46px;
    border-color: #cbd8e6;
    border-radius: 10px;
    background-color: #f8fafc;
    color: #17263b;
}

.login-field .form-control:focus,
.login-field .form-select:focus {
    border-color: #2563eb;
    background-color: white;
    box-shadow: 0 0 0 3px rgb(37 99 235 / 16%);
}

.login-submit {
    display: flex;
    min-height: 46px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-radius: 10px;
    background: #1d4ed8;
    color: white;
    font-weight: 700;
}

.login-submit:hover,
.login-submit:focus-visible {
    background: #1e40af;
    color: white;
}

.login-submit:focus-visible {
    outline: 2px solid #1e40af;
    outline-offset: 3px;
}

.login-submit:disabled {
    background: #1d4ed8;
    color: white;
}

.login-support {
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

@keyframes login-enter {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes login-draw-signal {
    from { stroke-dashoffset: 1; }
    to { stroke-dashoffset: 0; }
}

@media (max-width: 700px) {
    .login-card {
        grid-template-columns: minmax(0, 1fr);
    }

    .login-brand-description,
    .login-signal {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .login-card,
    .login-signal-line,
    .login-submit .spinner-border {
        animation: none;
    }

    .login-field .form-control,
    .login-field .form-select,
    .login-submit {
        transition: none;
    }
}
</style>
