<script setup>
import { ref } from 'vue';

const correo = ref('');
const password = ref('');
const rolDemo = ref('responsable');

const emit = defineEmits(['iniciar']);

function iniciarSesionDemo() {
    emit('iniciar', rolDemo.value);
}
</script>

<template>
    <main class="login-page">
        <section class="login-card">
            <div class="login-logo">+</div>

            <h1>SIGEI</h1>

            <p class="login-subtitle">
                Sistema de Gestión de Emergencias Institucionales
            </p>

            <h2>Iniciar sesión</h2>
            <p>Acceso para responsables y administradores.</p>

            <form @submit.prevent="iniciarSesionDemo">
                <label for="correo">Correo institucional</label>
                <input
                    id="correo"
                    v-model="correo"
                    type="email"
                    autocomplete="username"
                    placeholder="usuario@institucion.edu"
                    required
                />

                <label for="password">Contraseña</label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña"
                    required
                />

                <div class="mt-3 mb-3">
                    <label for="rol-demo">
                        Rol de demostración
                    </label>

                    <select
                        id="rol-demo"
                        v-model="rolDemo"
                        class="form-select"
                        aria-describedby="rol-demo-ayuda"
                    >
                        <option value="responsable">Responsable</option>
                        <option value="administrador">Administrador</option>
                    </select>

                    <small
                        id="rol-demo-ayuda"
                        class="d-block text-secondary mt-2"
                    >
                        Selector temporal para visualizar las interfaces
                        de cada rol.
                    </small>
                </div>

                <button type="submit">
                    Iniciar sesión
                </button>
            </form>

            <footer>SIGEI · Acceso institucional</footer>
        </section>
    </main>
</template>