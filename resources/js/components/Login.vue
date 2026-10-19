
<script setup>
import { ref } from 'vue';

const correo = ref('');
const password = ref('');

const emit = defineEmits(['iniciar', 'admin']);
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
            <p>Ingresa tus datos para continuar.</p>

            <form @submit.prevent="emit('iniciar')">
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

                <button type="submit">
                    Iniciar sesión
                </button>
            </form>

            <!-- Acceso temporal para desarrollar el panel -->
            <button
                type="button"
                class="admin-temporal"
                @click="emit('admin')"
            >
                Entrar al panel administrativo
            </button>

            <footer>SIGEI · Acceso institucional</footer>
        </section>
    </main>
</template>
