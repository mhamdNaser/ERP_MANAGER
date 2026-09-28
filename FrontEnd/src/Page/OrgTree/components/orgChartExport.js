import { downloadBlob } from "../../../utils/download";
import { escapeHtml, printHtml } from "../../../utils/exportHtml";
import { NODE_HEIGHT, NODE_WIDTH } from "./orgChartLayout";

/**
 * تصدير المخطط التنظيمي: SVG قائم بذاته يُطبع PDF أو يُحفظ صورة.
 *
 * يُبنى SVG من نفس مخرجات التخطيط لا من عناصر الصفحة، فلا يعتمد على
 * التقاط شاشة ولا على مكتبة خارجية، ويخرج بحدّة لا نهائية عند الطباعة.
 *
 * النصوص تُحاذى بـtext-anchor="end" وحدها بلا direction="rtl": الخاصيتان
 * معاً تعكسان معنى المرساة في SVG فيخرج النص من صندوقه، وخوارزمية bidi
 * ترتّب الحروف العربية من تلقائها.
 *
 * الخطوط محصورة في خطوط النظام: صورة SVG تُرسَم في canvas لا تُحمِّل خطاً
 * خارجياً، ولو أشارت إلى واحد لتلوّثت اللوحة وامتنع حفظ الصورة.
 */

const FONT = "'Noto Sans Arabic','Segoe UI',Tahoma,Arial,sans-serif";

const TONES = {
  root: { fill: "#0d655b", stroke: "#0d655b", label: "#ffffff", meta: "rgba(255,255,255,.75)" },
  branch: { fill: "#ffffff", stroke: "#dfe6e4", label: "#12211f", meta: "#687a76" },
  department: { fill: "#fafbfb", stroke: "#dfe6e4", label: "#12211f", meta: "#687a76" },
};

const LINE_COLOR = "#c7d3d0";
const TEXT_INSET = 12;

/** اقتطاع تقريبي: SVG لا يعرف text-overflow، فيُقصّ النص بحسب عرض الصندوق. */
function fit(text, fontSize) {
  const max = Math.floor((NODE_WIDTH - TEXT_INSET * 2) / (fontSize * 0.52));
  const value = String(text ?? "");
  return value.length > max ? `${value.slice(0, max - 1)}…` : value;
}

function nodeMarkup(node) {
  const tone = TONES[node.kind] ?? TONES.department;
  const right = node.x + NODE_WIDTH - TEXT_INSET;
  const hasMeta = Boolean(node.meta);
  const labelY = node.y + (hasMeta ? 24 : 32);

  const label =
    `<text x="${right}" y="${labelY}" text-anchor="end"` +
    ` font-family="${FONT}" font-size="12" font-weight="600" fill="${tone.label}">` +
    `${escapeHtml(fit(node.label, 12))}</text>`;

  const meta = hasMeta
    ? `<text x="${right}" y="${node.y + 40}" text-anchor="end"` +
      ` font-family="${FONT}" font-size="10" fill="${tone.meta}">` +
      `${escapeHtml(fit(node.meta, 10))}</text>`
    : "";

  return (
    `<g${node.inactive ? ' opacity="0.55"' : ""}>` +
    `<rect x="${node.x}" y="${node.y}" width="${NODE_WIDTH}" height="${NODE_HEIGHT}"` +
    ` rx="6" fill="${tone.fill}" stroke="${tone.stroke}" stroke-width="1"/>` +
    label + meta +
    `</g>`
  );
}

/** SVG قائم بذاته — بلا مراجع خارجية كي يُرسَم في canvas ويُحفظ صورة. */
export function chartToSvg(chart, title) {
  const lines = chart.lines
    .map(
      (line) =>
        `<line x1="${line.x1}" y1="${line.y1}" x2="${line.x2}" y2="${line.y2}"` +
        ` stroke="${LINE_COLOR}" stroke-width="1.5" stroke-linecap="round"/>`,
    )
    .join("");

  const nodes = chart.nodes.map(nodeMarkup).join("");
  const heading = title
    ? `<text x="${chart.width - 16}" y="26" text-anchor="end"` +
      ` font-family="${FONT}" font-size="15" font-weight="700" fill="#12211f">` +
      `${escapeHtml(title)}</text>`
    : "";
  const top = title ? 44 : 0;

  return (
    `<svg xmlns="http://www.w3.org/2000/svg" width="${chart.width}" height="${chart.height + top}"` +
    ` viewBox="0 0 ${chart.width} ${chart.height + top}">` +
    `<rect width="100%" height="100%" fill="#ffffff"/>` +
    heading +
    `<g transform="translate(0,${top})">${lines}${nodes}</g>` +
    `</svg>`
  );
}

function fileStamp() {
  return new Date().toISOString().slice(0, 10);
}

/** يفتح نافذة طباعة — ومنها يحفظ المستخدم PDF. */
export function printChart(chart, title) {
  const svg = chartToSvg(chart, title);
  // landscape لأن الشجرة تمتد عرضاً، وتصغيرها إلى عرض الصفحة يمنع قصّها.
  printHtml(
    `<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8">` +
      `<title>${escapeHtml(title)}</title><style>` +
      `@page{size:A4 landscape;margin:10mm}` +
      `body{margin:0;display:grid;place-items:center;background:#fff}` +
      `svg{max-width:100%;height:auto}` +
      `</style></head><body>${svg}</body></html>`,
    "width=1200,height=850",
  );
}

/** يحفظ المخطط صورة PNG بدقة مضاعفة كي تبقى حادّة عند الطباعة. */
export function downloadChartPng(chart, title, scale = 2) {
  return new Promise((resolve, reject) => {
    const svg = chartToSvg(chart, title);
    const url = URL.createObjectURL(
      new Blob([svg], { type: "image/svg+xml;charset=utf-8" }),
    );
    const image = new Image();

    image.onload = () => {
      const canvas = document.createElement("canvas");
      canvas.width = image.width * scale;
      canvas.height = image.height * scale;
      const context = canvas.getContext("2d");
      context.fillStyle = "#ffffff";
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.drawImage(image, 0, 0, canvas.width, canvas.height);
      URL.revokeObjectURL(url);

      canvas.toBlob((blob) => {
        if (!blob) {
          reject(new Error("PNG"));
          return;
        }
        downloadBlob(blob, `org-chart-${fileStamp()}.png`);
        resolve();
      }, "image/png");
    };

    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("SVG"));
    };

    image.src = url;
  });
}
