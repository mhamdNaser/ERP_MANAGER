import { Boxes, DollarSign, Layers, Percent, TriangleAlert } from "lucide-react";
import { useEffect, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { STATUSES, formatMoney, formatNumber, statusOf } from "../maintenanceMeta";
import { MovementLine } from "./MovementLine";
import { StatusBar, StatusLegend } from "./StatusParts";

const INK = "#12211f";
const MUTED = "#687a76";
const LINE = "#c7d3d0";

function Kpi({ icon: Icon, label, value, hint, tone }) {
  return (
    <p className="flex flex-col gap-1 rounded border border-line bg-surface p-4">
      <small className="flex items-center gap-1.5 text-xs font-semibold text-muted">
        <Icon size={13} aria-hidden />
        {label}
      </small>
      <b className={`text-xl font-semibold ${tone === "danger" ? "text-danger-500" : "text-ink"}`}>{value}</b>
      {hint && <small className="text-[11px] text-muted">{hint}</small>}
    </p>
  );
}

function Card({ title, hint, action, children }) {
  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{title}</h2>
          {hint && <p className="page-subtitle mt-0.5 text-xs">{hint}</p>}
        </div>
        {action}
      </header>
      <div className="card-body">{children}</div>
    </section>
  );
}

// مواضع المربعات في مخطط سير العمل، مرسومة من اليمين إلى اليسار كما يُقرأ.
const NODES = {
  in_stock: { x: 545, y: 32 },
  under_maintenance: { x: 340, y: 32 },
  repaired: { x: 135, y: 32 },
  ready: { x: 135, y: 196 },
  damaged: { x: 340, y: 196 },
};
const NODE_W = 130;
const NODE_H = 64;

// المسار الرئيسي وحده يُرسم أسهماً؛ باقي الانتقالات في الجدول تحته.
const EDGES = [
  { from: null, to: "in_stock", d: "M 718 64 H 681", label: [700, 54] },
  { from: "in_stock", to: "under_maintenance", d: "M 545 64 H 476", label: [510, 54] },
  { from: "under_maintenance", to: "repaired", d: "M 340 64 H 271", label: [305, 54] },
  { from: "repaired", to: "ready", d: "M 200 96 V 190", label: [214, 146], anchor: "start" },
  { from: "under_maintenance", to: "damaged", d: "M 405 96 V 190", label: [419, 124], anchor: "start" },
  { from: "in_stock", to: "ready", d: "M 610 96 V 158 H 240 V 190", label: [470, 150] },
];

function flowQuantity(flow, from, to) {
  return flow.filter((edge) => (edge.from ?? null) === from && (edge.to ?? null) === to).reduce((sum, edge) => sum + edge.quantity, 0);
}

/** سير العمل: كل حالة بعدد قطعها الآن، وكل سهم بما عبره خلال المدة. */
function WorkflowDiagram({ stats }) {
  const { t } = useLanguage();
  const units = Object.fromEntries(stats.by_status.map((row) => [row.status, row.units]));
  const issued = stats.flow.filter((edge) => edge.kind === "issue").reduce((sum, edge) => sum + edge.quantity, 0);

  return (
    // الإحداثيات مرسومة من اليمين إلى اليسار سلفاً؛ اتجاه الصفحة لا يجب أن
    // يرث إلى SVG، وإلا انقلب معنى text-anchor وخرجت العناوين من مربعاتها.
    <svg viewBox="0 0 820 280" className="h-auto w-full" style={{ direction: "ltr" }} role="img" aria-label={t("mt_flowTitle")}>
      <defs>
        <marker id="mt-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
          <path d="M 0 0 L 10 5 L 0 10 z" fill={MUTED} />
        </marker>
      </defs>

      {EDGES.map((edge) => {
        const quantity = flowQuantity(stats.flow, edge.from, edge.to);
        return (
          <g key={`${edge.from}-${edge.to}`}>
            <title>{`${edge.from ? t(`mt_status_${edge.from}`) : t("mt_flowIn")} ← ${t(`mt_status_${edge.to}`)}: ${formatNumber(quantity)}`}</title>
            <path d={edge.d} fill="none" stroke={quantity ? MUTED : LINE} strokeWidth="2" strokeDasharray={quantity ? undefined : "4 4"} markerEnd="url(#mt-arrow)" strokeLinejoin="round" />
            <text x={edge.label[0]} y={edge.label[1]} textAnchor={edge.anchor || "middle"} fontSize="12" fontWeight="600" fill={quantity ? INK : MUTED}>
              {formatNumber(quantity)}
            </text>
          </g>
        );
      })}

      {/* الإدخال والصرف: طرفا المسار خارج المستودع. */}
      <g>
        <rect x="720" y="44" width="92" height="40" rx="6" fill="#ffffff" stroke={LINE} strokeDasharray="4 3" />
        <text x="766" y="69" textAnchor="middle" fontSize="12" fill={MUTED}>{t("mt_flowIn")}</text>
      </g>
      <g>
        <title>{`${t("mt_flowOut")}: ${formatNumber(issued)}`}</title>
        <path d="M 135 228 H 106" fill="none" stroke={issued ? MUTED : LINE} strokeWidth="2" strokeDasharray={issued ? undefined : "4 4"} markerEnd="url(#mt-arrow)" />
        <rect x="8" y="208" width="96" height="40" rx="6" fill="#ffffff" stroke={LINE} strokeDasharray="4 3" />
        <text x="56" y="226" textAnchor="middle" fontSize="11" fill={MUTED}>{t("mt_flowOut")}</text>
        <text x="56" y="241" textAnchor="middle" fontSize="12" fontWeight="600" fill={INK}>{formatNumber(issued)}</text>
      </g>

      {STATUSES.map((status) => {
        const node = NODES[status.key];
        return (
          <g key={status.key}>
            <title>{`${t(`mt_status_${status.key}`)}: ${formatNumber(units[status.key])} — ${t(`mt_statusHint_${status.key}`)}`}</title>
            <rect x={node.x} y={node.y} width={NODE_W} height={NODE_H} rx="6" fill="#ffffff" stroke={LINE} strokeWidth="1.5" />
            <rect x={node.x + NODE_W - 5} y={node.y + 8} width="4" height={NODE_H - 16} rx="2" fill={status.color} />
            <text x={node.x + NODE_W - 14} y={node.y + 25} textAnchor="end" fontSize="12" fill={MUTED}>{t(`mt_status_${status.key}`)}</text>
            <text x={node.x + NODE_W - 14} y={node.y + 49} textAnchor="end" fontSize="20" fontWeight="700" fill={INK}>{formatNumber(units[status.key])}</text>
          </g>
        );
      })}
    </svg>
  );
}

/** كل انتقال وقع خلال المدة — بديل مقروء للمخطط، ويضم ما لا يرسمه. */
function FlowTable({ flow }) {
  const { t } = useLanguage();
  if (!flow.length) return <p className="empty-state py-6">{t("mt_noMovements")}</p>;

  return (
    <div className="table-wrap">
      <table className="table text-xs">
        <thead>
          <tr className="bg-subtle text-muted">
            <th className="p-2 text-start font-semibold">{t("mt_from")}</th>
            <th className="p-2 text-start font-semibold">{t("mt_to")}</th>
            <th className="p-2 text-end font-semibold">{t("mt_quantity")}</th>
          </tr>
        </thead>
        <tbody>
          {flow.map((edge) => (
            <tr key={`${edge.from}-${edge.to}`} className="border-t border-line">
              <td className="p-2">{edge.from ? t(`mt_status_${edge.from}`) : t("mt_flowIn")}</td>
              <td className="p-2">{edge.to ? t(`mt_status_${edge.to}`) : t("mt_flowOut")}</td>
              <td className="p-2 text-end font-semibold">{formatNumber(edge.quantity)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** أين القطع الآن: شريط لكل حالة، والرقم مكتوب بجانبه. */
function Distribution({ rows, total }) {
  const { t } = useLanguage();
  const max = Math.max(1, ...rows.map((row) => row.units));

  return (
    <div className="flex flex-col gap-3">
      {rows.map((row) => {
        const status = statusOf(row.status);
        const Icon = status.icon;
        const share = total ? Math.round((row.units / total) * 100) : 0;
        return (
          <article key={row.status} className="flex flex-col gap-1" title={`${formatNumber(row.units)} · ${formatMoney(row.value)}`}>
            <header className="flex items-center justify-between gap-2 text-xs">
              <span className="flex items-center gap-1.5 text-ink">
                <Icon size={13} style={{ color: status.color }} aria-hidden />
                <b className="font-semibold">{t(`mt_status_${row.status}`)}</b>
              </span>
              <span className="flex items-center gap-2">
                <b className="font-semibold text-ink">{formatNumber(row.units)}</b>
                <small className="text-muted">{share}% · {t("mt_itemsCount", { count: row.items })}</small>
              </span>
            </header>
            <span className="block h-2 w-full rounded bg-canvas">
              <i className="block h-2 rounded" style={{ width: `${row.units ? Math.max(2, (row.units / max) * 100) : 0}%`, background: status.color }} />
            </span>
          </article>
        );
      })}
    </div>
  );
}

const WEEKLY_SERIES = [
  ["received", "in_stock"],
  ["to_maintenance", "under_maintenance"],
  ["repaired", "repaired"],
  ["damaged", "damaged"],
];

/** أعمدة متجاورة لكل أسبوع، كل سلسلة بلون الحالة التي تنتهي إليها. */
function Weekly({ weeks, asTable }) {
  const { t } = useLanguage();
  const max = Math.max(1, ...weeks.flatMap((week) => WEEKLY_SERIES.map(([key]) => week[key])));

  if (asTable) {
    return (
      <div className="table-wrap">
        <table className="table text-xs">
          <thead>
            <tr className="bg-subtle text-muted">
              <th className="p-2 text-start font-semibold">{t("mt_weekTooltip", { week: "" })}</th>
              {WEEKLY_SERIES.map(([key]) => <th key={key} className="p-2 text-end font-semibold">{t(`mt_weekly_${key}`)}</th>)}
            </tr>
          </thead>
          <tbody>
            {weeks.map((week) => (
              <tr key={week.key} className="border-t border-line">
                <td className="p-2 [direction:ltr] text-start">{week.label}</td>
                {WEEKLY_SERIES.map(([key]) => <td key={key} className="p-2 text-end">{formatNumber(week[key])}</td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  }

  return (
    <>
      <div className="flex h-44 items-end gap-2 border-b border-line">
        {weeks.map((week) => (
          <article key={week.key} className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
            <div className="flex h-full w-full items-end justify-center gap-[2px]">
              {WEEKLY_SERIES.map(([key, status]) => (
                <i
                  key={key}
                  className="block w-2 rounded-t-[4px]"
                  title={`${t("mt_weekTooltip", { week: week.label })} — ${t(`mt_weekly_${key}`)}: ${formatNumber(week[key])}`}
                  style={{ height: week[key] ? `${Math.max(3, (week[key] / max) * 100)}%` : 0, background: statusOf(status).color }}
                />
              ))}
            </div>
            <small className="text-[10px] text-muted [direction:ltr]">{week.label}</small>
          </article>
        ))}
      </div>
      <footer className="mt-3 flex flex-wrap items-center gap-3">
        {WEEKLY_SERIES.map(([key, status]) => (
          <span key={key} className="flex items-center gap-1.5 text-xs text-muted">
            <i className="h-2.5 w-2.5 rounded-[2px]" style={{ background: statusOf(status).color }} />
            {t(`mt_weekly_${key}`)}
          </span>
        ))}
      </footer>
    </>
  );
}

function ByCategory({ rows }) {
  const { t } = useLanguage();
  if (!rows.length) return <p className="empty-state py-6">{t("mt_noItems")}</p>;

  return (
    <div className="flex flex-col gap-3">
      <StatusLegend />
      {rows.map((row) => (
        <article key={row.id ?? 0} className="flex flex-col gap-1">
          <header className="flex items-center justify-between gap-2 text-xs">
            <b className="font-semibold text-ink">{row.name || t("mt_noCategory")}</b>
            <span className="text-muted">
              <b className="font-semibold text-ink">{formatNumber(row.units)}</b> · {t("mt_itemsCount", { count: row.items })} · {formatMoney(row.value)}
            </span>
          </header>
          <StatusBar quantities={row.quantities} height="h-2.5" />
        </article>
      ))}
    </div>
  );
}

export function MaintenanceStats({ revision, notify }) {
  const { t } = useLanguage();
  const [stats, setStats] = useState(null);
  const [weeklyTable, setWeeklyTable] = useState(false);

  useEffect(() => {
    let alive = true;
    api.maintenanceStatistics().then((data) => alive && setStats(data)).catch((error) => notify?.(error.message, "error"));
    return () => { alive = false; };
  }, [revision, notify]);

  if (!stats) return <p className="empty-state">…</p>;
  const { totals } = stats;

  return (
    <div className="flex flex-col gap-4">
      <section className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <Kpi icon={Layers} label={t("mt_kpiItems")} value={formatNumber(totals.items)} />
        <Kpi icon={Boxes} label={t("mt_kpiUnits")} value={formatNumber(totals.units)} />
        <Kpi icon={DollarSign} label={t("mt_kpiValue")} value={formatMoney(totals.value)} />
        <Kpi icon={TriangleAlert} label={t("mt_kpiLowStock")} value={formatNumber(totals.low_stock)} tone={totals.low_stock ? "danger" : null} />
        <Kpi
          icon={Percent}
          label={t("mt_kpiRepairRate")}
          value={stats.repair_rate == null ? t("mt_noRate") : `${stats.repair_rate}%`}
          hint={t("mt_kpiRepairRateHint", { days: stats.flow_days })}
        />
      </section>

      <Card title={t("mt_flowTitle")} hint={t("mt_flowHint", { days: stats.flow_days })}>
        <div className="flex flex-col gap-4">
          <div className="overflow-x-auto"><div className="min-w-[560px]"><WorkflowDiagram stats={stats} /></div></div>
          <FlowTable flow={stats.flow} />
        </div>
      </Card>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Card title={t("mt_distributionTitle")} hint={t("mt_distributionHint")}>
          <Distribution rows={stats.by_status} total={totals.units} />
        </Card>
        <Card
          title={t("mt_weeklyTitle")}
          hint={t("mt_weeklyHint")}
          action={
            <button className="btn btn-ghost btn-sm" onClick={() => setWeeklyTable((value) => !value)}>
              {t(weeklyTable ? "mt_showChart" : "mt_showTable")}
            </button>
          }
        >
          <Weekly weeks={stats.weekly} asTable={weeklyTable} />
        </Card>
        <Card title={t("mt_byCategoryTitle")} hint={t("mt_byCategoryHint")}>
          <ByCategory rows={stats.by_category} />
        </Card>
        <Card title={t("mt_recentTitle")}>
          {stats.recent.length ? (
            <ol className="flex flex-col divide-y divide-line rounded border border-line">
              {stats.recent.map((movement) => <MovementLine key={movement.id} movement={movement} showItem />)}
            </ol>
          ) : (
            <p className="empty-state py-6">{t("mt_noMovements")}</p>
          )}
        </Card>
      </div>
    </div>
  );
}
