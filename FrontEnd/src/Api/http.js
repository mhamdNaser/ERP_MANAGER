import { environment } from '../config/environment';
import { language, token } from './storage';
import { tr } from '../i18n/tr';
const fallbackMessage = () => tr('api_requestFailed');
async function fetchFromApi(path, options) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), environment.apiTimeoutMs);
    try {
        return await fetch(`${environment.apiBaseUrl}${path}`, { ...options, signal: controller.signal });
    }
    catch (error) {
        if (error.name === 'AbortError')
            throw new Error(tr('api_timeout'), { cause: error });
        throw error;
    }
    finally {
        window.clearTimeout(timeout);
    }
}
function headers(options, json = true) {
    return {
        ...(json ? { 'Content-Type': 'application/json' } : {}),
        Accept: 'application/json',
        'X-Language': language.get(),
        ...(token.get() ? { Authorization: `Bearer ${token.get()}` } : {}),
        ...options.headers,
    };
}
export async function request(path, options = {}) {
    const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
    const response = await fetchFromApi(path, { ...options, headers: headers(options, !isFormData) });
    const data = await response.json().catch(() => ({}));
    if (response.status === 413)
        throw new Error(tr('api_payloadTooLarge'));
    if (!response.ok) {
        // جسم الرد يُرفق بالخطأ: بعض الردود تحمل تفاصيل تحتاجها الشاشة
        // (حقول ناقصة مثلاً) لا رسالةً فقط.
        const error = new Error(data.message || fallbackMessage());
        error.status = response.status;
        error.data = data;
        throw error;
    }
    return data;
}
export async function requestBlob(path, options = {}) {
    const response = await fetchFromApi(path, { ...options, headers: headers(options, false) });
    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        if (response.status === 413)
            throw new Error(tr('api_requestTooLarge'));
        throw new Error(data.message || fallbackMessage());
    }
    try {
        return await response.blob();
    }
    catch (error) {
        throw new Error(tr('api_blobFailed'), { cause: error });
    }
}

export function requestBlobProgress(path, { method = 'POST', body, onProgress, headers: customHeaders = {} } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, `${environment.apiBaseUrl}${path}`);
        xhr.timeout = environment.apiTimeoutMs;
        xhr.responseType = 'blob';
        const requestHeaders = headers({ headers: customHeaders }, false);
        Object.entries(requestHeaders).forEach(([key, value]) => {
            if (value) xhr.setRequestHeader(key, value);
        });
        if (typeof onProgress === 'function') {
            onProgress({ phase: 'preparing', loaded: 0, total: 0, percent: 0, lengthComputable: false });
        }
        xhr.onprogress = (event) => {
            if (typeof onProgress !== 'function') return;
            onProgress({
                phase: 'downloading',
                loaded: event.loaded,
                total: event.lengthComputable ? event.total : 0,
                percent: event.lengthComputable ? Math.min(100, Math.round((event.loaded / event.total) * 100)) : 0,
                lengthComputable: event.lengthComputable,
            });
        };
        xhr.onload = () => {
            if (xhr.status === 413) {
                reject(new Error(tr('api_requestTooLarge')));
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300) {
                readBlobError(xhr.response).then((message) => reject(new Error(message || fallbackMessage())));
                return;
            }
            resolve(xhr.response);
        };
        xhr.onerror = () => reject(new Error(tr('api_archiveConnFailed')));
        xhr.ontimeout = () => reject(new Error(tr('api_archiveTimeout')));
        xhr.send(body);
    });
}

export function requestUpload(path, { method = 'POST', body, onProgress, headers: customHeaders = {} } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, `${environment.apiBaseUrl}${path}`);
        xhr.timeout = environment.apiTimeoutMs;
        const requestHeaders = headers({ headers: customHeaders }, false);
        Object.entries(requestHeaders).forEach(([key, value]) => {
            if (value) xhr.setRequestHeader(key, value);
        });
        xhr.upload.onprogress = (event) => {
            if (!event.lengthComputable || typeof onProgress !== 'function') return;
            onProgress({
                loaded: event.loaded,
                total: event.total,
                percent: Math.min(100, Math.round((event.loaded / event.total) * 100)),
            });
        };
        xhr.onload = () => {
            const data = xhr.responseText ? safeJsonParse(xhr.responseText) : {};
            if (xhr.status === 413) {
                reject(new Error(tr('api_payloadTooLarge')));
                return;
            }
            if (xhr.status < 200 || xhr.status >= 300) {
                reject(new Error(data.message || fallbackMessage()));
                return;
            }
            resolve(data);
        };
        xhr.onerror = () => reject(new Error(tr('api_uploadConnFailed')));
        xhr.ontimeout = () => reject(new Error(tr('api_uploadTimeout')));
        xhr.send(body);
    });
}

function safeJsonParse(value) {
    try {
        return JSON.parse(value);
    }
    catch {
        return {};
    }
}

async function readBlobError(blob) {
    if (!blob) return '';
    try {
        const text = await blob.text();
        return safeJsonParse(text).message || '';
    }
    catch {
        return '';
    }
}
