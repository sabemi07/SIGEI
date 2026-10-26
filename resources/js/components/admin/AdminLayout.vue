<script setup>
import Notificaciones from '../Notificaciones.vue';
defineProps({
    activeView: {
        type: String,
        default: 'dashboard',
    },
});

const emit = defineEmits(['navigate', 'logout']);

const menu = [
    { id: 'dashboard', label: 'Dashboard', icon: '▦' },
    { id: 'incidentes', label: 'Incidentes', icon: '▤' },
    { id: 'usuarios', label: 'Usuarios', icon: '♙' },
    { id: 'reportes', label: 'Reportes', icon: '▥' },
    { id: 'configuracion', label: 'Configuración', icon: '⚙' },
];

const titulos = {
    dashboard: 'Dashboard',
    incidentes: 'Gestión de incidentes',
    usuarios: 'Usuarios y roles',
    reportes: 'Reportes y estadísticas',
    configuracion: 'Configuración del sistema',
};

function navegar(id) {
    emit('navigate', id);
}
</script>

<template>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <div class="admin-brand-icon">+</div>

                <div>
                    <h2>SIGEI</h2>
                    <span>Gestión de emergencias</span>
                </div>
            </div>

            <div class="admin-menu-label">MENÚ PRINCIPAL</div>

            <nav class="admin-nav">
                <button
                    v-for="item in menu"
                    :key="item.id"
                    type="button"
                    class="admin-nav-item"
                    :class="{ active: activeView === item.id }"
                    @click="navegar(item.id)"
                >
                    <span class="admin-nav-icon">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>

                    
                </button>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-demo-badge">
                    <span></span>
                    Modo demostración
                </div>

                <button
                    type="button"
                    class="admin-logout"
                    @click="emit('logout')"
                >
                    Volver al inicio
                </button>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div>
                    <div class="admin-breadcrumb">
                        SIGEI <span>/</span> {{ titulos[activeView] }}
                    </div>

                    <h1>{{ titulos[activeView] }}</h1>
                </div>

                <div class="admin-profile">
                <Notificaciones
                    rol="administrador"
                    @navigate="emit('navigate', $event)"
                />
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm mobile-logout"
                        @click="emit('logout')"
                    >
                        Cerrar sesión
                    </button>
                    <div class="admin-profile-avatar">AD</div>

                    <div class="admin-profile-info">
                        <strong>Administrador</strong>
                        <span>Acceso temporal</span>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <slot />
            </main>

            <footer class="admin-footer">
                SIGEI · Sistema de Gestión de Emergencias Institucionales
            </footer>
        </div>
    </div>
</template>

<style scoped>
.admin-layout {
    --navy: #14243a;
    --navy-light: #203650;
    --blue: #2563eb;
    display: flex;
    min-height: 100vh;
    background: #f4f7fb;
    color: #1e293b;
    font-family: Inter, system-ui, -apple-system, sans-serif;
}

.admin-sidebar {
    width: 258px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    padding: 25px 16px 18px;
    background: var(--navy);
    color: #d7e1ee;
}

.admin-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px 30px;
}

.admin-brand-icon {
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

.admin-brand h2 {
    margin: 0;
    color: white;
    font-size: 21px;
    font-weight: 750;
    letter-spacing: 1px;
}

.admin-brand span {
    display: block;
    margin-top: 4px;
    color: #9eb0c6;
    font-size: 10px;
}

.admin-menu-label {
    margin: 0 10px 12px;
    color: #8295ad;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.4px;
}

.admin-nav {
    display: grid;
    gap: 6px;
}

.admin-nav-item {
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

.admin-nav-item:hover {
    background: var(--navy-light);
    color: white;
}

.admin-nav-item.active {
    background: var(--blue);
    color: white;
    font-weight: 600;
}

.admin-nav-icon {
    width: 20px;
    text-align: center;
    font-size: 18px;
}

.admin-sidebar-footer {
    margin-top: auto;
    padding: 22px 5px 0;
}

.admin-demo-badge {
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

.admin-demo-badge span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fbbf24;
}

.admin-logout {
    width: 100%;
    padding: 10px;
    border: 1px solid #405169;
    border-radius: 8px;
    background: transparent;
    color: #d7e1ee;
    font-size: 12px;
}

.admin-logout:hover {
    background: #293d56;
}

.admin-main {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.admin-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 23px 34px;
    border-bottom: 1px solid #e6ebf2;
    background: white;
}

.admin-breadcrumb {
    margin-bottom: 7px;
    color: #8491a3;
    font-size: 11px;
}

.admin-breadcrumb span {
    margin: 0 6px;
}

.admin-topbar h1 {
    margin: 0;
    color: #17263b;
    font-size: 23px;
    font-weight: 750;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-profile-avatar {
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

.admin-profile-info strong,
.admin-profile-info span {
    display: block;
}

.admin-profile-info strong {
    color: #26364a;
    font-size: 12px;
}

.admin-profile-info span {
    margin-top: 3px;
    color: #8793a4;
    font-size: 10px;
}

.admin-content {
    flex: 1;
    padding: 30px 34px;
}

.admin-footer {
    padding: 15px 34px;
    border-top: 1px solid #e6ebf2;
    background: white;
    color: #8b97a8;
    font-size: 10px;
}

@media (max-width: 760px) {
    .admin-layout {
        flex-direction: column;
    }

    .admin-sidebar {
        width: 100%;
        padding: 15px;
    }

    .admin-brand {
        padding-bottom: 16px;
    }

    .admin-nav {
        display: flex;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .admin-nav-item {
        flex: 0 0 auto;
        width: auto;
    }

    .admin-menu-label,
    .admin-sidebar-footer {
        display: none;
    }

    .admin-topbar {
        padding: 20px;
    }

    .admin-topbar h1 {
        font-size: 19px;
    }

    .admin-profile-info {
        display: none;
    }

    .admin-content {
        padding: 20px;
    }

    .admin-footer {
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
@media (max-width: 560px) {
    .admin-topbar {
        flex-wrap: wrap;
    }

    .admin-profile {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
