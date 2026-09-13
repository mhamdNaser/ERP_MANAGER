import { FileText, KeyRound, Pencil, Search, UserRound } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { ChangePasswordModal } from "../../Components/ChangePasswordModal";
import { EmployeeDetailsModal } from "../../Components/EmployeeDetailsModal";
import { UserFormHistoryDrawer } from "../../Components/UserFormHistoryDrawer";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
export function EmployeesPage({ notify }) {
  const { t } = useLanguage();
  const [employees, setEmployees] = useState([]);
  const [branches, setBranches] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [name, setName] = useState("");
  const [branch, setBranch] = useState("");
  const [department, setDepartment] = useState("");
  const [editing, setEditing] = useState(null);
  const [historyUser, setHistoryUser] = useState(null);
  const [passwordUser, setPasswordUser] = useState(null);
  const load = () => {
    api
      .employees()
      .then((d) => setEmployees(d.employees))
      .catch((e) => notify(e.message, "error"));
    api
      .organization()
      .then((d) => {
        setBranches(d.branches);
        setDepartments(d.departments);
      })
      .catch((e) => notify(e.message, "error"));
  }; // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(load, []);
  const visibleDepartments = departments.filter(
    (item) => !branch || String(item.branch_id) === branch,
  );
  const list = useMemo(
    () =>
      employees.filter(
        (item) =>
          (item.name.toLowerCase().includes(name.toLowerCase()) ||
            (item.email || "").toLowerCase().includes(name.toLowerCase())) &&
          (!branch || String(item.branch_id) === branch) &&
          (!department || String(item.department_id) === department),
      ),
    [employees, name, branch, department],
  );
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("employeeRegistry")}</p>
          <h1 className="page-title">{t("employees")}</h1>
          <p className="page-subtitle">{t("employeesDetailsIntro")}</p>
        </div>
      </div>
      <section className="card overflow-hidden">
        <div className="flex flex-wrap items-center gap-2 border-b border-line p-3">
          <label className="relative flex w-full items-center sm:w-72">
            <Search className="pointer-events-none absolute start-3 h-4 w-4 text-muted" />
            <input
              className="input ps-9"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={t("searchEmployee")}
            />
          </label>
          <select
            className="select w-auto min-w-[150px]"
            value={branch}
            onChange={(e) => {
              setBranch(e.target.value);
              setDepartment("");
            }}
          >
            <option value="">{t("allBranches")}</option>
            {branches.map((item) => (
              <option value={item.id} key={item.id}>
                {item.name}
              </option>
            ))}
          </select>
          <select
            className="select w-auto min-w-[150px]"
            value={department}
            onChange={(e) => setDepartment(e.target.value)}
          >
            <option value="">{t("allDepartments")}</option>
            {visibleDepartments.map((item) => (
              <option value={item.id} key={item.id}>
                {item.name}
              </option>
            ))}
          </select>
          <span className="ms-auto text-xs text-muted">
            {list.length} {t("employees")}
          </span>
        </div>
        <div className="w-full overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>{t("employee")}</th>
                <th>{t("email")}</th>
                <th>{t("jobTitle")}</th>
                <th>{t("department")}</th>
                <th>{t("actions")}</th>
              </tr>
            </thead>
            <tbody>
              {list.map((item) => (
                <tr key={item.id}>
                  <td>
                    <div className="flex items-center gap-2.5">
                      <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded-full border border-line bg-subtle text-brand-500">
                        <UserRound className="h-4 w-4" />
                      </span>
                      <b className="font-semibold text-ink">{item.name}</b>
                    </div>
                  </td>
                  <td className="text-muted" dir="ltr">{item.email}</td>
                  <td className="text-muted">{item.job_title}</td>
                  <td className="text-muted">
                    {item.office?.name ||
                      `${item.branch?.name || ""} · ${item.department?.name || ""}`}
                  </td>
                  <td>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setHistoryUser(item)}
                        className="btn btn-secondary btn-sm"
                      >
                        <FileText className="h-4 w-4" />
                        {t("formHistory")}
                      </button>
                      <button
                        onClick={() => setEditing(item)}
                        className="btn btn-secondary btn-sm"
                      >
                        <Pencil className="h-4 w-4" />
                        {t("editDetails")}
                      </button>
                      <button
                        onClick={() => setPasswordUser(item)}
                        className="btn btn-secondary btn-sm"
                        title={t("changePassword")}
                      >
                        <KeyRound className="h-4 w-4" />
                        {t("changePassword")}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      {editing && (
        <EmployeeDetailsModal
          employee={editing}
          self={false}
          close={() => setEditing(null)}
          done={(updated) => {
            setEmployees((items) =>
              items.map((item) => (item.id === updated.id ? updated : item)),
            );
            setEditing(null);
          }}
          notify={notify}
        />
      )}{" "}
      {passwordUser && (
        <ChangePasswordModal
          employee={passwordUser}
          close={() => setPasswordUser(null)}
          notify={notify}
        />
      )}
      {historyUser && (
        <UserFormHistoryDrawer
          user={historyUser}
          close={() => setHistoryUser(null)}
        />
      )}
    </div>
  );
}
