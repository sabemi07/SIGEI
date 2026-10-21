<script setup>
defineProps({
    activeView: {
        type: String,
        default: 'reportar',
    },
});

const emit = defineEmits(['navigate', 'logout']);

const menu = [
    { id: 'reportar', label: 'Reportar emergencia', icon: '!' },
    { id: 'mis-reportes', label: 'Mis reportes', icon: '▤' },
];

const titulos = {
    reportar: 'Reportar emergencia',
    'mis-reportes': 'Mis reportes',
};
</script>

<template>
    <div class="usuario-layout">
        <aside class="usuario-sidebar">
            <div class="usuario-brand">
                <div class="brand-icon" aria-hidden="true">+</div>

                <div>
                    <h2>SIGEI</h2>
                    <span>Gestión de emergencias</span>
                </div>
            </div>

            <div class="menu-label">ESPACIO DEL USUARIO</div>

            <nav class="usuario-nav" aria-label="Menú del usuario">
                <button
                    v-for="item in menu"
                    :key="item.id"
                    type="button"
                    class="nav-item"
                    :class="{ active: activeView === item.id }"
                    :aria-current="activeView === item.id ? 'page' : undefined"
                    @click="emit('navigate', item.id)"
                >
                    <span class="nav-icon" aria-hidden="true">
                        {{ item.icon }}
                    </span>

                    <span>{{ item.label }}</span>
                </button>
            </nav>

            <div class="sidebar-footer">
                <div class="demo-badge">
                    <span aria-hidden="true"></span>
                    Modo demostración
                </div>

                <button
                    type="button"
                    class="logout-button"
                    @click="emit('logout')"
                >
                    Cerrar sesión
                </button>
            </div>
        </aside>

        <div class="usuario-main">
            <header class="usuario-topbar">
                <div>
                    <div class="breadcrumb-text">
                        SIGEI <span>/</span> Usuario
                    </div>

                    <h1>{{ titulos[activeView] || 'Espacio del usuario' }}</h1>
                </div>

                <div class="usuario-profile">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm mobile-logout"
                        @click="emit('logout')"
                    >
                        Cerrar sesión
                    </button>
                    <div class="profile-avatar" aria-hidden="true">US</div>

                    <div class="profile-info">
                        <strong>Usuario</strong>
                        <span>Sesión de demostración</span>
                    </div>
                </div>
            </header>

            <main class="usuario-content">
                <slot />
            </main>

            <footer class="usuario-footer">
                SIGEI · Sistema de Gestión de Emergencias Institucionales
            </footer>
        </div>
    </div>
</template>

<style scoped>
.usuario-layout {
    display: flex;
    min-height: 100vh;
    background: #f4f7fb;
    color: #26364a;
    font-family: Inter, system-ui, -apple-system, sans-serif;
}

.usuario-sidebar {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 258px;
    padding: 25px 16px 18px;
    background: #14243a;
    color: #d7e1ee;
}

.usuario-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 30px;
}

.brand-icon {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    border-radius: 12px;
    background: #2563eb;
    color: white;
    font-size: 32px;
    font-weight: 700;
}

.usuario-brand h2 {
    margin: 0;
    color: white;
    font-size: 21px;
    font-weight: 750;
    letter-spacing: 1px;
}

.usuario-brand span {
    display: block;
    margin-top: 4px;
    color: #9eb0c6;
    font-size: 10px;
}

.menu-label {
    margin: 0 10px 12px;
    color: #8295ad;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.4px;
}

.usuario-nav {
    display: grid;
    gap: 6px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 13px;
    border: 0;
    border-radius: 9px;
    background: transparent;
    color: #c0cddd;
    text-align: left;
    font-size: 13px;
    transition: background 0.2s, color 0.2s;
}

.nav-item:hover {
    background: #203650;
    color: white;
}

.nav-item.active {
    background: #2563eb;
    color: white;
    font-weight: 600;
}

.nav-icon {
    width: 20px;
    text-align: center;
    font-size: 18px;
    font-weight: 700;
}

.sidebar-footer {
    margin-top: auto;
    padding: 22px 5px 0;
}

.demo-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #33465e;
    border-radius: 8px;
    color: #b8c6d8;
    font-size: 11px;
}

.demo-badge span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fbbf24;
}

.logout-button {
    width: 100%;
    padding: 10px;
    border: 1px solid #405169;
    border-radius: 8px;
    background: transparent;
    color: #d7e1ee;
    font-size: 12px;
}

.logout-button:hover {
    background: #293d56;
}

.nav-item:focus-visible,
.logout-button:focus-visible {
    outline: 2px solid #93c5fd;
    outline-offset: 3px;
}

.usuario-main {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.usuario-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 23px 34px;
    border-bottom: 1px solid #e6ebf2;
    background: white;
}

.breadcrumb-text {
    margin-bottom: 7px;
    color: #68778b;
    font-size: 11px;
}

.breadcrumb-text span {
    margin: 0 6px;
}

.usuario-topbar h1 {
    margin: 0;
    color: #17263b;
    font-size: 23px;
    font-weight: 750;
}

.usuario-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-avatar {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e0eaff;
    color: #2454bd;
    font-size: 12px;
    font-weight: 750;
}

.profile-info strong,
.profile-info span {
    display: block;
}

.profile-info strong {
    font-size: 12px;
}

.profile-info span {
    margin-top: 3px;
    color: #68778b;
    font-size: 10px;
}

.usuario-content {
    flex: 1;
    min-width: 0;
    padding: 30px 34px;
}

.usuario-footer {
    padding: 15px 34px;
    border-top: 1px solid #e6ebf2;
    background: white;
    color: #68778b;
    font-size: 10px;
}

@media (max-width: 760px) {
    .usuario-layout {
        flex-direction: column;
    }

    .usuario-sidebar {
        width: 100%;
        padding: 15px;
    }

    .usuario-brand {
        padding-bottom: 16px;
    }

    .usuario-nav {
        display: flex;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .nav-item {
        flex: 0 0 auto;
        width: auto;
    }

    .menu-label,
    .sidebar-footer {
        display: none;
    }

    .usuario-topbar {
        padding: 20px;
    }

    .usuario-topbar h1 {
        font-size: 19px;
    }

    .profile-info {
        display: none;
    }

    .usuario-content {
        padding: 20px;
    }

    .usuario-footer {
        padding: 14px 20px;
    }
}
.mobile-logout {
    display: none;
}

@media (max-width: 760px) {
    .mobile-logout {
        display: inline-flex;
        flex-shrink: 0;
    }
}
</style>