import { useEffect, useRef, useState } from "react";
import {
  Activity,
  ArrowLeftRight,
  Building2,
  Check,
  ChevronDown,
  CircleDot,
  Download,
  FileText,
  Filter,
  Layers3,
  Paperclip,
  Pencil,
  Trash2,
  UploadCloud,
  X,
} from "lucide-react";
import { api } from "../../../lib";
import { useConfirm } from "../../../Provider/ConfirmContext";
import { useLanguage } from "../../../Provider/LanguageContext";
import {
  activityTypes,
  columnOf,
  columns,
  fieldLabels,
  priorityLabels,
  statusBadges,
} from "./taskMeta";
import {
  formatBytes,
  formatDateTime,
  formatDuration,
  formatDueDate,
} from "./taskUtils";

export function DepartmentPicker({ departments, value, global, change }) {
  const { t } = useLanguage();
  const [open, setOpen] = useState(false);
  const picker = useRef(null);
  const selected = departments.find((item) => item.id === Number(value));
  useEffect(() => {
    const close = (event) => {
      if (!picker.current?.contains(event.target)) setOpen(false);
    };
    const escape = (event) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("mousedown", close);
    document.addEventListener("keydown", escape);
    return () => {
      document.removeEventListener("mousedown", close);
      document.removeEventListener("keydown", escape);
    };
  }, []);
  const select = (next) => {
    change(next);
    setOpen(false);
  };
  return (
    <div className="relative" ref={picker}>
      <button
        className="flex h-9 w-[260px] max-w-full items-center gap-2 rounded border border-line bg-white px-2.5 text-start hover:bg-canvas"
        onClick={() => setOpen((current) => !current)}
        aria-expanded={open}
      >
        <span className="inline-grid h-6 w-6 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
          {value === "all" ? <Layers3 size={14} /> : <Building2 size={14} />}
        </span>
        <span className="flex min-w-0 flex-1 flex-col leading-tight">
          <small className="text-[10px] text-muted">
            {value === "all" ? t("task_scopeLabel") : t("task_activeDeptLabel")}
          </small>
          <b className="truncate text-xs font-semibold text-ink">
            {value === "all" ? t("task_allDepts") : selected?.name}
          </b>
        </span>
        <ChevronDown size={14} className="shrink-0 text-muted" />
      </button>
      {open && (
        <div className="absolute end-0 top-[calc(100%+6px)] z-50 w-80 max-w-[90vw] rounded border border-line bg-surface">
          <header className="flex items-center gap-2 border-b border-line px-3 py-2.5">
            <span className="inline-grid h-7 w-7 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
              <CircleDot size={14} />
            </span>
            <div className="flex min-w-0 flex-col leading-tight">
              <b className="text-[13px] font-semibold text-ink">
                {t("task_pickerTitle")}
              </b>
              <small className="text-xs text-muted">
                {t("task_pickerSubtitle")}
              </small>
            </div>
          </header>
          <div className="max-h-80 overflow-y-auto p-1">
            {global && (
              <button
                className={`flex w-full items-center gap-2 rounded px-2 py-2 text-start ${
                  value === "all" ? "bg-brand-50 text-brand-600" : "hover:bg-canvas"
                }`}
                onClick={() => select("all")}
              >
                <span className="inline-grid h-7 w-7 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
                  <Layers3 size={14} />
                </span>
                <span className="flex min-w-0 flex-1 flex-col leading-tight">
                  <b className="truncate text-[13px] font-semibold">
                    {t("task_allDepts")}
                  </b>
                  <small className="truncate text-xs text-muted">
                    {t("task_allDeptsHint")}
                  </small>
                </span>
                {value === "all" && (
                  <Check size={15} className="shrink-0 text-brand-500" />
                )}
              </button>
            )}
            {departments.map((item) => (
              <button
                key={item.id}
                className={`flex w-full items-center gap-2 rounded px-2 py-2 text-start ${
                  value === String(item.id)
                    ? "bg-brand-50 text-brand-600"
                    : "hover:bg-canvas"
                }`}
                onClick={() => select(String(item.id))}
              >
                <span className="inline-grid h-7 w-7 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
                  <Building2 size={14} />
                </span>
                <span className="flex min-w-0 flex-1 flex-col leading-tight">
                  <b className="truncate text-[13px] font-semibold">
                    {item.name}
                  </b>
                  <small className="truncate text-xs text-muted">
                    {item.branch?.name || t("task_deptFallback")}
                  </small>
                </span>
                {value === String(item.id) && (
                  <Check size={15} className="shrink-0 text-brand-500" />
                )}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
export function TaskCardActivity({ activities }) {
  const { t } = useLanguage();
  const latest = activities[0];
  const count = activities.length;
  if (!latest)
    return (
      <div className="flex items-center gap-2 rounded border border-line bg-subtle px-2 py-1.5 text-xs text-muted">
        <Activity size={12} className="shrink-0" />
        {t("task_noActivityYet")}
      </div>
    );
  const type = activityTypes[latest.action] || activityTypes.task_updated;
  const Icon = type.icon;
  return (
    <div className="flex items-center gap-2 rounded border border-line bg-subtle px-2 py-1.5">
      <Icon size={12} className={`shrink-0 ${type.accent}`} />
      <span className="flex min-w-0 flex-1 flex-col leading-tight">
        <b className="truncate text-[11px] font-semibold text-ink">
          {t(type.label)}
        </b>
        <small className="truncate text-[11px] text-muted">
          {latest.actor?.name || t("task_deletedUser")} ·{" "}
          {formatDateTime(latest.created_at, t)}
        </small>
      </span>
      {count > 1 && (
        <em className="inline-grid h-5 min-w-5 place-items-center rounded border border-line bg-surface px-1 text-[10px] font-bold text-muted not-italic">
          {count}
        </em>
      )}
    </div>
  );
}
export function TaskDetails({
  task,
  close,
  edit,
  move,
  canDelete,
  canEdit,
  changed,
  notify,
}) {
  const { t } = useLanguage();
  const [files, setFiles] = useState([]);
  const [taskFiles, setTaskFiles] = useState(task.files || []);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(null);
  const confirm = useConfirm();
  const upload = async () => {
    if (!files.length) return;
    const totalSize = files.reduce((sum, file) => sum + file.size, 0);
    setUploading(true);
    setUploadProgress({ loaded: 0, total: totalSize, percent: 0 });
    try {
      await api.uploadDriveFiles(files, {
        scope: "task",
        task_id: task.id,
        department_id: task.department.id,
      }, setUploadProgress);
      notify(t("task_filesAttachedToast"), "success");
      changed();
      close();
    } catch (error) {
      notify(error.message, "error");
    } finally {
      setUploading(false);
      setUploadProgress(null);
    }
  };
  const download = async (file) => {
    try {
      const blob = await api.downloadDriveFile(file.id);
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = file.name;
      link.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const removeFile = async (file) => {
    if (
      !(await confirm({
        message: t("task_confirmDeleteFileMsg", { name: file.name }),
        confirmLabel: t("task_confirmDeleteFileLabel"),
      }))
    )
      return;
    try {
      await api.deleteDriveFile(file.id);
      setTaskFiles((current) => current.filter((item) => item.id !== file.id));
      notify(t("task_fileDeletedToast"), "delete");
      changed();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  return (
    <div className="overlay p-0" onClick={close}>
      <aside className="drawer" onClick={(e) => e.stopPropagation()}>
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="flex min-w-0 flex-col gap-1">
            <span className="eyebrow">{task.label || task.department.name}</span>
            <h2 className="text-base font-semibold text-ink">{task.title}</h2>
            <em
              className={`${statusBadges[task.status] || "badge badge-neutral"} not-italic`}
            >
              {t(columnOf(task.status)?.title)}
            </em>
          </div>
          <button className="btn-icon shrink-0" onClick={close}>
            <X size={16} />
          </button>
        </header>
        <div className="grid grid-cols-2 gap-px border-b border-line bg-line sm:grid-cols-3">
          {[
            [t("task_createdAt"), formatDateTime(task.created_at, t)],
            [t("task_assignee"), task.assignee?.name || t("task_unassigned")],
            [t("task_priority"), t(priorityLabels[task.priority])],
            [t("task_dueDate"), formatDueDate(task.due_date, t)],
            [
              t("task_communicationUser"),
              task.communication_user?.name || t("task_notSet"),
            ],
            [t("task_stageSince"), formatDateTime(task.stage_entered_at, t)],
            [t("task_department"), task.department.name],
          ].map(([label, value]) => (
            <div key={label} className="flex flex-col gap-0.5 bg-subtle px-3 py-2.5">
              <small className="text-[11px] text-muted">{label}</small>
              <b className="truncate text-[13px] font-semibold text-ink">
                {value}
              </b>
            </div>
          ))}
        </div>
        <article className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
          {move && (task.allowed_transitions || []).length > 0 && (
            <section className="flex flex-col gap-2">
              <h3 className="section-title">{t("task_moveSection")}</h3>
              <div className="flex flex-wrap gap-2">
                {(task.allowed_transitions || []).map((status) => {
                  const column = columnOf(status);
                  if (!column) return null;
                  return (
                    <button
                      className="btn btn-secondary btn-sm"
                      key={status}
                      onClick={() => move(task.id, status)}
                    >
                      <column.icon size={14} />
                      {t(column.title)}
                    </button>
                  );
                })}
              </div>
            </section>
          )}
          <section className="flex flex-col gap-2">
            <h3 className="section-title">{t("task_stagePathTitle")}</h3>
            <TaskStageTimeline transitions={task.transitions} />
          </section>
          <section className="flex flex-col gap-2">
            <h3 className="section-title">{t("task_detailsSection")}</h3>
            <p className="text-[13px] leading-relaxed text-muted">
              {task.description || t("task_noDescription")}
            </p>
          </section>
          <section className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
              <h3 className="section-title">{t("task_taskActivityTitle")}</h3>
              <span className="text-xs text-muted">
                {t("task_activityCount", { count: task.activities?.length || 0 })}
              </span>
            </div>
            <div className="flex flex-col gap-2">
              {(task.activities || []).map((activity) => (
                <TaskActivityItem
                  key={activity.id}
                  activity={{
                    ...activity,
                    department: activity.department || task.department,
                    task_title: activity.task_title || task.title,
                  }}
                  compact
                />
              ))}
              {!task.activities?.length && (
                <div className="empty-state">
                  <Activity size={20} />
                  <b>{t("task_noTaskActivity")}</b>
                </div>
              )}
            </div>
          </section>
          <section className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
              <h3 className="section-title">{t("task_filesSection")}</h3>
              <span className="text-xs text-muted">
                {t("task_filesCount", { count: taskFiles.length })}
              </span>
            </div>
            <div className="flex flex-col gap-2">
              {taskFiles.map((file) => (
                <div className="flex items-center gap-2" key={file.id}>
                  <button
                    className="flex min-w-0 flex-1 items-center gap-2 rounded border border-line bg-surface px-3 py-2 text-start hover:bg-canvas"
                    onClick={() => download(file)}
                  >
                    <FileText size={16} className="shrink-0 text-muted" />
                    <span className="flex min-w-0 flex-1 flex-col leading-tight">
                      <b className="truncate text-[13px] font-semibold text-ink">
                        {file.name}
                      </b>
                      <small className="text-[11px] text-muted">
                        {formatBytes(file.size)}
                      </small>
                    </span>
                    <Download size={15} className="shrink-0 text-muted" />
                  </button>
                  <button
                    className="btn-icon shrink-0 hover:bg-danger-50 hover:text-danger-500"
                    title={t("task_deleteFile")}
                    onClick={() => removeFile(file)}
                  >
                    <Trash2 size={15} />
                  </button>
                </div>
              ))}
              {!taskFiles.length && (
                <div className="empty-state">
                  <Paperclip size={20} />
                  <p>{t("task_noFiles")}</p>
                </div>
              )}
            </div>
            <label className="relative flex cursor-pointer items-center gap-2 rounded border border-dashed border-line bg-subtle px-3 py-3">
              <UploadCloud size={18} className="shrink-0 text-muted" />
              <span className="flex min-w-0 flex-col leading-tight">
                <b className="text-[13px] font-semibold text-ink">
                  {t("task_addFiles")}
                </b>
                <small className="text-[11px] text-muted">
                  {t("task_addFilesHint")}
                </small>
              </span>
              <input
                type="file"
                multiple
                disabled={uploading}
                onChange={(e) => setFiles(Array.from(e.target.files || []))}
                className="absolute inset-0 cursor-pointer opacity-0"
              />
            </label>
            {uploadProgress && <TaskUploadProgress progress={uploadProgress} />}
            {files.length > 0 && (
              <button
                className="btn btn-primary"
                disabled={uploading}
                onClick={upload}
              >
                {uploading
                  ? t("task_uploading")
                  : t("task_uploadCount", { count: files.length })}
              </button>
            )}
          </section>
        </article>
        <footer className="flex flex-wrap items-center justify-end gap-2 border-t border-line px-4 py-3">
          {canEdit && (
            <button className="btn btn-secondary me-auto" onClick={edit}>
              <Pencil size={15} />
              {t("task_editTask")}
            </button>
          )}
          {!canEdit && (
            <span className="me-auto text-xs text-muted">
              {t("task_editPermissionNote")}
            </span>
          )}
          {!canDelete && (
            <span className="text-xs text-muted">
              {t("task_deletePermissionNote")}
            </span>
          )}
          <button className="btn btn-primary" onClick={close}>
            {t("close")}
          </button>
        </footer>
      </aside>
    </div>
  );
}
// مسار المهمة عبر المراحل: كل انتقال بمن نفّذه، ووقته، ومدة بقاء المهمة في
// المرحلة التي غادرتها، وملاحظة التواصل إن رافقته.
function TaskStageTimeline({ transitions }) {
  const { t } = useLanguage();
  if (!transitions?.length)
    return (
      <div className="empty-state">
        <ArrowLeftRight size={20} />
        <b>{t("task_noStagePath")}</b>
      </div>
    );
  return (
    <ol className="flex flex-col gap-2">
      {transitions.map((item) => {
        const target = columnOf(item.to_status);
        const source = columnOf(item.from_status);
        const Icon = target?.icon || ArrowLeftRight;
        return (
          <li
            className="flex items-start gap-3 rounded border border-line bg-surface p-2.5"
            key={item.id}
          >
            <span className="inline-grid h-7 w-7 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
              <Icon size={13} />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
              <header className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  {source && (
                    <>
                      <span className="badge badge-neutral">
                        {t(source.title)}
                      </span>
                      <ArrowLeftRight
                        size={12}
                        className="text-muted rtl:rotate-180"
                      />
                    </>
                  )}
                  <span
                    className={
                      statusBadges[item.to_status] || "badge badge-neutral"
                    }
                  >
                    {t(target?.title)}
                  </span>
                </div>
                <time className="text-[11px] whitespace-nowrap text-muted">
                  {formatDateTime(item.created_at, t)}
                </time>
              </header>
              <p className="text-xs leading-relaxed text-muted">
                <strong className="font-semibold text-ink">
                  {item.actor?.name || t("task_deletedUser")}
                </strong>
                {item.seconds_in_previous != null &&
                  ` · ${t("task_stayedFor", {
                    duration: formatDuration(item.seconds_in_previous, t),
                  })}`}
              </p>
              {item.note && (
                <p className="rounded border border-line bg-subtle px-2 py-1.5 text-xs leading-relaxed text-ink">
                  {item.note}
                </p>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
function TaskUploadProgress({ progress }) {
  const { t } = useLanguage();
  const loaded = Math.min(progress.loaded || 0, progress.total || progress.loaded || 0);
  return (
    <div
      className="flex flex-col gap-2 rounded border border-line bg-subtle p-3"
      role="status"
      aria-live="polite"
    >
      <div className="flex items-center justify-between gap-2 text-xs">
        <span className="text-muted">{t("task_uploadingAttachments")}</span>
        <b className="font-semibold text-ink">{progress.percent}%</b>
      </div>
      <div
        className="h-1 w-full rounded bg-canvas"
        aria-label={t("task_uploadProgressAria")}
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow={progress.percent}
        role="progressbar"
      >
        <i
          className="block h-1 rounded bg-brand-500 not-italic"
          style={{ width: `${progress.percent}%` }}
        />
      </div>
      <small className="text-[11px] text-muted">
        {t("task_bytesOf", {
          loaded: formatBytes(loaded),
          total: formatBytes(progress.total || 0),
        })}
      </small>
    </div>
  );
}
export function TaskActivityDrawer({ department, all, close }) {
  const { t } = useLanguage();
  const [data, setData] = useState({ activities: [], actors: [] });
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    actor_id: "",
    action: "",
    from: "",
    to: "",
  });
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setLoading(true);
    api
      .taskActivities({
        ...filters,
        department_id: all ? "" : department,
        all: all ? 1 : "",
      })
      .then(setData)
      .finally(() => setLoading(false));
  }, [department, all, filters]);
  return (
    <div className="overlay p-0" onClick={close}>
      <aside className="drawer" onClick={(e) => e.stopPropagation()}>
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="flex min-w-0 flex-col gap-1">
            <span className="eyebrow">{t("task_auditEyebrow")}</span>
            <h2 className="text-base font-semibold text-ink">{t("task_activityDetailsTitle")}</h2>
            <p className="text-[13px] text-muted">
              {t("task_activityDetailsSubtitle")}
            </p>
          </div>
          <button className="btn-icon shrink-0" onClick={close}>
            <X size={16} />
          </button>
        </header>
        <section className="grid grid-cols-1 gap-2 border-b border-line bg-subtle p-3 sm:grid-cols-2">
          <label className="flex items-center gap-2">
            <Filter size={15} className="shrink-0 text-muted" />
            <select
              className="select"
              value={filters.actor_id}
              onChange={(e) =>
                setFilters({ ...filters, actor_id: e.target.value })
              }
            >
              <option value="">{t("task_allActors")}</option>
              {data.actors.map((actor) => (
                <option key={actor.id} value={actor.id}>
                  {actor.name}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2">
            <select
              className="select"
              value={filters.action}
              onChange={(e) =>
                setFilters({ ...filters, action: e.target.value })
              }
            >
              <option value="">{t("task_allActionTypes")}</option>
              {Object.entries(activityTypes).map(([value, item]) => (
                <option key={value} value={value}>
                  {t(item.label)}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2">
            <span className="text-xs font-semibold text-muted">{t("task_fromLabel")}</span>
            <input
              className="input"
              type="date"
              value={filters.from}
              onChange={(e) => setFilters({ ...filters, from: e.target.value })}
            />
          </label>
          <label className="flex items-center gap-2">
            <span className="text-xs font-semibold text-muted">{t("task_toLabel")}</span>
            <input
              className="input"
              type="date"
              value={filters.to}
              onChange={(e) => setFilters({ ...filters, to: e.target.value })}
            />
          </label>
        </section>
        <div className="flex flex-1 flex-col gap-2 overflow-y-auto p-4">
          {loading ? (
            <div className="empty-state">{t("task_loadingActivities")}</div>
          ) : (
            data.activities.map((activity) => (
              <TaskActivityItem key={activity.id} activity={activity} />
            ))
          )}
          {!loading && !data.activities.length && (
            <div className="empty-state">
              <Activity size={20} />
              <b>{t("task_noMatchingActivities")}</b>
              <span>{t("task_tryChangeFilters")}</span>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}
function TaskActivityItem({ activity, compact = false }) {
  const { t } = useLanguage();
  const type = activityTypes[activity.action] || activityTypes.task_updated;
  const Icon = type.icon;
  return (
    <article
      className={`flex items-start gap-3 rounded border border-line bg-surface ${
        compact ? "p-2.5" : "p-3"
      }`}
    >
      <span
        className={`inline-grid shrink-0 place-items-center rounded border border-line bg-subtle ${
          compact ? "h-7 w-7" : "h-8 w-8"
        } ${type.accent}`}
      >
        <Icon size={compact ? 13 : 15} />
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <header className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex min-w-0 items-center gap-2">
            <b className="text-[13px] font-semibold text-ink">{t(type.label)}</b>
            <em className="truncate text-[11px] text-muted not-italic">
              {activity.department?.name}
            </em>
          </div>
          <time className="text-[11px] whitespace-nowrap text-muted">
            {new Date(activity.created_at).toLocaleString("ar-SY", {
              dateStyle: "medium",
              timeStyle: "short",
            })}
          </time>
        </header>
        <h3 className="truncate text-[13px] font-semibold text-ink">
          {activity.task_title}
        </h3>
        <p className="text-xs leading-relaxed text-muted">
          <strong className="font-semibold text-ink">
            {activity.actor?.name || t("task_deletedUser")}
          </strong>{" "}
          {activity.summary}
        </p>
        <ActivityDetails activity={activity} />
      </div>
    </article>
  );
}
function ActivityDetails({ activity }) {
  const { t } = useLanguage();
  const details = activity.details || {};
  if (activity.action === "task_moved")
    return (
      <div className="mt-1 flex flex-col gap-1.5">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="badge badge-neutral">
            {t(columnOf(details.from_status)?.title)}
          </span>
          <ArrowLeftRight size={13} className="text-muted rtl:rotate-180" />
          <span className="badge badge-neutral">
            {t(columnOf(details.to_status)?.title)}
          </span>
          {details.communication_user && (
            <span className="badge badge-warn">
              {details.communication_user}
            </span>
          )}
        </div>
        {details.note && (
          <p className="rounded border border-line bg-subtle px-2 py-1.5 text-xs leading-relaxed text-ink">
            {details.note}
          </p>
        )}
      </div>
    );
  if (["file_attached", "file_deleted"].includes(activity.action))
    return (
      <small className="badge badge-neutral mt-1 self-start">
        <FileText size={12} />
        {details.file_name} · {formatBytes(details.file_size || 0)}
      </small>
    );
  if (activity.action === "task_updated" && details.changes)
    return (
      <div className="mt-1 flex flex-wrap items-center gap-1.5">
        {Object.keys(details.changes).map((field) => (
          <span key={field} className="badge badge-neutral">
            {fieldLabels[field] ? t(fieldLabels[field]) : field}
          </span>
        ))}
      </div>
    );
  return null;
}
export function TaskEditor({ task, departmentId, members, close, saved, notify }) {
  const { t } = useLanguage();
  const [saving, setSaving] = useState(false);
  const [data, setData] = useState({
    title: task?.title || "",
    description: task?.description || "",
    assignee_id: task?.assignee?.id ? String(task.assignee.id) : "",
    priority: task?.priority || "medium",
    label: task?.label || "",
    due_date: task?.due_date || "",
    status: task?.status || "archived",
  });
  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload = {
        ...data,
        department_id: departmentId,
        assignee_id: data.assignee_id ? Number(data.assignee_id) : null,
      };
      if (task) await api.updateTask(task.id, payload);
      else await api.createTask(payload);
      notify(task ? t("task_updatedToast") : t("task_createdToast"), "success");
      saved();
    } catch (error) {
      notify(error.message, "error");
    } finally {
      setSaving(false);
    }
  };
  return (
    <div className="overlay overflow-y-auto" onClick={close}>
      <section className="modal" onClick={(e) => e.stopPropagation()}>
        <header className="modal-head items-start">
          <div className="flex min-w-0 flex-col gap-1">
            <small className="eyebrow">
              {task ? t("task_editEyebrow") : t("task_createEyebrow")}
            </small>
            <h2 className="text-base font-semibold text-ink">
              {task ? t("task_editTask") : t("task_createTask")}
            </h2>
            <p className="text-[13px] text-muted">
              {t("task_editorSubtitle")}
            </p>
          </div>
          <button className="btn-icon shrink-0" onClick={close}>
            <X size={16} />
          </button>
        </header>
        <form
          onSubmit={submit}
          className="grid flex-1 grid-cols-1 gap-4 overflow-y-auto p-4 sm:grid-cols-2"
        >
          <label className="field mb-0 sm:col-span-2">
            <span className="label">{t("task_titleLabel")}</span>
            <input
              className="input"
              autoFocus
              required
              value={data.title}
              onChange={(e) => setData({ ...data, title: e.target.value })}
              placeholder={t("task_titlePlaceholder")}
            />
          </label>
          <label className="field mb-0 sm:col-span-2">
            <span className="label">{t("task_description")}</span>
            <textarea
              className="textarea"
              value={data.description}
              onChange={(e) =>
                setData({ ...data, description: e.target.value })
              }
              placeholder={t("task_descriptionPlaceholder")}
            />
          </label>
          <label className="field mb-0">
            <span className="label">{t("task_assignee")}</span>
            <select
              className="select"
              value={data.assignee_id}
              onChange={(e) =>
                setData({ ...data, assignee_id: e.target.value })
              }
            >
              <option value="">{t("task_unassignedOption")}</option>
              {members.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.name} · {member.job_title}
                </option>
              ))}
            </select>
          </label>
          <label className="field mb-0">
            <span className="label">{t("task_priority")}</span>
            <select
              className="select"
              value={data.priority}
              onChange={(e) => setData({ ...data, priority: e.target.value })}
            >
              {Object.entries(priorityLabels).map(([value, label]) => (
                <option key={value} value={value}>
                  {t(label)}
                </option>
              ))}
            </select>
          </label>
          <label className="field mb-0">
            <span className="label">{t("task_label")}</span>
            <input
              className="input"
              value={data.label}
              onChange={(e) => setData({ ...data, label: e.target.value })}
              placeholder={t("task_labelPlaceholder")}
            />
          </label>
          <label className="field mb-0">
            <span className="label">{t("task_dueDate")}</span>
            <input
              className="input"
              type="date"
              value={data.due_date}
              onChange={(e) => setData({ ...data, due_date: e.target.value })}
            />
          </label>
          {/* بعد الإنشاء تتحرك المهمة على المسار عبر النقل فقط، فلا يظهر
              اختيار المرحلة إلا عند إنشائها. */}
          {!task && (
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("task_startStage")}</span>
              <select
                className="select"
                value={data.status}
                onChange={(e) => setData({ ...data, status: e.target.value })}
              >
                {columns
                  .filter((column) => ["archived", "planned"].includes(column.id))
                  .map((column) => (
                    <option key={column.id} value={column.id}>
                      {t(column.title)}
                    </option>
                  ))}
              </select>
              <small className="mt-1 text-[11px] text-muted">
                {t("task_startStageHint")}
              </small>
            </label>
          )}
          <footer className="flex items-center justify-end gap-2 border-t border-line pt-3 sm:col-span-2">
            <button type="button" className="btn btn-secondary" onClick={close}>
              {t("cancel")}
            </button>
            <button disabled={saving} className="btn btn-primary">
              {saving
                ? t("task_saving")
                : task
                  ? t("task_saveEdits")
                  : t("task_createTaskBtn")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
