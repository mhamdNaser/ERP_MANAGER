import { useState } from "react";
import { ArrowDownUp, MessagesSquare, Users } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { formatHours } from "../../Tasks/components/taskUtils";

// الترتيب يجري في المتصفح: الصفوف كلها وصلت مع الطلب، والفرز هو الأداة التي
// يقلب بها رئيس القسم الجدول من "الأكثر إنجازًا" إلى "الأكثر تأخرًا".
function useSortedRows(rows, initialKey) {
  const [sort, setSort] = useState({ key: initialKey, desc: true });
  const sorted = [...rows].sort((a, b) => {
    const left = a[sort.key];
    const right = b[sort.key];
    if (left == null && right == null) return 0;
    if (left == null) return 1;
    if (right == null) return -1;
    if (typeof left === "string") return sort.desc ? right.localeCompare(left, "ar") : left.localeCompare(right, "ar");
    return sort.desc ? right - left : left - right;
  });
  const toggle = (key) =>
    setSort((current) =>
      current.key === key ? { key, desc: !current.desc } : { key, desc: true },
    );
  return { sorted, sort, toggle };
}

function SortableHead({ columnKey, label, sort, toggle, align = "center" }) {
  return (
    <th className={align === "start" ? "text-start" : "text-center"}>
      <button
        type="button"
        className={`inline-flex items-center gap-1 ${sort.key === columnKey ? "text-brand-600" : ""}`}
        onClick={() => toggle(columnKey)}
      >
        {label}
        <ArrowDownUp size={11} className="shrink-0 opacity-60" />
      </button>
    </th>
  );
}

export function EmployeesTable({ employees }) {
  const { t } = useLanguage();
  const { sorted, sort, toggle } = useSortedRows(employees, "approved");

  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title flex items-center gap-2">
            <Users size={15} className="text-muted" />
            {t("stats_employeesTitle")}
          </h2>
          <p className="page-subtitle mt-0.5 text-xs">
            {t("stats_employeesHint")}
          </p>
        </div>
      </header>
      <div className="table-wrap">
        <table className="table">
          <thead>
            <tr>
              <SortableHead
                columnKey="name"
                label={t("stats_colEmployee")}
                sort={sort}
                toggle={toggle}
                align="start"
              />
              <SortableHead columnKey="total" label={t("stats_colTotal")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="approved" label={t("stats_colApproved")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="open" label={t("stats_colOpen")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="overdue" label={t("stats_colOverdue")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="returns" label={t("stats_colReturns")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="avg_execution_hours" label={t("stats_colExecution")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="avg_cycle_hours" label={t("stats_colCycle")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="on_time_rate" label={t("stats_colOnTime")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="completion_rate" label={t("stats_colCompletion")} sort={sort} toggle={toggle} />
            </tr>
          </thead>
          <tbody>
            {sorted.map((row) => (
              <tr key={row.id}>
                <td>
                  <div className="flex min-w-0 flex-col leading-tight">
                    <b className="truncate text-[13px] font-semibold text-ink">
                      {row.name}
                    </b>
                    <small className="truncate text-[11px] text-muted">
                      {row.job_title}
                    </small>
                  </div>
                </td>
                <td className="text-center">{row.total}</td>
                <td className="text-center font-semibold text-ink">
                  {row.approved}
                </td>
                <td className="text-center">{row.open}</td>
                <td className="text-center">
                  {row.overdue > 0 ? (
                    <span className="badge badge-danger">{row.overdue}</span>
                  ) : (
                    <span className="text-muted">0</span>
                  )}
                </td>
                <td className="text-center">
                  {row.returns > 0 ? (
                    <span className="badge badge-warn">{row.returns}</span>
                  ) : (
                    <span className="text-muted">0</span>
                  )}
                </td>
                <td className="text-center whitespace-nowrap">
                  {formatHours(row.avg_execution_hours, t)}
                </td>
                <td className="text-center whitespace-nowrap">
                  {formatHours(row.avg_cycle_hours, t)}
                </td>
                <td className="text-center">
                  {row.on_time_rate == null ? (
                    <span className="text-muted">{t("task_noData")}</span>
                  ) : (
                    `${row.on_time_rate}%`
                  )}
                </td>
                <td>
                  <div className="flex items-center gap-2">
                    <span className="block h-1.5 w-16 rounded bg-canvas">
                      <i
                        className="block h-1.5 rounded bg-brand-500"
                        style={{ width: `${row.completion_rate ?? 0}%` }}
                      />
                    </span>
                    <small className="w-9 shrink-0 text-[11px] text-muted">
                      {row.completion_rate == null
                        ? t("task_noData")
                        : `${row.completion_rate}%`}
                    </small>
                  </div>
                </td>
              </tr>
            ))}
            {!sorted.length && (
              <tr>
                <td colSpan={10} className="text-center text-muted">
                  {t("stats_noEmployees")}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

export function CommunicatorsTable({ communicators }) {
  const { t } = useLanguage();
  const { sorted, sort, toggle } = useSortedRows(communicators, "received");

  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title flex items-center gap-2">
            <MessagesSquare size={15} className="text-muted" />
            {t("stats_communicatorsTitle")}
          </h2>
          <p className="page-subtitle mt-0.5 text-xs">
            {t("stats_communicatorsHint")}
          </p>
        </div>
      </header>
      <div className="table-wrap">
        <table className="table">
          <thead>
            <tr>
              <SortableHead
                columnKey="name"
                label={t("stats_colEmployee")}
                sort={sort}
                toggle={toggle}
                align="start"
              />
              <SortableHead columnKey="received" label={t("stats_colReceived")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="pending" label={t("stats_colPending")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="returned" label={t("stats_colReturned")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="forwarded" label={t("stats_colForwarded")} sort={sort} toggle={toggle} />
              <SortableHead columnKey="avg_response_hours" label={t("stats_colResponse")} sort={sort} toggle={toggle} />
            </tr>
          </thead>
          <tbody>
            {sorted.map((row) => (
              <tr key={row.id}>
                <td>
                  <div className="flex min-w-0 flex-col leading-tight">
                    <b className="truncate text-[13px] font-semibold text-ink">
                      {row.name}
                    </b>
                    <small className="truncate text-[11px] text-muted">
                      {row.job_title}
                    </small>
                  </div>
                </td>
                <td className="text-center font-semibold text-ink">
                  {row.received}
                </td>
                <td className="text-center">
                  {row.pending > 0 ? (
                    <span className="badge badge-warn">{row.pending}</span>
                  ) : (
                    <span className="text-muted">0</span>
                  )}
                </td>
                <td className="text-center">{row.returned}</td>
                <td className="text-center">{row.forwarded}</td>
                <td className="text-center whitespace-nowrap">
                  {formatHours(row.avg_response_hours, t)}
                </td>
              </tr>
            ))}
            {!sorted.length && (
              <tr>
                <td colSpan={6} className="text-center text-muted">
                  {t("stats_noCommunicators")}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}
