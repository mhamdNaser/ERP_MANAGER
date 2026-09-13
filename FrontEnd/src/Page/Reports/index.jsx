import { FileDown, Search } from "lucide-react";
import { useMemo, useState } from "react";
import { ReportTable } from "../../Components/Reports";
import { useLanguage } from "../../Provider/LanguageContext";
import { exportReports } from "../../lib";
export function ReportsPage({ reports, open, user }) {
  const { t } = useLanguage();
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("all");
  const [employee, setEmployee] = useState("all");
  const [department, setDepartment] = useState("all");
  const [branch, setBranch] = useState("all");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const employeeOptions = useMemo(
    () => uniqueOptions(reports, (report) => report.employee),
    [reports],
  );
  const departmentOptions = useMemo(
    () => uniqueOptions(reports, (report) => report.department),
    [reports],
  );
  const branchOptions = useMemo(
    () => uniqueOptions(reports, (report) => report.branch),
    [reports],
  );
  const canFilterBranch = ["general_manager", "database_manager"].includes(
    user.role,
  );
  const canFilterDepartment = [
    "branch_manager",
    "general_manager",
    "database_manager",
  ].includes(user.role);
  const canFilterEmployee = [
    "department_head",
    "branch_manager",
    "general_manager",
    "database_manager",
  ].includes(user.role);
  const list = useMemo(
    () =>
      reports.filter(
        (r) => {
          const period = r.period_start || "";
          return (
            (filter === "all" || r.status === filter) &&
            (employee === "all" || String(r.employee?.id) === employee) &&
            (department === "all" || String(r.department?.id) === department) &&
            (branch === "all" || String(r.branch?.id) === branch) &&
            (!dateFrom || period >= dateFrom) &&
            (!dateTo || period <= dateTo) &&
            `${r.title} ${r.employee?.name || ""}`
              .toLowerCase()
              .includes(query.toLowerCase())
          );
        },
      ),
    [reports, query, filter, employee, department, branch, dateFrom, dateTo],
  );
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("reportRegistry")}</p>
          <h1 className="page-title">{t("reportsTitle")}</h1>
          <p className="page-subtitle">{t("reportsIntro")}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => exportReports(list, "word")}
          >
            <FileDown size={15} />
            Word
          </button>
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => exportReports(list, "pdf")}
          >
            <FileDown size={15} />
            PDF
          </button>
        </div>
      </div>
      <section className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2 rounded border border-line bg-surface p-3">
          <label className="relative flex min-w-56 flex-1 items-center sm:max-w-xs">
            <Search
              size={16}
              className="pointer-events-none absolute start-3 text-muted"
            />
            <input
              className="input ps-9"
              placeholder={t("searchReports")}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </label>
          <select
            className="select w-auto min-w-40"
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
          >
            <option value="all">{t("allStatuses")}</option>
            <option value="draft">{t("draft")}</option>
            <option value="department_review">{t("departmentReview")}</option>
            <option value="branch_review">{t("branchReview")}</option>
            <option value="general_review">{t("generalReview")}</option>
            <option value="returned">{t("returned")}</option>
            <option value="approved">{t("approved")}</option>
          </select>
          {canFilterEmployee && (
            <select
              className="select w-auto min-w-40"
              value={employee}
              onChange={(e) => setEmployee(e.target.value)}
            >
              <option value="all">{t("allEmployees")}</option>
              {employeeOptions.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          )}
          {canFilterDepartment && (
            <select
              className="select w-auto min-w-40"
              value={department}
              onChange={(e) => setDepartment(e.target.value)}
            >
              <option value="all">{t("allDepartments")}</option>
              {departmentOptions.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          )}
          {canFilterBranch && (
            <select
              className="select w-auto min-w-40"
              value={branch}
              onChange={(e) => setBranch(e.target.value)}
            >
              <option value="all">{t("allBranches")}</option>
              {branchOptions.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          )}
          <input
            className="input w-auto min-w-36"
            type="date"
            aria-label={t("fromDate")}
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
          />
          <input
            className="input w-auto min-w-36"
            type="date"
            aria-label={t("toDate")}
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
          />
          <span className="ms-auto text-xs text-muted">
            {list.length} {t("results")}
          </span>
        </div>
        <ReportTable reports={list} open={open} />
      </section>
    </div>
  );
}

function uniqueOptions(items, select) {
  const map = new Map();
  items.forEach((item) => {
    const value = select(item);
    if (value?.id) map.set(String(value.id), value);
  });
  return [...map.values()].sort((a, b) => a.name.localeCompare(b.name, "ar"));
}
