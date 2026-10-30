<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { createSessionApi } from '../sessionApi';
import { loadAllPages, requireIncidentReceipt } from '../incidentClient';
import { createPushClient } from '../pushClient';
import Dashboard from './admin/Dashboard.vue';
import Incidentes from './admin/Incidentes.vue';
import DetalleIncidente from './admin/DetalleIncidente.vue';
import Reportes from './admin/Reportes.vue';
import AlertasAsignadas from './responsable/AlertasAsignadas.vue';
import MisReportes from './responsable/MisReportes.vue';
import Notificaciones from './Notificaciones.vue';
import Login from './Login.vue';
import BotonPanico from './BotonPanico.vue';
import AdminLayout from './admin/AdminLayout.vue';
import ResponsableLayout from './responsable/ResponsableLayout.vue';
import Usuarios from './admin/Usuarios.vue';
import Configuracion from './admin/Configuracion.vue';

const configuracion = ref({ siglas: 'SIGEI', institucion: 'Institución por configurar', tipos: [], ubicaciones: [] });
const configuracionAdministrativa = ref(null);
const cargandoConfiguracion = ref(false);
let cargaConfiguracion = 0;
const usuario = ref(null);
const usuarios = ref([]);
const pantalla = ref('panico');
const incidentes = ref([]);
const asignadas = ref([]);
const misReportes = ref([]);
const responsables = ref([]);
const notificaciones = ref([]);
const seleccionado = ref(null);
const solicitudDetalle = ref(null);
const origenDetalle = ref('incidentes');
const cargandoDatos = ref(false);
const datosCompletos = ref(false);
const registrando = ref(false);
const catalogosListos = ref(false);
let actualizacion = null;
let intervalo = null;
let cargaDetalle = 0;
const cargando = ref(true);
const autenticando = ref(false);
const guardando = ref(false);
const cargandoUsuarios = ref(false);
const estadoPush = ref({ busy: false, bound: false, canLink: false, message: 'Comprobando el dispositivo…', error: '' });
const errorLogin = ref('');
const mensaje = ref('');
const vistaAdmin = ref('dashboard');
const vistaResponsable = ref('alertas');
const ocupado = computed(() => autenticando.value || guardando.value || registrando.value || estadoPush.value.busy);
let versionSesion = 0;
let sincronizacion = null;
let sincronizacionPendiente = false;
let canal = null;
let desmontado = false;
const uuidAviso = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const avisoEnUrl = new URLSearchParams(window.location.search).get('aviso');
const avisoPendiente = ref(uuidAviso.test(avisoEnUrl ?? '') ? avisoEnUrl : null);

function claveCuenta(cuenta) {
    return cuenta ? `${cuenta.id}:${cuenta.rol}:${cuenta.activo}` : '';
}

function aplicarCuenta(cuenta) {
    const valida = cuenta?.activo && ['Administrador', 'Responsable'].includes(cuenta.rol) ? cuenta : null;
    if (claveCuenta(valida) !== claveCuenta(usuario.value)) {
        versionSesion += 1;
        cargaConfiguracion += 1;
        configuracionAdministrativa.value = null;
        cargandoConfiguracion.value = false;
        usuarios.value = [];
        incidentes.value = [];
        asignadas.value = [];
        misReportes.value = [];
        responsables.value = [];
        notificaciones.value = [];
        seleccionado.value = null;
        cargaDetalle += 1;
        solicitudDetalle.value = null;
        datosCompletos.value = false;
        vistaAdmin.value = 'dashboard';
        vistaResponsable.value = 'alertas';
        mensaje.value = '';
    }
    usuario.value = valida;
    clientePush.setAccount(valida);
    if (!valida && pantalla.value === 'panel') pantalla.value = 'panico';
}

function abrirBotonPanico() {
    olvidarAvisoPendiente();
    cargaDetalle += 1;
    pantalla.value = 'panico';
    cargarConfiguracionPublica().catch(error => { mensaje.value = error.message; });
}

async function volverAlPanel() {
    if (usuario.value) {
        pantalla.value = 'panel';
        await actualizarPanel();
    }
}

const api = createSessionApi({
    onUnauthorized() {
        aplicarCuenta(null);
        errorLogin.value = 'Tu sesión terminó. Inicia sesión nuevamente.';
    },
    async onCsrfExpired() {
        try { await sincronizarSesion(); } catch { /* La solicitud original mostrará el error sin repetir la escritura. */ }
    },
    async onForbidden() {
        usuarios.value = [];
        configuracionAdministrativa.value = null;
        try { await sincronizarSesion(); } catch { aplicarCuenta(null); }
    },
});
const clientePush = createPushClient({ request: api.request, onState: estado => { if (!desmontado) estadoPush.value = estado; } });

async function vincularDispositivo() {
    await clientePush.link();
    await terminarOperacion();
}

async function desvincularDispositivo() {
    await clientePush.unlink();
    await terminarOperacion();
}

function olvidarAvisoPendiente() {
    avisoPendiente.value = null;
    const url = new URL(window.location.href);
    if (url.searchParams.has('aviso')) {
        url.searchParams.delete('aviso');
        window.history.replaceState(null, '', `${url.pathname}${url.search}${url.hash}`);
    }
}

async function abrirAvisoPendiente() {
    if (!avisoPendiente.value || !usuario.value) return;
    const id = avisoPendiente.value;
    olvidarAvisoPendiente();
    pantalla.value = 'panel';
    await actualizarPanel();
    if (datosCompletos.value) await abrirNotificacion(id);
}

function alCambiarSuscripcion(evento) {
    if (evento.data?.type === 'sigei-push-changed') clientePush.refresh();
}

async function sincronizarSesion() {
    if (sincronizacion) return sincronizacion;
    const version = versionSesion;
    sincronizacion = (async () => {
        const datos = await api.request('/api/session');
        if (!desmontado && version === versionSesion) aplicarCuenta(datos.user);
    })();
    try { await sincronizacion; } finally { sincronizacion = null; }
}

function publicarCambioSesion() {
    canal?.postMessage('session-changed');
}

async function revisarSesion() {
    if (ocupado.value || cargando.value) {
        sincronizacionPendiente = true;
        return;
    }
    sincronizacionPendiente = false;
    const cuentaAnterior = claveCuenta(usuario.value);
    try {
        await sincronizarSesion();
        await cargarConfiguracionPublica();
        await clientePush.refresh();
        if (pantalla.value === 'panel' && usuario.value) {
            if (usuario.value.rol === 'Administrador' && vistaAdmin.value === 'usuarios') await cargarUsuarios();
            if (usuario.value.rol === 'Administrador' && vistaAdmin.value === 'configuracion') await cargarConfiguracion(null, { silenciosa: true });
            await actualizarPanel();
        }
        if (cuentaAnterior && claveCuenta(usuario.value) !== cuentaAnterior) {
            errorLogin.value = 'La sesión cambió en otra pestaña o la cuenta ya no tiene el mismo acceso.';
        }
    } catch (error) {
        mensaje.value = error.message;
    }
}

async function terminarOperacion() {
    if (sincronizacionPendiente) await revisarSesion();
}

async function iniciarSesion(credenciales) {
    if (ocupado.value || cargando.value) return;
    autenticando.value = true;
    errorLogin.value = '';
    try {
        const datos = await api.request('/api/login', { method: 'POST', body: credenciales });
        aplicarCuenta(datos.user);
        if (usuario.value && pantalla.value === 'login') pantalla.value = 'panel';
        publicarCambioSesion();
    } catch (error) {
        errorLogin.value = error.message;
    } finally {
        credenciales.password = '';
        autenticando.value = false;
        if (usuario.value && pantalla.value === 'panel') await actualizarPanel();
        if (usuario.value) {
            await clientePush.refresh();
            await abrirAvisoPendiente();
        }
        await terminarOperacion();
    }
}

async function cerrarSesion() {
    if (ocupado.value) return;
    autenticando.value = true;
    mensaje.value = '';
    try {
        await api.request('/api/logout', { method: 'POST' });
        aplicarCuenta(null);
        abrirBotonPanico();
        errorLogin.value = '';
        publicarCambioSesion();
    } catch (error) {
        mensaje.value = error.message;
    } finally {
        autenticando.value = false;
        await terminarOperacion();
    }
}

async function cargarUsuarios() {
    if (usuario.value?.rol !== 'Administrador') return;
    const version = versionSesion;
    cargandoUsuarios.value = true;
    try {
        const datos = await api.request('/api/users');
        if (!desmontado && version === versionSesion && usuario.value?.rol === 'Administrador') usuarios.value = datos.data;
    } catch (error) {
        mensaje.value = error.message;
    } finally {
        cargandoUsuarios.value = false;
    }
}

async function cambiarVistaAdmin(vista) {
    vista = ['alertas', 'historial'].includes(vista) ? 'incidentes' : vista;
    if (ocupado.value || usuario.value?.rol !== 'Administrador') return;
    if (!['dashboard', 'incidentes', 'usuarios', 'reportes', 'configuracion'].includes(vista)) return;
    vistaAdmin.value = vista;
    cargaDetalle += 1;
    mensaje.value = '';
    seleccionado.value = null;
    if (vista === 'usuarios') await cargarUsuarios();
    else if (vista === 'configuracion') await cargarConfiguracion();
    else await actualizarPanel();
}

let cargaPublica = 0;

async function cargarConfiguracionPublica() {
    const solicitud = ++cargaPublica;
    const datos = await api.request('/api/settings/public');
    if (!desmontado && solicitud === cargaPublica) {
        configuracion.value = datos;
        catalogosListos.value = true;
    }
}

function aplicarConfiguracion(datos) {
    cargaPublica += 1;
    configuracionAdministrativa.value = datos;
    configuracion.value = {
        institucion: datos.institucion,
        siglas: datos.siglas,
        tipos: datos.tipos.filter(item => item.activo).map(({ id, nombre }) => ({ id, nombre })),
        ubicaciones: datos.ubicaciones.filter(item => item.activo).map(({ id, nombre, requiereDetalle }) => ({ id, nombre, requiereDetalle })),
    };
    catalogosListos.value = true;
}

async function cargarConfiguracion(responder, { silenciosa = false } = {}) {
    if (usuario.value?.rol !== 'Administrador') {
        if (typeof responder === 'function') responder({ ok: false, error: 'Inicia sesión como Administrador.' });
        return;
    }
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    const solicitud = ++cargaConfiguracion;
    if (!silenciosa) cargandoConfiguracion.value = true;
    let resultado;
    try {
        const respuesta = await api.request('/api/settings');
        if (!contextoActual(version, cuenta) || solicitud !== cargaConfiguracion) return;
        aplicarConfiguracion(respuesta.data);
        resultado = { ok: true, data: respuesta.data };
    } catch (error) {
        if (contextoActual(version, cuenta) && solicitud === cargaConfiguracion) {
            mensaje.value = error.message;
            resultado = { ok: false, error: error.message };
        }
    } finally {
        if (solicitud === cargaConfiguracion) cargandoConfiguracion.value = false;
        if (typeof responder === 'function') responder(resultado ?? { ok: false, error: 'La sesión o la solicitud cambió. Tus cambios no se descartaron.' });
    }
}

async function guardarConfiguracion(datos, responder) {
    if (ocupado.value || cargandoConfiguracion.value || usuario.value?.rol !== 'Administrador') {
        responder({ ok: false, error: 'No se puede guardar en este momento.' });
        return;
    }
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    cargaConfiguracion += 1;
    guardando.value = true;
    let resultado;
    try {
        const respuesta = await api.request('/api/settings', { method: 'PUT', body: datos });
        if (!contextoActual(version, cuenta)) return;
        aplicarConfiguracion(respuesta.data);
        resultado = { ok: true, data: respuesta.data };
    } catch (error) {
        resultado = { ok: false, error: error.message };
    } finally {
        guardando.value = false;
        responder(resultado ?? { ok: false, error: 'La sesión cambió durante la operación.' });
        await terminarOperacion();
    }
}

async function cambiarVistaResponsable(vista) {
    if (ocupado.value || usuario.value?.rol !== 'Responsable') return;
    if (['alertas', 'mis-reportes'].includes(vista)) {
        vistaResponsable.value = vista;
        mensaje.value = '';
        await actualizarPanel();
    }
}

async function guardarUsuario(datos, id, responder) {
    if (ocupado.value || usuario.value?.rol !== 'Administrador') {
        responder({ ok: false, error: 'No se puede guardar en este momento.' });
        return;
    }
    guardando.value = true;
    let resultado;
    try {
        const respuesta = await api.request(id === null ? '/api/users' : `/api/users/${id}`, {
            method: id === null ? 'POST' : 'PATCH', body: datos,
        });
        if (respuesta.session_ended) {
            aplicarCuenta(null);
            errorLogin.value = 'Tu cuenta se actualizó. Inicia sesión nuevamente.';
            publicarCambioSesion();
        } else {
            await cargarUsuarios();
        }
        resultado = { ok: true };
    } catch (error) {
        resultado = { ok: false, error: error.message };
    } finally {
        delete datos.password;
        delete datos.password_confirmation;
        guardando.value = false;
    }
    responder(resultado);
    await terminarOperacion();
}

function cambiarEstado(usuarioObjetivo, responder) {
    guardarUsuario({
        nombre: usuarioObjetivo.nombre,
        correo: usuarioObjetivo.correo,
        rol: usuarioObjetivo.rol,
        area: usuarioObjetivo.area,
        activo: !usuarioObjetivo.activo,
    }, usuarioObjetivo.id, responder);
}

async function inicializar() {
    cargando.value = true;
    errorLogin.value = '';
    try {
        await Promise.all([
            cargarConfiguracionPublica(),
            sincronizarSesion(),
        ]);
    } catch (error) {
        errorLogin.value = error.message;
    } finally {
        cargando.value = false;
        if (usuario.value) {
            await clientePush.refresh();
            await abrirAvisoPendiente();
        } else if (avisoPendiente.value) {
            pantalla.value = 'login';
            errorLogin.value ||= 'Inicia sesión con la cuenta destinataria para consultar el aviso.';
        }
        await terminarOperacion();
    }
}


function contextoActual(version, cuenta) {
    return !desmontado && version === versionSesion && cuenta === claveCuenta(usuario.value);
}

async function actualizarPanel({ allowBusy = false } = {}) {
    if (!usuario.value || pantalla.value !== 'panel' || (ocupado.value && !allowBusy)) return;
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    if (actualizacion?.version === version) return actualizacion.promise;
    const isCurrent = () => contextoActual(version, cuenta);
    cargandoDatos.value = true;
    const carga = { version, promise: null };
    actualizacion = carga;
    carga.promise = (async () => {
        try {
            const avisos = await loadAllPages(api.request, '/api/notifications', { isCurrent });
            if (!isCurrent()) return;
            if (usuario.value.rol === 'Administrador') {
                const lista = await loadAllPages(api.request, '/api/incidents?scope=all', { isCurrent });
                if (!isCurrent()) return;
                const cuentas = await api.request('/api/responsibles');
                if (!isCurrent()) return;
                incidentes.value = lista;
                responsables.value = cuentas.data;
            } else {
                const aCargo = await loadAllPages(api.request, '/api/incidents?scope=assigned', { isCurrent });
                if (!isCurrent()) return;
                const propios = await loadAllPages(api.request, '/api/incidents?scope=mine', { isCurrent });
                if (!isCurrent()) return;
                asignadas.value = aCargo;
                misReportes.value = propios;
            }
            notificaciones.value = avisos;
            datosCompletos.value = true;
        } catch (error) {
            if (isCurrent()) {
                datosCompletos.value = false;
                mensaje.value = error.message;
            }
        } finally {
            if (isCurrent()) cargandoDatos.value = false;
            if (actualizacion === carga) actualizacion = null;
        }
    })();
    return carga.promise;
}

async function registrarReporte(datos, responder) {
    if (registrando.value || cargando.value || autenticando.value || guardando.value || !catalogosListos.value) {
        responder({ ok: false, error: 'Espera a que termine la comprobación de conexión y vuelve a registrar.' });
        return;
    }
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    registrando.value = true;
    try {
        const respuesta = await api.request('/api/incidents', { method: 'POST', body: datos });
        if (!contextoActual(version, cuenta)) {
            responder({ ok: false, error: 'La cuenta cambió durante el registro. Revisa tu sesión antes de continuar.' });
            return;
        }
        responder({ ok: true, data: requireIncidentReceipt(respuesta.data) });
    } catch (error) {
        responder({ ok: false, error: error.message });
        if (error.status === 422 && contextoActual(version, cuenta)
            && ['tipo_id', 'ubicacion_id', 'otro_lugar'].some(campo => campo in error.errors)) {
            try { await cargarConfiguracionPublica(); } catch { /* El formulario conserva el error original y permite volver a intentar. */ }
        }
    } finally {
        registrando.value = false;
        await terminarOperacion();
    }
}

async function abrirDetalle(incidente, origen = 'incidentes') {
    if (ocupado.value) return;
    const solicitud = ++cargaDetalle;
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    try {
        const respuesta = await api.request(`/api/incidents/${encodeURIComponent(incidente.folio)}`);
        if (!contextoActual(version, cuenta) || solicitud !== cargaDetalle || usuario.value?.rol !== 'Administrador') return;
        seleccionado.value = respuesta.data;
        origenDetalle.value = origen;
        vistaAdmin.value = 'detalle-incidente';
    } catch (error) {
        if (contextoActual(version, cuenta)) mensaje.value = error.status === 404 ? 'Este caso ya no está disponible para tu cuenta.' : error.message;
    }
}

function volverDeDetalle() {
    cargaDetalle += 1;
    seleccionado.value = null;
    vistaAdmin.value = origenDetalle.value;
}

async function guardarGestion(cambio, responder) {
    const cierre = cambio.estado === 'Cerrada' && seleccionado.value?.estado === 'Atendida';
    const body = cierre
        ? { version: cambio.version, observacion: cambio.observaciones }
        : { version: cambio.version, responsable_id: cambio.responsableId || null, prioridad: cambio.prioridad, observacion: cambio.observaciones };
    await mutarIncidente(cambio.folio, cierre ? 'close' : 'management', body, responder, true);
}

async function guardarSeguimiento(cambio, responder) {
    const actual = asignadas.value.find(item => item.folio === cambio.folio);
    if (!actual) { responder({ ok: false, error: 'El caso ya no está asignado a tu cuenta.' }); return; }
    const recepcion = cambio.accion === 'recibir';
    await mutarIncidente(cambio.folio, recepcion ? 'receipt' : 'updates', recepcion
        ? { version: cambio.version }
        : { version: cambio.version, estado: cambio.estado, observacion: cambio.observacion }, responder);
}

async function mutarIncidente(folio, accion, body, responder, admin = false) {
    if (ocupado.value) { responder({ ok: false, error: 'Espera a que termine la operación actual.' }); return; }
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    guardando.value = true;
    let resultado;
    try {
        const respuesta = await api.request(`/api/incidents/${encodeURIComponent(folio)}/${accion}`, { method: 'POST', body });
        if (!contextoActual(version, cuenta)) { resultado = { ok: false, error: 'La sesión cambió durante la operación.' }; return; }
        if (admin) seleccionado.value = respuesta.data;
        else asignadas.value = asignadas.value.map(item => item.folio === folio ? respuesta.data : item);
        resultado = { ok: true, data: respuesta.data };
    } catch (error) {
        resultado = { ok: false, error: error.status === 409
            ? 'El caso cambió mientras lo editabas. Conserva tus notas, cierra el detalle y vuelve a abrirlo antes de guardar.' : error.message };
    } finally {
        const cargaAnterior = actualizacion?.promise;
        if (cargaAnterior) await cargaAnterior;
        if (contextoActual(version, cuenta)) await actualizarPanel({ allowBusy: true });
        guardando.value = false;
        responder(resultado ?? { ok: false, error: 'La sesión cambió durante la operación.' });
        await terminarOperacion();
    }
}

async function marcarLeida(id) {
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    try {
        const respuesta = await api.request(`/api/notifications/${encodeURIComponent(id)}/read`, { method: 'POST' });
        if (contextoActual(version, cuenta)) notificaciones.value = notificaciones.value.map(item => item.id === id ? respuesta.data : item);
    } catch (error) { if (contextoActual(version, cuenta)) mensaje.value = error.message; }
}

async function marcarTodas() {
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    try {
        await api.request('/api/notifications/read-all', { method: 'POST' });
        if (contextoActual(version, cuenta)) notificaciones.value = notificaciones.value.map(item => ({ ...item, leida: true }));
    } catch (error) { if (contextoActual(version, cuenta)) mensaje.value = error.message; }
}

async function abrirNotificacion(id) {
    if (ocupado.value) return;
    const solicitud = ++cargaDetalle;
    const aviso = notificaciones.value.find(item => item.id === id);
    if (!aviso?.folio) { mensaje.value = 'El caso ya no está disponible.'; return; }
    const version = versionSesion;
    const cuenta = claveCuenta(usuario.value);
    try {
        const respuesta = await api.request(`/api/incidents/${encodeURIComponent(aviso.folio)}`);
        if (!contextoActual(version, cuenta) || solicitud !== cargaDetalle) return;
        if (usuario.value.rol === 'Administrador') {
            seleccionado.value = respuesta.data;
            origenDetalle.value = 'incidentes';
            vistaAdmin.value = 'detalle-incidente';
        } else {
            await actualizarPanel();
            if (!contextoActual(version, cuenta) || solicitud !== cargaDetalle) return;
            vistaResponsable.value = respuesta.data.responsableId === usuario.value.id ? 'alertas' : 'mis-reportes';
            solicitudDetalle.value = { folio: aviso.folio };
        }
        await marcarLeida(id);
    } catch (error) {
        if (contextoActual(version, cuenta)) mensaje.value = [403, 404].includes(error.status) ? 'Este caso ya no está disponible para tu cuenta.' : error.message;
    }
}

function alVolverAPestana() {
    if (document.visibilityState === 'visible') revisarSesion();
}

onMounted(() => {
    window.addEventListener('focus', revisarSesion);
    document.addEventListener('visibilitychange', alVolverAPestana);
    navigator.serviceWorker?.addEventListener('message', alCambiarSuscripcion);
    if ('BroadcastChannel' in window) {
        try {
            canal = new BroadcastChannel('sigei-session');
            canal.onmessage = event => {
                if (event.data === 'session-changed') revisarSesion();
            };
        } catch {
            canal = null;
        }
    }
    inicializar();
    intervalo = window.setInterval(() => {
        if (document.visibilityState === 'visible' && pantalla.value === 'panel' && usuario.value && !ocupado.value) revisarSesion();
    }, 15000);
});

onBeforeUnmount(() => {
    desmontado = true;
    window.removeEventListener('focus', revisarSesion);
    document.removeEventListener('visibilitychange', alVolverAPestana);
    navigator.serviceWorker?.removeEventListener('message', alCambiarSuscripcion);
    canal?.close();
    window.clearInterval(intervalo);
    usuarios.value = [];
});
</script>

<template>
    <BotonPanico v-if="pantalla === 'panico'" :key="versionSesion" :demo="false" :ready="!cargando && !autenticando && !guardando && catalogosListos" :configuracion="configuracion" @registrar="registrarReporte">
        <template #acceso>
            <button v-if="!usuario" type="button" class="btn btn-outline-primary btn-sm" @click="pantalla = 'login'">Iniciar sesión</button>
            <template v-else>
                <button type="button" class="btn btn-outline-primary btn-sm" @click="volverAlPanel">Volver a mi panel</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="ocupado" @click="cerrarSesion">Cerrar sesión</button>
            </template>
            <span v-if="mensaje || errorLogin" role="status">{{ mensaje || errorLogin }}</span>
            <button v-if="!catalogosListos && !cargando" type="button" class="btn btn-outline-secondary btn-sm" @click="inicializar">Reintentar conexión</button>
        </template>
    </BotonPanico>

    <div v-else-if="pantalla === 'login'">
        <nav class="public-access" aria-label="Regresar al inicio">
            <button type="button" class="btn btn-outline-danger btn-sm" @click="abrirBotonPanico">Volver al botón de pánico</button>
            <button v-if="usuario" type="button" class="btn btn-outline-primary btn-sm" @click="volverAlPanel">Volver a mi panel</button>
        </nav>
        <Login real :configuracion="configuracion" :busy="cargando || autenticando" :error-text="errorLogin" @credentials="iniciarSesion">
            <div class="live-login-note">
                <p v-if="cargando" role="status">Comprobando la sesión…</p>
                <p>Acceso del personal institucional. Para reportar una emergencia no necesitas iniciar sesión.</p>
                <a href="/demo">Ver la demostración del diseño</a>
                <button v-if="errorLogin" type="button" class="retry-button" :disabled="cargando || autenticando" @click="inicializar">Volver a comprobar la conexión</button>
            </div>
        </Login>
    </div>

    <AdminLayout v-else-if="usuario?.rol === 'Administrador'" :demo="false" :busy="ocupado" :usuario="usuario" :configuracion="configuracion" :active-view="vistaAdmin === 'detalle-incidente' ? 'incidentes' : vistaAdmin" @navigate="cambiarVistaAdmin" @logout="cerrarSesion">
        <template #notificaciones>
            <Notificaciones real :push-state="estadoPush" :notificaciones="notificaciones" @abrir="abrirNotificacion" @leer="marcarLeida" @leer-todas="marcarTodas" @vincular="vincularDispositivo" @desvincular="desvincularDispositivo" @revisar-dispositivo="clientePush.refresh" />
            <button type="button" class="btn btn-outline-danger btn-sm" @click="abrirBotonPanico">Botón de pánico</button>
        </template>
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="ocupado || cargandoDatos" @click="actualizarPanel">{{ cargandoDatos ? 'Actualizando…' : 'Actualizar incidentes y avisos' }}</button>
        </div>
        <div v-if="mensaje" class="alert alert-info" role="status">{{ mensaje }}</div>
        <template v-if="vistaAdmin === 'usuarios'">
            <p v-if="cargandoUsuarios" role="status">Cargando cuentas…</p>
            <Usuarios :key="usuario.id" real :usuarios="usuarios" :saving="ocupado || cargandoUsuarios" @guardar="guardarUsuario" @cambiar-estado="cambiarEstado" />
        </template>
        <DetalleIncidente v-else-if="vistaAdmin === 'detalle-incidente' && seleccionado" :demo="false" :saving="guardando" :incidente="seleccionado" :usuarios="responsables" @guardar="guardarGestion" @volver="volverDeDetalle" />
        <template v-else-if="vistaAdmin === 'configuracion'">
            <p v-if="cargandoConfiguracion" role="status">Cargando configuración guardada…</p>
            <Configuracion v-if="configuracionAdministrativa" :key="usuario.id" real :configuracion="configuracionAdministrativa" :saving="ocupado || cargandoConfiguracion" @guardar="guardarConfiguracion" @recargar="cargarConfiguracion" />
            <button v-else-if="!cargandoConfiguracion" type="button" class="btn btn-outline-primary" @click="cargarConfiguracion">Reintentar cargar configuración</button>
        </template>
        <template v-else-if="datosCompletos">
            <Dashboard v-if="vistaAdmin === 'dashboard'" :demo="false" :incidentes="incidentes" @navigate="cambiarVistaAdmin" />
            <Incidentes v-else-if="vistaAdmin === 'incidentes'" :demo="false" :incidentes="incidentes" @gestionar="abrirDetalle" />
            <Reportes v-else-if="vistaAdmin === 'reportes'" :demo="false" :incidentes="incidentes" />
        </template>
        <p v-else role="status">{{ cargandoDatos ? 'Cargando los registros completos…' : 'No se cargaron los registros completos. Pulsa Actualizar para volver a intentarlo.' }}</p>
    </AdminLayout>

    <ResponsableLayout v-else-if="usuario?.rol === 'Responsable'" :demo="false" :busy="ocupado" :usuario="usuario" :configuracion="configuracion" :active-view="vistaResponsable" @navigate="cambiarVistaResponsable" @logout="cerrarSesion" @reportar="abrirBotonPanico">
        <template #notificaciones>
            <Notificaciones real :push-state="estadoPush" :notificaciones="notificaciones" @abrir="abrirNotificacion" @leer="marcarLeida" @leer-todas="marcarTodas" @vincular="vincularDispositivo" @desvincular="desvincularDispositivo" @revisar-dispositivo="clientePush.refresh" />
            <button type="button" class="btn btn-outline-danger btn-sm" @click="abrirBotonPanico">Botón de pánico</button>
        </template>
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="ocupado || cargandoDatos" @click="actualizarPanel">{{ cargandoDatos ? 'Actualizando…' : 'Actualizar casos y avisos' }}</button>
        </div>
        <div v-if="mensaje" class="alert alert-info" role="status">{{ mensaje }}</div>
        <template v-if="datosCompletos">
            <AlertasAsignadas v-if="vistaResponsable === 'alertas'" :demo="false" :saving="guardando" :incidentes="asignadas" :usuario="usuario" :solicitud-detalle="solicitudDetalle" @guardar="guardarSeguimiento" />
            <MisReportes v-else-if="vistaResponsable === 'mis-reportes'" :demo="false" :incidentes="misReportes" :usuario="usuario" :solicitud-detalle="solicitudDetalle" />
        </template>
        <p v-else role="status">{{ cargandoDatos ? 'Cargando tus casos…' : 'No se cargaron tus casos. Pulsa Actualizar para volver a intentarlo.' }}</p>
    </ResponsableLayout>
</template>

<style scoped>
.live-login-note {
    margin-top: 24px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.7;
    text-align: center;
}
.live-stage-note {
    max-width: 820px;
    padding: 28px;
    border: 1px solid #dbe4f0;
    border-radius: 12px;
    background: white;
    line-height: 1.7;
}
.live-stage-note h2 { color: #17365d; font-size: 23px; }
.live-stage-note p { color: #475569; }
.retry-button { width: 100%; }
</style>
