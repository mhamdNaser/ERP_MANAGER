import { useLanguage } from "../../../Provider/LanguageContext";
import { columnOf } from "../../Tasks/components/taskMeta";
import { formatHours } from "../../Tasks/components/taskUtils";

// متوسط زمن البقاء في كل مرحلة. المقدار هنا حجم لا هوية، فيأخذ لونًا واحدًا
// بتدرّج واحد، وكل عمود يحمل قيمته مكتوبة فلا يُقرأ الطول وحده.
export function StageDurations({ stages }) {
  const { t } = useLanguage();
  const max = Math.max(1, ...stages.map((stage) => stage.avg_hours || 0));

  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{t("stats_stageTitle")}</h2>
          <p className="page-subtitle mt-0.5 text-xs">{t("stats_stageHint")}</p>
        </div>
      </header>
      <div className="card-body flex flex-col gap-2.5">
        {stages.map((stage) => {
          const column = columnOf(stage.status);
          return (
            <article className="flex flex-col gap-1" key={stage.status}>
              <header className="flex items-center justify-between gap-2 text-xs">
                <span className="flex min-w-0 items-center gap-1.5 text-ink">
                  {column && <column.icon size={13} className="shrink-0 text-muted" />}
                  <b className="truncate font-semibold">{t(column?.title)}</b>
                </span>
                <span className="flex shrink-0 items-center gap-2">
                  <b className="font-semibold text-ink">
                    {formatHours(stage.avg_hours, t)}
                  </b>
                  <small className="text-muted">
                    {t("stats_stageCount", { count: stage.count })}
                  </small>
                </span>
              </header>
              <span className="block h-1.5 w-full rounded bg-canvas">
                <i
                  className="block h-1.5 rounded bg-brand-500"
                  style={{
                    width: `${Math.max(2, ((stage.avg_hours || 0) / max) * 100)}%`,
                  }}
                />
              </span>
            </article>
          );
        })}
        {!stages.length && (
          <p className="empty-state">{t("stats_noStageData")}</p>
        )}
      </div>
    </section>
  );
}

// الوارد مقابل المعتمد أسبوعيًا. سلسلتان بلون واحد ودرجتَي إضاءة — نفس نمط
// مخطط الداشبورد — مع دليل ألوان وقيم في التلميح، فلا تقوم الهوية على اللون وحده.
export function WeeklyFlow({ trend, summary }) {
  const { t } = useLanguage();
  const max = Math.max(
    1,
    ...trend.flatMap((item) => [item.created, item.approved]),
  );

  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{t("stats_flowTitle")}</h2>
          <p className="page-subtitle mt-0.5 text-xs">{t("stats_flowHint")}</p>
        </div>
        <span className="badge badge-neutral">
          {t("stats_returnsBadge", { count: summary?.returns ?? 0 })}
        </span>
      </header>
      <div className="card-body">
        <div className="flex h-40 items-end gap-1.5 border-b border-line">
          {trend.map((item) => (
            <article
              className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
              key={item.key}
            >
              <div className="flex flex-1 items-end gap-0.5">
                <i
                  className="min-h-0.5 w-1.5 rounded-t bg-brand-500"
                  title={t("stats_createdTooltip", {
                    count: item.created,
                    week: item.label,
                  })}
                  style={{ height: `${Math.max(3, (item.created / max) * 100)}%` }}
                />
                <em
                  className="min-h-0.5 w-1.5 rounded-t bg-brand-300"
                  title={t("stats_approvedTooltip", {
                    count: item.approved,
                    week: item.label,
                  })}
                  style={{ height: `${Math.max(3, (item.approved / max) * 100)}%` }}
                />
              </div>
              <small className="text-[10px] text-muted [direction:ltr]">
                {item.label}
              </small>
            </article>
          ))}
        </div>
        <footer className="mt-3 flex flex-wrap items-center gap-3">
          <span className="flex items-center gap-1.5 text-xs text-muted">
            <i className="h-2 w-2 rounded-[2px] bg-brand-500" />
            {t("stats_created")}
          </span>
          <span className="flex items-center gap-1.5 text-xs text-muted">
            <em className="h-2 w-2 rounded-[2px] bg-brand-300" />
            {t("stats_approvedLegend")}
          </span>
          <b className="ms-auto text-xs font-semibold text-brand-500">
            {t("stats_avgExecution", {
              duration: formatHours(summary?.avg_execution_hours, t),
            })}
          </b>
        </footer>
      </div>
    </section>
  );
}
