export function escapeHtml(value) {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
export function textValue(value, fallback = "—") {
  const trimmed = value?.trim();
  return escapeHtml(trimmed && trimmed.length ? trimmed : fallback);
}
export function formatDate(value, withTime = false) {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return escapeHtml(value);
  const options = {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    ...(withTime ? { hour: "2-digit", minute: "2-digit" } : {}),
  };
  return date.toLocaleString("ar-EG", options);
}
export function downloadHtml(html, type, fileName) {
  const url = URL.createObjectURL(new Blob([`\ufeff${html}`], { type }));
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = fileName;
  anchor.click();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
export function printHtml(html, size = "width=1000,height=800") {
  const printWindow = window.open("", "_blank", size);
  if (!printWindow) return;
  printWindow.document.write(html);
  printWindow.document.close();
  printWindow.focus();
  window.setTimeout(() => printWindow.print(), 300);
}
export const documentStyles = `
  :root{--forest:#06312D;--light:#f4f9f7;--line:#cfded9;--ink:#17201d;--muted:#5f6f6b;--gold:#c27c21}
  *{box-sizing:border-box}body{font-family:'Noto Sans Arabic',Tahoma,Arial,sans-serif;color:var(--ink);margin:0;line-height:1.7;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .sheet{page-break-after:always}.sheet:last-child{page-break-after:auto}
  header{display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:3px solid var(--forest);padding-bottom:12px;margin-bottom:16px}
  .brand{display:flex;align-items:center;gap:10px}.mark{width:48px;height:48px;border-radius:8px;background:var(--forest);color:#fff;display:grid;place-items:center;font-weight:700}
  .brand strong,.brand small{display:block}.brand small,.subtitle{color:var(--muted)}.chip{background:var(--light);border:1px solid var(--line);border-right:4px solid var(--gold);padding:6px 11px;font-size:11px;font-weight:700}
  h1{color:var(--forest);font-size:27px;margin:5px 0}.subtitle{font-size:12px;margin:0 0 15px}
  table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid var(--line);padding:9px 10px;font-size:11px;vertical-align:top}th{background:var(--forest);color:#fff}tbody tr:nth-child(even) td{background:var(--light)}
`;
