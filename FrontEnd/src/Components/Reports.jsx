import { ChevronLeft } from "lucide-react";
import { useLanguage } from "../Provider/LanguageContext";
import { Empty } from "./Feedback";
const statusKeys = {
  draft: "draft",
  department_review: "departmentReview",
  branch_review: "branchReview",
  general_review: "generalReview",
  returned: "returned",
  approved: "approved",
};
const typeKeys = {
  daily_report: "dailyReport",
  daily_plan: "dailyPlan",
  weekly_report: "weeklyReport",
  weekly_plan: "weeklyPlan",
};
const statusTones = {
  draft: "badge-neutral",
  department_review: "badge-warn",
  branch_review: "badge-warn",
  general_review: "badge-warn",
  returned: "badge-danger",
  approved: "badge-ok",
};
const statusDots = {
  draft: "bg-muted",
  department_review: "bg-warn-500",
  branch_review: "bg-warn-500",
  general_review: "bg-warn-500",
  returned: "bg-danger-500",
  approved: "bg-ok-500",
};
export function StatusBadge({ status }) {
  const { t } = useLanguage();
  const key = statusKeys[status];
  return (
    <span className={`badge ${statusTones[status] || "badge-neutral"}`}>
      <i
        className={`h-1.5 w-1.5 shrink-0 rounded-full not-italic ${statusDots[status] || "bg-muted"}`}
      />
      {key ? t(key) : status}
    </span>
  );
}
// eslint-disable-next-line react-refresh/only-export-components
export function reportType(type, t) {
  const key = typeKeys[type];
  return key ? t(key) : type;
}
export function ReportTable({ reports, open }) {
  const { t } = useLanguage();
  return reports.length ? (
    <div className="table-wrap">
      <table className="table min-w-[760px]">
        <thead>
          <tr>
            <th>{t("report")}</th>
            <th>{t("employee")}</th>
            <th>{t("entity")}</th>
            <th>{t("period")}</th>
            <th>{t("status")}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {reports.map((r) => (
            <tr key={r.id} onClick={() => open(r)} className="cursor-pointer">
              <td>
                <b className="block font-semibold">{r.title}</b>
                <small className="block text-xs text-muted">
                  {reportType(r.type, t)}
                </small>
              </td>
              <td>
                {r.employee?.name}
                <small className="block text-xs text-muted">
                  {r.employee?.job_title}
                </small>
              </td>
              <td>
                {r.department?.name}
                <small className="block text-xs text-muted">
                  {r.branch?.name}
                </small>
              </td>
              <td className="whitespace-nowrap">{r.period_start}</td>
              <td>
                <StatusBadge status={r.status} />
              </td>
              <td className="w-8">
                <ChevronLeft size={18} className="text-muted rtl:rotate-180" />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  ) : (
    <Empty text={t("noReports")} />
  );
}
