import { documentStyles, downloadHtml, escapeHtml, formatDate, printHtml, textValue } from '../utils/exportHtml';
function columns(form) {
    return form.fields.map(field => ({ key: field.field_key, label: field.label }));
}
function formValue(value) {
    if (Array.isArray(value))
        return value.length ? value.join('، ') : '—';
    if (typeof value === 'boolean')
        return value ? 'نعم' : 'لا';
    return String(value ?? '—');
}
function submissionRows(form, submissions) {
    const fields = columns(form);
    return submissions.map(item => `<tr><td>${item.version}</td><td>${escapeHtml(item.user.name)}</td><td>${escapeHtml(item.user.job_title || item.user.role)}</td><td>${escapeHtml(item.user.branch?.name || '—')}</td><td>${escapeHtml(item.user.department?.name || '—')}</td><td>${formatDate(item.submitted_at, true)}</td>${fields.map(field => `<td>${textValue(formValue(item.payload?.[field.key]))}</td>`).join('')}</tr>`).join('');
}
function submissionsTable(form, submissions) {
    const headers = ['النسخة', 'الموظف', 'المسمى الوظيفي', 'الفرع', 'القسم', 'تاريخ التعبئة', ...columns(form).map(field => field.label)];
    return `<table><thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${submissionRows(form, submissions)}</tbody></table>`;
}
function pdfDocument(form, submissions) {
    return `<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>${escapeHtml(form.title)}</title><style>@page{size:Letter landscape;margin:14mm}${documentStyles}th,td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}</style></head><body><section class="sheet"><header><div class="brand"><div class="mark">CND</div><div><strong>إدارة الشبكات والاتصالات</strong><small>تعبئات النموذج</small></div></div><div class="chip">عدد التعبئات: ${submissions.length}</div></header><h1>${escapeHtml(form.title)}</h1><p class="subtitle">${escapeHtml(form.description || 'سجل بيانات تعبئة النموذج')}</p>${submissionsTable(form, submissions)}</section></body></html>`;
}
function excelDocument(form, submissions) {
    return `<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><style>${documentStyles}</style></head><body>${submissionsTable(form, submissions)}</body></html>`;
}
export function exportFormSubmissions(form, submissions, format) {
    if (!submissions.length)
        return;
    if (format === 'excel') {
        const safeTitle = form.title.replaceAll(/[\\/:*?"<>|]/g, '_');
        return downloadHtml(excelDocument(form, submissions), 'application/vnd.ms-excel;charset=utf-8', `${safeTitle}-${new Date().toISOString().slice(0, 10)}.xls`);
    }
    printHtml(pdfDocument(form, submissions), 'width=1200,height=900');
}
