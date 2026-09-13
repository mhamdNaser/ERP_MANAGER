import {
  Activity,
  AlertTriangle,
  Bell,
  CalendarClock,
  CalendarDays,
  CheckCircle2,
  ClipboardList,
  FileInput,
  RefreshCw,
  Siren,
  TimerReset,
} from "lucide-react";
import { Empty } from "../../Components/Feedback";
import { ReportTable } from "../../Components/Reports";
import { useLanguage } from "../../Provider/LanguageContext";
const statTones = {
  blue: "border-info-500/20 bg-info-50 text-info-500",
  amber: "border-warn-500/20 bg-warn-50 text-warn-500",
  red: "border-danger-500/20 bg-danger-50 text-danger-500",
  green: "border-ok-500/20 bg-ok-50 text-ok-500",
};
const statusTones = {
  approved: "bg-ok-500",
  returned: "bg-danger-500",
  draft: "bg-line-strong",
};
export function DashboardPage({ data, user, open }) {
  const { t, lang } = useLanguage();
  const ar = lang === "ar";
  const stats = [
    [
      t("totalReports"),
      data.stats.total,
      ClipboardList,
      "blue",
      t("guide_scopeInScope"),
    ],
    [
      t("pendingAction"),
      data.stats.pending,
      Activity,
      "amber",
      t("guide_needsFollowUp"),
    ],
    [
      t("guide_tasksInProgress"),
      data.analytics.tasks.in_progress,
      TimerReset,
      "blue",
      t("guide_scopedTasks"),
    ],
    [
      t("guide_overdue"),
      data.analytics.attention.overdue_tasks,
      AlertTriangle,
      "red",
      t("guide_needsAttention"),
    ],
    [
      t("guide_assignedForms"),
      data.analytics.attention.assigned_forms,
      FileInput,
      "amber",
      t("guide_waitingSubmission"),
    ],
    [
      t("completedApproved"),
      data.stats.completed,
      CheckCircle2,
      "green",
      t("guide_approvedReports"),
    ],
  ];
  const attention = [
    [
      t("guide_unreadNotifications"),
      data.analytics.attention.unread_notifications,
      Bell,
    ],
    [
      t("guide_returnedReports"),
      data.analytics.attention.returned_reports,
      RefreshCw,
    ],
    [t("guide_urgentTasks"), data.analytics.tasks.urgent, Siren],
    [
      t("guide_dueThisWeek"),
      data.analytics.tasks.due_this_week,
      CalendarClock,
    ],
  ];
  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("operationalView")}</p>
          <h1 className="page-title mt-1">
            {t("greeting", { name: user.name.split(" ")[0] })}
          </h1>
          <p className="page-subtitle mt-1">{t("guide_dashSubtitle")}</p>
        </div>
        <span className="inline-flex items-center gap-2 rounded border border-line bg-surface px-3 py-1.5 text-xs text-muted">
          <CalendarDays size={15} />
          {new Intl.DateTimeFormat(ar ? "ar-SY" : "en-GB", {
            dateStyle: "full",
          }).format(new Date())}
        </span>
      </div>
      <div className="stat-grid xl:grid-cols-3">
        {stats.map(([label, value, Icon, color, hint]) => (
          <div className="card flex items-center gap-3 p-4" key={label}>
            <span
              className={`inline-grid h-10 w-10 shrink-0 place-items-center rounded border ${statTones[color]}`}
            >
              <Icon size={18} />
            </span>
            <div className="min-w-0 flex-1">
              <small className="block text-xs text-muted">{label}</small>
              <b className="block text-xl leading-tight font-semibold text-ink">
                {value}
              </b>
              <em className="block text-[11px] not-italic text-muted">
                {hint}
              </em>
            </div>
          </div>
        ))}
      </div>
      <div className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <TrendChart data={data} />
        <TaskChart data={data} />
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {attention.map(([label, value, Icon]) => (
          <article
            className={`card flex items-center gap-3 p-3 ${
              value ? "border-warn-500/30 bg-warn-50" : ""
            }`}
            key={label}
          >
            <span
              className={`inline-grid h-9 w-9 shrink-0 place-items-center rounded border ${
                value
                  ? "border-warn-500/20 bg-warn-50 text-warn-500"
                  : "border-line bg-subtle text-muted"
              }`}
            >
              <Icon size={17} />
            </span>
            <div className="min-w-0">
              <b className="block text-base font-semibold text-ink">{value}</b>
              <small className="block text-xs text-muted">{label}</small>
            </div>
          </article>
        ))}
      </div>
      <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
        <section className="flex min-w-0 flex-col gap-3">
          <div>
            <h2 className="section-title">{t("recentReports")}</h2>
            <p className="page-subtitle mt-0.5">{t("recentReportsHint")}</p>
          </div>
          <ReportTable reports={data.recent_reports} open={open} />
        </section>
        <StatusChart data={data} />
      </div>
      <section className="flex flex-col gap-3">
        <div>
          <h2 className="section-title">{t("notifications")}</h2>
          <p className="page-subtitle mt-0.5">{t("notificationsHint")}</p>
        </div>
        <div
          className={
            data.notifications.length
              ? "grid grid-cols-1 gap-2 lg:grid-cols-2"
              : ""
          }
        >
          {data.notifications.length ? (
            data.notifications.map((notice) => (
              <div
                className="flex items-start gap-3 rounded border border-line bg-surface p-3"
                key={notice.id}
              >
                <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
                  <Bell size={16} />
                </span>
                <div className="min-w-0 flex-1">
                  <b className="block text-[13px] font-semibold text-ink">
                    {notice.title}
                  </b>
                  <p className="mt-0.5 text-xs text-muted">{notice.message}</p>
                </div>
                <small className="shrink-0 text-[11px] text-muted">
                  {new Date(notice.created_at).toLocaleDateString(
                    ar ? "ar-SY" : "en-GB",
                  )}
                </small>
              </div>
            ))
          ) : (
            <Empty text={t("noNotifications")} />
          )}
        </div>
      </section>
    </div>
  );
}
function TrendChart({ data }) {
  const { t } = useLanguage();
  const max = Math.max(
    1,
    ...data.analytics.report_trend.map((item) => item.total),
  );
  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{t("guide_trendTitle")}</h2>
          <p className="page-subtitle mt-0.5 text-xs">{t("guide_trendHint")}</p>
        </div>
        <span className="badge badge-neutral">
          {data.analytics.report_trend.reduce(
            (sum, item) => sum + item.total,
            0,
          )}{" "}
          {t("guide_reportsUnit")}
        </span>
      </header>
      <div className="card-body">
        <div className="flex h-40 items-end justify-around gap-2 border-b border-line">
          {data.analytics.report_trend.map((item) => (
            <article
              key={item.key}
              className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
            >
              <div className="flex w-7 flex-1 items-end justify-center gap-1">
                <i
                  className="w-2 min-h-0.5 rounded-t bg-brand-500"
                  style={{ height: `${Math.max(4, (item.total / max) * 100)}%` }}
                />
                <em
                  className="w-2 min-h-0.5 rounded-t bg-brand-300"
                  style={{
                    height: `${Math.max(2, (item.completed / max) * 100)}%`,
                  }}
                />
              </div>
              <b className="text-[11px] font-semibold text-ink">{item.total}</b>
              <small className="text-[10px] text-muted">{item.label}</small>
            </article>
          ))}
        </div>
        <footer className="mt-3 flex flex-wrap gap-4">
          <span className="flex items-center gap-1.5 text-xs text-muted">
            <i className="h-2 w-2 rounded-[2px] bg-brand-500" />
            {t("guide_created")}
          </span>
          <span className="flex items-center gap-1.5 text-xs text-muted">
            <em className="h-2 w-2 rounded-[2px] bg-brand-300" />
            {t("guide_approved")}
          </span>
        </footer>
      </div>
    </section>
  );
}
function TaskChart({ data }) {
  const { t } = useLanguage();
  const tasks = data.analytics.tasks;
  const completion = tasks.total
    ? Math.round((tasks.completed / tasks.total) * 100)
    : 0;
  const max = Math.max(
    1,
    ...data.analytics.task_trend.flatMap((item) => [
      item.created,
      item.completed,
    ]),
  );
  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{t("guide_taskChartTitle")}</h2>
          <p className="page-subtitle mt-0.5 text-xs">
            {t("guide_taskChartHint")}
          </p>
        </div>
        <span className="badge badge-neutral">
          {completion}% {t("guide_doneUnit")}
        </span>
      </header>
      <div className="card-body">
        <div className="flex h-40 items-end gap-1.5 border-b border-line">
          {data.analytics.task_trend.map((item) => (
            <article
              key={item.key}
              className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
            >
              <div className="flex flex-1 items-end gap-0.5">
                <i
                  className="w-1.5 min-h-0.5 rounded-t bg-brand-500"
                  style={{
                    height: `${Math.max(3, (item.created / max) * 100)}%`,
                  }}
                />
                <em
                  className="w-1.5 min-h-0.5 rounded-t bg-brand-300"
                  style={{
                    height: `${Math.max(3, (item.completed / max) * 100)}%`,
                  }}
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
            {t("guide_taskCreated")}
          </span>
          <span className="flex items-center gap-1.5 text-xs text-muted">
            <em className="h-2 w-2 rounded-[2px] bg-brand-300" />
            {t("guide_completed")}
          </span>
          <b className="ms-auto text-xs font-semibold text-brand-500">
            {t("guide_inProgressCount", { count: tasks.in_progress })}
          </b>
        </footer>
      </div>
    </section>
  );
}
function StatusChart({ data }) {
  const { t } = useLanguage();
  const labels = {
    draft: t("guide_statusDraft"),
    department_review: t("guide_statusDepartment"),
    branch_review: t("guide_statusBranch"),
    general_review: t("guide_statusExecutive"),
    returned: t("guide_statusReturned"),
    approved: t("guide_statusApproved"),
  };
  const max = Math.max(
    1,
    ...data.analytics.status_distribution.map((item) => item.count),
  );
  return (
    <section className="card flex min-w-0 flex-col">
      <div className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{t("guide_statusDistTitle")}</h2>
          <p className="page-subtitle mt-0.5 text-xs">
            {t("guide_statusDistHint")}
          </p>
        </div>
      </div>
      <div className="card-body flex flex-col gap-3">
        {data.analytics.status_distribution.map((item) => (
          <p
            key={item.status}
            className="grid grid-cols-[minmax(90px,1fr)_2fr_28px] items-center gap-2 text-xs text-muted"
          >
            <span className="truncate">{labels[item.status]}</span>
            <i className="block h-1.5 overflow-hidden rounded bg-canvas not-italic">
              <em
                className={`block h-full min-w-0.5 rounded not-italic ${
                  statusTones[item.status] || "bg-warn-500"
                }`}
                style={{ width: `${(item.count / max) * 100}%` }}
              />
            </i>
            <b className="text-end text-xs font-semibold text-ink">
              {item.count}
            </b>
          </p>
        ))}
      </div>
    </section>
  );
}
