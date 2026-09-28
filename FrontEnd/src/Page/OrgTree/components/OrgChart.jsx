import { Building2, Landmark, Users } from "lucide-react";
import { useMemo } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import {
  buildTree,
  layoutTree,
  NODE_HEIGHT,
  NODE_WIDTH,
} from "./orgChartLayout";

const ICONS = { root: Landmark, branch: Building2, department: Users };

const TONES = {
  root: "border-brand-500 bg-brand-500 text-white",
  branch: "border-line bg-surface text-ink",
  department: "border-line bg-subtle text-ink",
};

/**
 * ديجرام الشجرة: الإدارة في الأعلى، تحتها الأفرع، وتحت كل فرع أقسامه.
 *
 * الخطوط في طبقة SVG والعقد عناصر HTML فوقها — فيبقى النص العربي والاقتطاع
 * والثيم على حالها بدل التصارع مع نصّ SVG.
 */
export function OrgChart({ branches, departments }) {
  const { t } = useLanguage();

  const chart = useMemo(() => {
    const tree = buildTree(
      branches,
      departments,
      t("orgChartRoot"),
      t("orgChartLooseHint"),
    );
    return layoutTree(tree);
  }, [branches, departments, t]);

  const isEmpty = branches.length === 0 && departments.length === 0;

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("orgChartTitle")}</h2>
          <p className="text-xs text-muted">{t("orgChartHint")}</p>
        </div>
      </div>

      {isEmpty ? (
        <p className="px-4 py-8 text-center text-xs text-muted">
          {t("orgChartEmpty")}
        </p>
      ) : (
        // الشجرة قد تتجاوز عرض الشاشة، فتُمرَّر أفقياً بدل أن تُضغط وتتشابك.
        <div className="w-full overflow-x-auto p-2">
          <div
            className="relative mx-auto"
            style={{ width: chart.width, height: chart.height }}
          >
            <svg
              className="absolute inset-0 overflow-visible"
              width={chart.width}
              height={chart.height}
              aria-hidden="true"
            >
              {chart.lines.map((line, index) => (
                <line
                  key={index}
                  x1={line.x1}
                  y1={line.y1}
                  x2={line.x2}
                  y2={line.y2}
                  stroke="var(--color-line-strong)"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                />
              ))}
            </svg>

            {chart.nodes.map((node) => {
              const Icon = ICONS[node.kind];
              return (
                <div
                  key={node.id}
                  className={`absolute flex items-center gap-2 rounded-lg border px-2.5 shadow-sm ${TONES[node.kind]} ${
                    node.inactive ? "opacity-55" : ""
                  }`}
                  style={{
                    insetInlineStart: "auto",
                    left: node.x,
                    top: node.y,
                    width: NODE_WIDTH,
                    height: NODE_HEIGHT,
                  }}
                  title={node.meta ? `${node.label} — ${node.meta}` : node.label}
                >
                  <span
                    className={`inline-grid h-7 w-7 shrink-0 place-items-center rounded ${
                      node.kind === "root" ? "bg-white/15" : "bg-canvas"
                    }`}
                  >
                    <Icon className="h-3.5 w-3.5" />
                  </span>
                  <span className="min-w-0 leading-tight">
                    <b className="block truncate text-[12px] font-semibold">
                      {node.label}
                    </b>
                    {node.meta && (
                      <small
                        className={`block truncate text-[10px] ${
                          node.kind === "root" ? "text-white/70" : "text-muted"
                        }`}
                      >
                        {node.meta}
                      </small>
                    )}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </section>
  );
}
