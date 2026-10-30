export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export function createSessionApi({ onUnauthorized, onCsrfExpired, onForbidden } = {}) {
    let csrfToken = '';

    async function request(path, { method = 'GET', body } = {}) {
        let response;
        try {
            response = await fetch(path, {
                method,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
                    ...(method === 'GET' ? {} : { 'X-CSRF-TOKEN': csrfToken }),
                },
                ...(body === undefined ? {} : { body: JSON.stringify(body) }),
            });
        } catch {
            throw new ApiError('No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.', 0);
        }

        const data = await response.json().catch(() => ({}));
        if (typeof data.csrf_token === 'string') csrfToken = data.csrf_token;
        if (response.ok) return data;

        if (response.status === 401) onUnauthorized?.();
        if (response.status === 419) await onCsrfExpired?.();
        if (response.status === 403) await onForbidden?.();

        const validationMessage = Object.values(data.errors ?? {}).flat().find(value => typeof value === 'string');
        const message = response.status >= 500
            ? 'No se pudo completar la solicitud. Inténtalo de nuevo.'
            : response.status === 419
                ? 'La sesión de seguridad cambió. Revisa tu cuenta y vuelve a intentar la operación.'
                : response.status === 401
                    ? 'Tu sesión terminó. Inicia sesión nuevamente.'
                    : validationMessage ?? data.message ?? 'No se pudo completar la solicitud.';

        throw new ApiError(message, response.status, data.errors);
    }

    return { request };
}
