export async function loadAllPages(request, path, { isCurrent = () => true } = {}) {
    const collected = [];
    let lastPage = 1;
    for (let page = 1; page <= lastPage; page++) {
        if (!isCurrent()) return null;
        const separator = path.includes('?') ? '&' : '?';
        const response = await request(`${path}${separator}per_page=100&page=${page}`);
        if (!isCurrent()) return null;
        if (!Array.isArray(response.data) || !Number.isInteger(response.meta?.last_page) || response.meta.last_page < page) {
            throw new Error('No se pudo cargar la lista completa. Vuelve a actualizar.');
        }
        lastPage = response.meta.last_page;
        collected.push(...response.data);
    }
    return [...new Map(collected.map(item => [item.id ?? item.folio, item])).values()];
}

export function requireIncidentReceipt(data) {
    if (!data || typeof data.folio !== 'string' || !/^SIG-[0-9A-HJKMNP-TV-Z]{26}$/.test(data.folio)
        || typeof data.fecha !== 'string' || !/^\d{2}\/\d{2}\/\d{4}$/.test(data.fecha)
        || typeof data.hora !== 'string' || !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(data.hora)) {
        throw new Error('No se recibió un comprobante válido. Conserva los datos y vuelve a intentarlo.');
    }
    return Object.fromEntries(['folio', 'tipo', 'ubicacion', 'fecha', 'hora', 'reportadoEn', 'descripcion', 'estado', 'prioridad'].map(key => [key, data[key]]));
}
