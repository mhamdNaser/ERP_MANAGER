import { documentStyles, escapeHtml, printHtml } from "../../utils/exportHtml";
import { STATUSES, formatMoney, formatNumber } from "./maintenanceMeta";

const HEADER_LINES = [
  "الجمهورية العربية السورية | Syrian Arab Republic",
  "وزارة الداخلية | Ministry of interior",
  "إدارة الاتصالات و الشبكات - فرع الصيانة",
];

const text = (value) => escapeHtml(value == null || value === "" ? "—" : String(value));

/** وصف المرشحات المطبّقة، كي يعرف قارئ الورقة أنها جزء من السجل لا كله. */
function filterSummary(filters, catalog, t) {
  const parts = [];
  if (filters.search) parts.push(`«${filters.search}»`);
  const category = catalog.categories.find((entry) => String(entry.id) === String(filters.category_id));
  if (category) parts.push(category.name);
  const type = category?.types?.find((entry) => String(entry.id) === String(filters.type_id));
  if (type) parts.push(type.name);
  const brand = catalog.brands.find((entry) => String(entry.id) === String(filters.brand_id));
  if (brand) parts.push(brand.name);
  if (filters.status) parts.push(t(`mt_status_${filters.status}`));
  if (filters.low_stock) parts.push(t("mt_lowStockOnly"));
  return parts.join(" · ");
}

/**
 * نسخة PDF من السجل المعروض: تُفتح للطباعة ويُحفظ منها PDF، كسائر تصديرات
 * النظام. تحمل المرشحات نفسها التي على الشاشة.
 */
export function printMaintenancePdf(items, filters, catalog, t) {
  const date = new Date().toISOString().slice(0, 10);
  const totals = Object.fromEntries(STATUSES.map((status) => [status.key, items.reduce((sum, item) => sum + (item.quantities?.[status.key] || 0), 0)]));
  const units = items.reduce((sum, item) => sum + item.total_quantity, 0);
  const value = items.reduce((sum, item) => sum + item.total_quantity * (item.unit_price || 0), 0);
  const filtered = filterSummary(filters, catalog, t);

  const rows = items
    .map(
      (item, index) => `<tr class="${item.is_low_stock ? "low" : ""}">
        <td>${index + 1}</td>
        <td class="ltr">${text(item.code)}</td>
        <td><b>${text(item.name)}</b>${item.part_number ? `<br><small class="ltr">${text(item.part_number)}</small>` : ""}</td>
        <td>${text(item.category?.name)}${item.type ? `<br><small>${text(item.type.name)}</small>` : ""}</td>
        <td>${text(item.brand?.name)}</td>
        <td>${text(item.device)}</td>
        ${STATUSES.map((status) => `<td class="num">${formatNumber(item.quantities?.[status.key])}</td>`).join("")}
        <td class="num"><b>${formatNumber(item.total_quantity)}</b> <small>${text(item.unit)}</small></td>
        <td class="num">${item.unit_price != null ? formatMoney(item.total_quantity * item.unit_price) : "—"}</td>
      </tr>`,
    )
    .join("");

  const html = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>${escapeHtml(t("mt_pdfTitle"))} ${date}</title>
  <style>${documentStyles}
    @page{size:A4 landscape;margin:12mm}
    body{padding:0 4px}
    .org{text-align:center;font-size:11px;font-weight:700;line-height:1.6;margin-bottom:10px}
    .summary{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin:10px 0 14px}
    .summary div{border:1px solid var(--line);border-radius:6px;padding:6px 8px;font-size:10px;color:var(--muted)}
    .summary b{display:block;font-size:15px;color:var(--ink)}
    .summary i{display:inline-block;width:8px;height:8px;border-radius:2px;margin-inline-end:4px}
    table{table-layout:auto}th,td{padding:5px 6px;font-size:9.5px}th{font-size:9.5px}
    td.num{text-align:left;white-space:nowrap}.ltr{direction:ltr;unicode-bidi:embed}
    small{color:var(--muted)}tr.low td{color:#a13e3e}
    tfoot td{font-weight:700;background:var(--light)}
    .sign{margin-top:28px;display:flex;justify-content:flex-end}.sign div{text-align:center;min-width:200px;font-size:11px;font-weight:700}
    .sign span{display:block;border-top:1px solid var(--line);margin-top:36px}
  </style></head><body>
  <div class="org">${HEADER_LINES.map(escapeHtml).join("<br>")}</div>
  <header>
    <div><h1>${escapeHtml(t("mt_pdfTitle"))}</h1>
    <p class="subtitle">${escapeHtml(t("mt_pdfSubtitle", { date, count: items.length }))}${filtered ? ` — ${escapeHtml(filtered)}` : ""}</p></div>
    <span class="chip">${escapeHtml(t("mt_eyebrow"))}</span>
  </header>
  <section class="summary">
    ${STATUSES.map((status) => `<div><i style="background:${status.color}"></i>${escapeHtml(t(`mt_status_${status.key}`))}<b>${formatNumber(totals[status.key])}</b></div>`).join("")}
    <div>${escapeHtml(t("mt_kpiUnits"))}<b>${formatNumber(units)}</b></div>
    <div>${escapeHtml(t("mt_kpiValue"))}<b>${formatMoney(value)}</b></div>
  </section>
  <table>
    <thead><tr>
      <th>#</th><th>${escapeHtml(t("mt_code"))}</th><th>${escapeHtml(t("mt_colItem"))}</th><th>${escapeHtml(t("mt_colCategory"))}</th>
      <th>${escapeHtml(t("mt_brand"))}</th><th>${escapeHtml(t("mt_colDevice"))}</th>
      ${STATUSES.map((status) => `<th>${escapeHtml(t(`mt_status_${status.key}`))}</th>`).join("")}
      <th>${escapeHtml(t("mt_colTotal"))}</th><th>${escapeHtml(t("mt_colValue"))}</th>
    </tr></thead>
    <tbody>${rows}</tbody>
    <tfoot><tr>
      <td colspan="6">${escapeHtml(t("mt_colTotal"))}</td>
      ${STATUSES.map((status) => `<td class="num">${formatNumber(totals[status.key])}</td>`).join("")}
      <td class="num">${formatNumber(units)}</td><td class="num">${formatMoney(value)}</td>
    </tr></tfoot>
  </table>
  <div class="sign"><div>${escapeHtml(t("mt_pdfSignature"))}<span></span></div></div>
  </body></html>`;

  printHtml(html, "width=1200,height=900");
}
