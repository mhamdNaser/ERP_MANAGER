import { useEffect, useMemo, useState } from "react";
import { BadgeCheck, ChartColumn, Clock3, Gauge, Timer, TriangleAlert } from "lucide-react";
import { api } from "../../lib";
import { useLanguage } from "../../Provider/LanguageContext";
import { DepartmentPicker } from "../Tasks/components/TaskComponents";
import { formatHours } from "../Tasks/components/taskUtils";
import { StageDurations, WeeklyFlow } from "./components/TaskStatsCharts";
import {
  CommunicatorsTable,
  EmployeesTable,
} from "./components/TaskStatsTables";

// لوحة إحصائيات المهام: أرقام الإنجاز وسرعته لكل موظف داخل نطاق المستخدم.
// الوصول إليها مضبوط بصلاحية tasks.statistics.view — الشريط الجانبي يخفي
// التبويب، والخادم يرفض الطلب، فلا يكفي أحدهما وحده.
export function TaskStatsPage({ user, notify }) {
  const { t } = useLanguage();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [department, setDepartment] = useState("");
  const [range, setRange] = useState({ from: "", to: "" });
  const isGlobal = ["general_manager", "database_manager"].includes(user.role);

  const load = (selection = department, nextRange = range) => {
    const all = selection === "all";
    setLoading(true);
    api
      .taskStatistics({
        department_id: all ? "" : Number(selection) || "",
        all: all ? 1 : "",
        from: nextRange.from,
        to: nextRange.to,
      })
      .then((result) => {
        setData(result);
        if (!selection)
          setDepartment(String(result.active_department_id || "all"));
      })
      .catch((error) => notify(error.message, "error"))
      .finally(() => setLoading(false));
  };
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load("");
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const activeDepartment = data?.departments.find(
    (item) => item.id === Number(department),
  );
  const summary = data?.summary;
  const tiles = useMemo(
    () =>
      summary
        ? [
            {
              key: "total",
              icon: ChartColumn,
              value: summary.total,
              label: t("stats_total"),
            },
            {
              key: "approved",
              icon: BadgeCheck,
              value: summary.approved,
              label: t("stats_approved"),
            },
            {
              key: "open",
              icon: Clock3,
              value: summary.open,
              label: t("stats_open"),
            },
            {
              key: "overdue",
              icon: TriangleAlert,
              value: summary.overdue,
              label: t("stats_overdue"),
              danger: summary.overdue > 0,
            },
            {
              key: "cycle",
              icon: Timer,
              value: formatHours(summary.avg_cycle_hours, t),
              label: t("stats_avgCycle"),
            },
            {
              key: "onTime",
              icon: Gauge,
              value:
                summary.on_time_rate == null
                  ? t("task_noData")
                  : `${summary.on_time_rate}%`,
              label: t("stats_onTimeRate"),
            },
          ]
        : [],
    [summary, t],
  );

  const changeRange = (next) => {
    setRange(next);
    load(department, next);
  };

  if (!data)
    return (
      <div className="page">
        <div className="empty-state">{t("stats_loading")}</div>
      </div>
    );

  return (
    <div className="page">
      <header className="page-header">
        <div className="flex min-w-0 items-start gap-3">
          <span className="inline-grid h-10 w-10 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
            <ChartColumn size={18} />
          </span>
          <div className="min-w-0">
            <p className="eyebrow">{t("stats_eyebrow")}</p>
            <h1 className="page-title">{t("navTaskStats")}</h1>
            <small className="page-subtitle">
              {department === "all"
                ? t("stats_allScope")
                : activeDepartment?.name || user.department?.name || ""}
            </small>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {(data.departments.length > 1 || isGlobal) && (
            <DepartmentPicker
              departments={data.departments}
              value={department}
              global={isGlobal}
              change={(value) => {
                setDepartment(value);
                load(value);
              }}
            />
          )}
          <label className="flex items-center gap-1.5">
            <span className="text-xs font-semibold text-muted">
              {t("task_fromLabel")}
            </span>
            <input
              className="input w-36"
              type="date"
              value={range.from}
              onChange={(event) =>
                changeRange({ ...range, from: event.target.value })
              }
            />
          </label>
          <label className="flex items-center gap-1.5">
            <span className="text-xs font-semibold text-muted">
              {t("task_toLabel")}
            </span>
            <input
              className="input w-36"
              type="date"
              value={range.to}
              onChange={(event) =>
                changeRange({ ...range, to: event.target.value })
              }
            />
          </label>
        </div>
      </header>

      <section className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        {tiles.map((tile) => (
          <article
            className="flex flex-col gap-1 rounded border border-line bg-surface p-3"
            key={tile.key}
          >
            <span className="flex items-center gap-1.5 text-muted">
              <tile.icon size={14} className="shrink-0" />
              <small className="truncate text-[11px]">{tile.label}</small>
            </span>
            <b
              className={`text-xl font-semibold ${tile.danger ? "text-danger-500" : "text-ink"}`}
            >
              {tile.value}
            </b>
          </article>
        ))}
      </section>

      <section className="grid grid-cols-1 items-start gap-3 xl:grid-cols-2">
        <StageDurations stages={data.stages} />
        <WeeklyFlow trend={data.trend} summary={summary} />
      </section>

      <EmployeesTable employees={data.employees} />
      <CommunicatorsTable communicators={data.communicators} />
      {loading && (
        <p className="text-center text-xs text-muted">{t("stats_loading")}</p>
      )}
    </div>
  );
}
