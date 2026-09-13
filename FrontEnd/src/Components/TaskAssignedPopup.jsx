import { CalendarDays, KanbanSquare, X } from "lucide-react";
import { useEffect, useRef } from "react";
import { sound } from "../lib";
import { useLanguage } from "../Provider/LanguageContext";
import {
  priorityBadges,
  priorityLabels,
} from "../Page/Tasks/components/taskMeta";
export function TaskAssignedPopup({ event, onView, onClose }) {
  const { t } = useLanguage();
  const played = useRef(false);
  const task = event.task;
  useEffect(() => {
    if (!played.current) {
      sound("notice");
      played.current = true;
    }
  }, []);
  return (
    <div className="fixed end-4 top-[calc(var(--spacing-topbar)+0.75rem)] z-[60] w-[min(340px,calc(100vw-2rem))] rounded border border-line bg-surface shadow-lg">
      <div className="flex items-start gap-2.5 border-b border-line p-3">
        <span className="mt-0.5 inline-grid h-8 w-8 shrink-0 place-items-center rounded bg-brand-50 text-brand-500">
          <KanbanSquare size={16} />
        </span>
        <div className="min-w-0 flex-1">
          <b className="block text-[13px] font-semibold text-ink">
            {t("task_assignedHeading")}
          </b>
          <p className="mt-0.5 line-clamp-2 text-xs text-muted">
            {task?.title}
          </p>
        </div>
        <button
          onClick={onClose}
          aria-label={t("close")}
          className="shrink-0 text-muted hover:text-ink"
        >
          <X size={15} />
        </button>
      </div>
      <div className="flex flex-wrap items-center gap-1.5 px-3 py-2.5">
        {task?.department?.name && (
          <span className="badge badge-neutral">{task.department.name}</span>
        )}
        {task?.priority && (
          <span className={priorityBadges[task.priority] || "badge badge-neutral"}>
            {t(priorityLabels[task.priority])}
          </span>
        )}
        {task?.due_date && (
          <span className="badge badge-neutral">
            <CalendarDays size={12} />
            {new Date(task.due_date).toLocaleDateString("ar-SY", {
              day: "numeric",
              month: "short",
            })}
          </span>
        )}
      </div>
      <div className="p-3 pt-0">
        <button className="btn btn-primary w-full" onClick={onView}>
          {t("task_assignedViewCta")}
        </button>
      </div>
    </div>
  );
}
