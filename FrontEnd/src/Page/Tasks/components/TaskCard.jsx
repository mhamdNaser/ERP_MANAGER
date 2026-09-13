import {
  CalendarDays,
  Eye,
  MessagesSquare,
  Paperclip,
  Timer,
  Trash2,
  UserRound,
} from "lucide-react";
import { TaskCardActivity } from "./TaskComponents";
import { TaskStatusMenu } from "./TaskStatusMenu";
import { priorityBadges, priorityLabels } from "./taskMeta";
import { elapsedSince, formatDuration, isOverdue } from "./taskUtils";

export function TaskCard({
  task,
  t,
  dragging,
  setDragging,
  move,
  remove,
  canDeleteTasks,
  onSelect,
}) {
  const canMove = task.can_move ?? false;
  // زمن بقاء المهمة في مرحلتها الحالية — المؤشر الأول على تعثّرها.
  const stageAge = ["completed", "cancelled"].includes(task.status)
    ? null
    : elapsedSince(task.stage_entered_at);
  return (
    <article
      draggable={canMove}
      className={`flex flex-col gap-2 rounded border border-line bg-surface p-3 ${
        dragging === task.id ? "opacity-50" : ""
      } ${canMove ? "cursor-grab" : ""}`}
      title={canMove ? undefined : t("task_moveRestricted")}
      onDragStart={() => canMove && setDragging(task.id)}
      onDragEnd={() => setDragging(null)}
    >
      <div className="flex items-center justify-between gap-2">
        <span className="badge badge-neutral max-w-[70%] truncate">
          {task.label || task.department.name}
        </span>
        <div className="ms-auto flex items-center gap-0.5">
          {canMove && <TaskStatusMenu task={task} move={move} />}
          {(task.can_delete ?? canDeleteTasks) && (
            <button
              className="inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-danger-50 hover:text-danger-500"
              onClick={(e) => {
                e.stopPropagation();
                remove(task);
              }}
              title={t("delete")}
            >
              <Trash2 size={14} />
            </button>
          )}
        </div>
      </div>
      <h3 className="text-[13px] leading-relaxed font-semibold text-ink">
        {task.title}
      </h3>
      <div className="flex flex-wrap items-center gap-1.5">
        {task.due_date && (
          <span
            className={
              isOverdue(task) ? "badge badge-danger" : "badge badge-neutral"
            }
          >
            <CalendarDays size={12} />
            {new Date(task.due_date).toLocaleDateString("ar-SY", {
              day: "numeric",
              month: "short",
            })}
          </span>
        )}
        <span className={priorityBadges[task.priority] || "badge badge-neutral"}>
          {t(priorityLabels[task.priority])}
        </span>
        {task.files?.length ? (
          <span className="badge badge-neutral">
            <Paperclip size={12} />
            {task.files.length}
          </span>
        ) : null}
        {stageAge != null && (
          <span className="badge badge-neutral" title={t("task_stageAgeHint")}>
            <Timer size={12} />
            {formatDuration(stageAge, t)}
          </span>
        )}
      </div>
      {task.status === "communication" && task.communication_user && (
        <div className="flex items-center gap-2 rounded border border-warn-500/30 bg-warn-50 px-2 py-1.5">
          <MessagesSquare size={12} className="shrink-0 text-warn-500" />
          <span className="flex min-w-0 flex-col leading-tight">
            <small className="text-[10px] text-muted">
              {t("task_communicationUser")}
            </small>
            <b className="truncate text-[11px] font-semibold text-ink">
              {task.communication_user.name}
            </b>
          </span>
        </div>
      )}
      <TaskCardActivity activities={task.activities || []} />
      <footer className="flex items-center gap-2 border-t border-line pt-2.5">
        <span className="inline-grid h-7 w-7 shrink-0 place-items-center rounded bg-brand-800 text-[11px] font-bold text-white">
          {task.assignee?.name?.charAt(0) || <UserRound size={14} />}
        </span>
        <div className="flex min-w-0 flex-1 flex-col leading-tight">
          <small className="text-[11px] text-muted">{t("task_assignee")}</small>
          <b className="truncate text-xs font-semibold text-ink">
            {task.assignee?.name || t("task_unassigned")}
          </b>
        </div>
        <button className="btn btn-secondary btn-sm" onClick={() => onSelect(task)}>
          <Eye size={14} />
          {t("task_details")}
        </button>
      </footer>
    </article>
  );
}
