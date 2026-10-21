<script setup>
defineProps({
    activeView: {
        type: String,
        default: 'alertas',
    },
});

const emit = defineEmits(['navigate', 'logout', 'reportar']);

const menu = [
    { id: 'alertas', label: 'Alertas asignadas', icon: '⚠' },
    { id: 'mis-reportes', label: 'Mis reportes', icon: '▤' },
];

const titulos = {
    alertas: 'Alertas asignadas',
    'mis-reportes': 'Mis reportes',
};
</script>

<template>
    <div class="responsable-layout">
        <aside class="responsable-sidebar">
            <div class="responsable-brand">
                <div class="responsable-brand-icon">+</div>

                <div>
                    <h2>SIGEI</h2>
                    <span>Gestión de emergencias</span>
                </div>
            </div>

            <div class="responsable-menu-label">
                PANEL DEL RESPONSABLE
            </div>

            <nav
                class="responsable-nav"
                aria-label="Menú del responsable"
            >
                <button
                    v-for="item in menu"
                    :key="item.id"
                    type="button"
                    class="responsable-nav-item"
                    :class="{ active: activeView === item.id }"
                    :aria-current="activeView === item.id ? 'page' : undefined"
                    @click="emit('navigate', item.id)"
                >
                    <span
                        class="responsable-nav-icon"
                        aria-hidden="true"
                    >
                        {{ item.icon }}
                    </span>

                    <span>{{ item.label }}</span>
                </button>

                <button
                    type="button"
                    class="responsable-nav-item responsable-reportar"
                    @click="emit('reportar')"
                >
                    <span
                        class="responsable-nav-icon"
                        aria-hidden="true"
                    >
                        +
                    </span>

                    <span>Reportar emergencia</span>
                </button>
            </nav>

            <div class="responsable-sidebar-footer">
                <div class="responsable-demo-badge">
                    <span aria-hidden="true"></span>
                    Modo demostración
                </div>

                <button
                    type="button"
                    class="responsable-logout"
                    @click="emit('logout')"
                >
                    Cerrar sesión
                </button>
            </div>
        </aside>

        <div class="responsable-main">
            <header class="responsable-topbar">
                <div>
                    <div class="responsable-breadcrumb">
                        SIGEI <span>/</span> Responsable
                    </div>

                    <h1>{{ titulos[activeView] || 'Panel del responsable' }}</h1>
                </div>

                <div class="responsable-profile">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm mobile-logout"
                        @click="emit('logout')"
                    >
                        Cerrar sesión
                    </button>
                    <div
                        class="responsable-profile-avatar"
                        aria-hidden="true"
                    >
                        RE
                    </div>

                    <div class="responsable-profile-info">
                        <strong>Responsable</strong>
                        <span>Sesión de demostración</span>
                    </div>
                </div>
            </header>

            <main class="responsable-content">
                <slot />
            </main>

            <footer class="responsable-footer">
                SIGEI · Sistema de Gestión de Emergencias Institucionales
            </footer>
        </div>
    </div>
</template>

<style scoped>
.responsable-layout {
    --navy: #14243a;
    --navy-light: #203650;
    --blue: #2563eb;
    display: flex;
    min-height: 100vh;
    background: #f4f7fb;
    color: #1e293b;
    font-family: Inter, system-ui, -apple-system, sans-serif;
}

.responsable-sidebar {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 258px;
    padding: 25px 16px 18px;
    background: var(--navy);
    color: #d7e1ee;
}

.responsable-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 30px;
}

.responsable-brand-icon {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    border-radius: 12px;
    background: var(--blue);
    color: white;
    font-size: 32px;
    font-weight: 700;
}

.responsable-brand h2 {
    margin: 0;
    color: white;
    font-size: 21px;
    font-weight: 750;
    letter-spacing: 1px;
}

.responsable-brand span {
    display: block;
    margin-top: 4px;
    color: #9eb0c6;
    font-size: 10px;
}

.responsable-menu-label {
    margin: 0 10px 12px;
    color: #8295ad;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.4px;
}

.responsable-nav {
    display: grid;
    gap: 6px;
}

.responsable-nav-item {
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

.responsable-nav-item:hover {
    background: var(--navy-light);
    color: white;
}

.responsable-nav-item.active {
    background: var(--blue);
    color: white;
    font-weight: 600;
}

.responsable-nav-icon {
    width: 20px;
    text-align: center;
    font-size: 18px;
}

.responsable-reportar {
    margin-top: 12px;
    border: 1px solid #805064;
    color: #fecdd3;
}

.responsable-reportar:hover {
    background: #493047;
    color: white;
}

.responsable-sidebar-footer {
    margin-top: auto;
    padding: 22px 5px 0;
}

.responsable-demo-badge {
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

.responsable-demo-badge span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fbbf24;
}

.responsable-logout {
    width: 100%;
    padding: 10px;
    border: 1px solid #405169;
    border-radius: 8px;
    background: transparent;
    color: #d7e1ee;
    font-size: 12px;
}

.responsable-logout:hover {
    background: #293d56;
}

.responsable-nav-item:focus-visible,
.responsable-logout:focus-visible {
    outline: 2px solid #93c5fd;
    outline-offset: 3px;
}

.responsable-main {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.responsable-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 23px 34px;
    border-bottom: 1px solid #e6ebf2;
    background: white;
}

.responsable-breadcrumb {
    margin-bottom: 7px;
    color: #8491a3;
    font-size: 11px;
}

.responsable-breadcrumb span {
    margin: 0 6px;
}

.responsable-topbar h1 {
    margin: 0;
    color: #17263b;
    font-size: 23px;
    font-weight: 750;
}

.responsable-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.responsable-profile-avatar {
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

.responsable-profile-info strong,
.responsable-profile-info span {
    display: block;
}

.responsable-profile-info strong {
    color: #26364a;
    font-size: 12px;
}

.responsable-profile-info span {
    margin-top: 3px;
    color: #8793a4;
    font-size: 10px;
}

.responsable-content {
    flex: 1;
    padding: 30px 34px;
}

.responsable-footer {
    padding: 15px 34px;
    border-top: 1px solid #e6ebf2;
    background: white;
    color: #8b97a8;
    font-size: 10px;
}

@media (max-width: 760px) {
    .responsable-layout {
        flex-direction: column;
    }

    .responsable-sidebar {
        width: 100%;
        padding: 15px;
    }

    .responsable-brand {
        padding-bottom: 16px;
    }

    .responsable-nav {
        display: flex;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .responsable-nav-item {
        flex: 0 0 auto;
        width: auto;
    }

    .responsable-reportar {
        margin-top: 0;
    }

    .responsable-menu-label,
    .responsable-sidebar-footer {
        display: none;
    }

    .responsable-topbar {
        padding: 20px;
    }

    .responsable-topbar h1 {
        font-size: 19px;
    }

    .responsable-profile-info {
        display: none;
    }

    .responsable-content {
        padding: 20px;
    }

    .responsable-footer {
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