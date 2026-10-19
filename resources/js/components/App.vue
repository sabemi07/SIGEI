
<script setup>
import { ref } from 'vue';

import Login from './Login.vue';
import BotonPanico from './BotonPanico.vue';

const mostrarBotonPanico = ref(false);

function iniciarSesion() {
    mostrarBotonPanico.value = true;
}
</script>

<template>
    <Login
        v-if="!mostrarBotonPanico"
        @iniciar="iniciarSesion"
    />

    <BotonPanico v-else />
</template>
