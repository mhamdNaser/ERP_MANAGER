export const language = {
    get: () => localStorage.getItem('LANGUAGE') || 'ar',
    set: (value) => {
        localStorage.setItem('LANGUAGE', value);
        document.documentElement.lang = value;
        document.documentElement.dir = value === 'ar' ? 'rtl' : 'ltr';
    },
};
export const token = {
    get: () => localStorage.getItem('cnd_token'),
    set: (value) => localStorage.setItem('cnd_token', value),
    clear: () => localStorage.removeItem('cnd_token'),
};
