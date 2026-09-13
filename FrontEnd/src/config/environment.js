function required(name, value) {
    const normalized = value?.trim();
    if (!normalized)
        throw new Error(`Missing required environment variable: ${name}`);
    return normalized;
}
function positiveNumber(value, fallback) {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}
export const environment = Object.freeze({
    apiBaseUrl: required('VITE_API_BASE_URL', import.meta.env.VITE_API_BASE_URL).replace(/\/+$/, ''),
    apiTimeoutMs: positiveNumber(import.meta.env.VITE_API_TIMEOUT_MS, 300000),
});
