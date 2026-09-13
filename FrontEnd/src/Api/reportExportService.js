import { documentStyles, downloadHtml, escapeHtml, formatDate, printHtml, textValue } from '../utils/exportHtml';
function reportRows(report) {
    const rows = [
        ['الملخص التنفيذي', report.summary],
        ['الإنجازات', report.achievements],
        ['التحديات والملاحظات', report.challenges],
        ['الخطوات التالية', report.next_steps],
    ];
    return rows.map(([label, value], index) => `<tr><td class="number">${index + 1}</td><td class="label">${escapeHtml(label)}</td><td>${textValue(value, 'لا توجد تفاصيل.')}</td></tr>`).join('');
}
function reportSheet(report, index) {
    return `<section class="sheet"><header><div class="brand"><div class="mark">CND</div><div><strong>إدارة الشبكات والاتصالات</strong><small>التقارير وخطط العمل</small></div></div><div class="chip">تقرير ${index + 1}</div></header>
  <h1>${textValue(report.title, 'تقرير عمل')}</h1><p class="subtitle">${textValue(report.employee?.name)} · ${textValue(report.department?.name)} · ${textValue(report.branch?.name)}</p>
  <table class="meta"><tbody><tr><th>الفترة</th><td>${formatDate(report.period_start)}</td><th>الحالة</th><td>${textValue(report.status)}</td></tr><tr><th>الفرع</th><td>${textValue(report.branch?.name)}</td><th>القسم</th><td>${textValue(report.department?.name)}</td></tr><tr><th>مرسل التقرير</th><td>${textValue(report.employee?.job_title || report.employee?.name)}</td><th>تاريخ الإنشاء</th><td>${formatDate(report.created_at)}</td></tr></tbody></table>
  <h2>تفاصيل التقرير</h2><table><thead><tr><th class="number">الرقم</th><th class="label">البند</th><th>التفاصيل</th></tr></thead><tbody>${reportRows(report)}</tbody></table></section>`;
}
function reportDocument(reports) {
    return `<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>تقارير المؤسسة</title><style>@page{size:Letter;margin:18mm 16mm}${documentStyles}.meta{margin-bottom:18px}.meta th{width:14%}.number{width:65px;text-align:center}.label{width:28%;font-weight:700}h2{font-size:15px;color:var(--forest)}</style></head><body>${reports.map(reportSheet).join('')}</body></html>`;
}
export function exportReports(reports, format) {
    if (!reports.length)
        return;
    const html = reportDocument(reports);
    if (format === 'word')
        return downloadHtml(html, 'application/msword;charset=utf-8', `CND-reports-${new Date().toISOString().slice(0, 10)}.doc`);
    printHtml(html);
}
