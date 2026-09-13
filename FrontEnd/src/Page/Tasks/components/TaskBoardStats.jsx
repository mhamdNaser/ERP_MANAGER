import { useLanguage } from "../../../Provider/LanguageContext";
// Counts describe the tasks currently on the board, so they follow the active
// search and filters instead of contradicting the columns underneath them.
export function TaskBoardStats({ tasks, completion }) {
  const { t } = useLanguage();
  return (
    <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <div className="flex flex-col gap-0.5 rounded border border-line bg-surface p-3">
        <b className="text-xl font-semibold text-ink">{tasks.length}</b>
        <span className="text-xs text-muted">{t("task_statTotal")}</span>
      </div>
      <div className="flex flex-col gap-0.5 rounded border border-line bg-surface p-3">
        <b className="text-xl font-semibold text-ink">
          {tasks.filter((task) => task.status === "in_progress").length}
        </b>
        <span className="text-xs text-muted">{t("task_statInProgress")}</span>
      </div>
      <div className="flex flex-col gap-0.5 rounded border border-line bg-surface p-3">
        <b className="text-xl font-semibold text-ink">
          {tasks.filter((task) => task.priority === "urgent").length}
        </b>
        <span className="text-xs text-muted">{t("task_statUrgent")}</span>
      </div>
      <div className="flex flex-col gap-2 rounded border border-line bg-surface p-3">
        <span className="flex items-baseline gap-2">
          <b className="text-xl font-semibold text-ink">{completion}%</b>
          <small className="text-xs text-muted">{t("task_statCompletion")}</small>
        </span>
        <span className="block h-1 w-full rounded bg-canvas">
          <span
            className="block h-1 rounded bg-brand-500"
            style={{ width: `${completion}%` }}
          />
        </span>
      </div>
    </section>
  );
}
