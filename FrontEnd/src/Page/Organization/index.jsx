import { Building2, Pencil, Trash2, Users } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { ReusableFormModal } from "../../Components/ReusableFormModal";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { useConfirm } from "../../Provider/ConfirmContext";
import { getEditorConfig } from "./editorConfig";
import { UnitSection } from "./components/UnitSection";
export function OrganizationPage({ user, notify }) {
  const { t } = useLanguage();
  const [branches, setBranches] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [offices, setOffices] = useState([]);
  const [editor, setEditor] = useState(null);
  const ref = useRef(notify);
  const can = (permission) => user.permissions?.includes(permission);
  const confirm = useConfirm();
  const load = () => {
    api
      .organization()
      .then((data) => {
        setBranches(data.branches);
        setDepartments(data.departments);
      })
      .catch((error) => ref.current(error.message, "error"));
    api
      .employees()
      .then((data) => setEmployees(data.employees))
      .catch((error) => ref.current(error.message, "error"));
    if (can("offices.manage"))
      api
        .offices()
        .then(setOffices)
        .catch((error) => ref.current(error.message, "error"));
  };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(load, []);
  const remove = async (kind, id) => {
    const label = t(
      kind === "branch"
        ? "ui_orgLabelBranch"
        : kind === "department"
          ? "ui_orgLabelDepartment"
          : "ui_orgLabelEmployee",
    );
    if (
      !(await confirm({
        message: t("ui_orgDeleteMessage", { label }),
        confirmLabel: t("ui_orgDeleteConfirm", { label }),
      }))
    )
      return;
    try {
      if (kind === "branch") await api.deleteBranch(id);
      else if (kind === "department") await api.deleteDepartment(id);
      else await api.deleteEmployee(id);
      notify(t("ui_orgDeleted", { label }), "delete");
      load();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const toggleActive = async (kind, item) => {
    const activating = !item.is_active;
    if (
      !(await confirm({
        title: activating ? t("activateAction") : t("confirmDeactivateTitle"),
        message: activating
          ? t("confirmActivateMessage")
          : t("confirmDeactivateMessage"),
        confirmLabel: activating ? t("activateAction") : t("deactivateAction"),
        danger: !activating,
      }))
    )
      return;
    try {
      if (kind === "branch")
        await api.saveBranch({ is_active: activating }, item.id);
      else await api.saveDepartment({ is_active: activating }, item.id);
      notify(activating ? t("itemActivated") : t("itemDeactivated"), "success");
      load();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const editorConfig =
    editor && getEditorConfig(editor, branches, departments, offices, t);
  const save = async (values) => {
    if (!editor) return;
    try {
      if (editor.kind === "branch")
        await api.saveBranch(
          {
            name: String(values.name),
            code: String(values.code),
            description: String(values.description || ""),
          },
          editor.item?.id,
        );
      if (editor.kind === "department")
        await api.saveDepartment(
          {
            name: String(values.name),
            code: String(values.code),
            description: String(values.description || ""),
            branch_id: values.branch_id ? Number(values.branch_id) : null,
          },
          editor.item?.id,
        );
      if (editor.kind === "employee") {
        const department = departments.find(
          (item) => item.id === Number(values.department_id),
        );
        const officeId = Number(values.office_id) || undefined;
        const data = {
          name: String(values.name),
          email: String(values.email),
          job_title: String(values.job_title || ""),
          employee_number: String(values.employee_number || ""),
          employment_type: String(values.employment_type || "contract"),
          role: String(values.role),
          department_id: officeId
            ? undefined
            : Number(values.department_id) || undefined,
          branch_id: officeId ? undefined : department?.branch_id,
          office_id: officeId,
        };
        if (values.password) data.password = String(values.password);
        await api.saveEmployee(data, editor.item?.id);
      }
      notify(t("dataSaved"), "success");
      load();
    } catch (error) {
      notify(error.message, "error");
      throw error;
    }
  };
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("orgEyebrow")}</p>
          <h1 className="page-title">{t("orgTitle")}</h1>
          <p className="page-subtitle">{t("orgIntro")}</p>
        </div>
        {can("branches.create") && (
          <button
            className="btn btn-primary"
            onClick={() => setEditor({ kind: "branch" })}
          >
            <Building2 size={18} />
            {t("addBranch")}
          </button>
        )}
      </div>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <UnitSection
          title={t("branches")}
          items={branches.map((branch) => ({
            id: branch.id,
            name: branch.name,
            meta: `${branch.code} · ${branch.departments_count || 0} ${t("departments")}`,
            Icon: Building2,
            isActive: branch.is_active,
            edit: can("branches.update")
              ? () => setEditor({ kind: "branch", item: branch })
              : undefined,
            toggleActive: can("branches.update")
              ? () => toggleActive("branch", branch)
              : undefined,
            remove: can("branches.delete")
              ? () => remove("branch", branch.id)
              : undefined,
          }))}
        />
        <UnitSection
          title={t("departments")}
          action={
            can("departments.create")
              ? () => setEditor({ kind: "department" })
              : undefined
          }
          actionLabel={t("addDepartment")}
          items={departments.map((department) => ({
            id: department.id,
            name: department.name,
            meta: `${department.branch?.name || t("noBranchAdminLevel")} · ${department.users_count || 0} ${t("employees")}`,
            Icon: Users,
            isActive: department.is_active,
            edit: can("departments.update")
              ? () => setEditor({ kind: "department", item: department })
              : undefined,
            toggleActive: can("departments.update")
              ? () => toggleActive("department", department)
              : undefined,
            remove: can("departments.delete")
              ? () => remove("department", department.id)
              : undefined,
          }))}
        />
      </div>
      <section className="card overflow-hidden">
        <div className="card-head">
          <div>
            <h2 className="section-title">{t("employees")}</h2>
            <p className="text-xs text-muted">
              {employees.length} {t("withinManagement")}
            </p>
          </div>
          {can("employees.create") && (
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => setEditor({ kind: "employee" })}
            >
              {t("addEmployee")}
            </button>
          )}
        </div>
        <div className="w-full overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>{t("employee")}</th>
                <th>{t("employmentType")}</th>
                <th>{t("branch")}</th>
                <th>{t("department")}</th>
                <th>{t("actions")}</th>
              </tr>
            </thead>
            <tbody>
              {employees.map((employee) => (
                <tr key={employee.id}>
                  <td>
                    <b className="block font-semibold text-ink">
                      {employee.name}
                    </b>
                    <small className="block text-xs text-muted">
                      {employee.email}
                    </small>
                  </td>
                  <td>
                    {employee.employment_type
                      ? t(
                          employee.employment_type === "fixed"
                            ? "fixedEmployee"
                            : "contractEmployee",
                        )
                      : t("contractEmployee")}
                  </td>
                  <td>{employee.branch?.name}</td>
                  <td>{employee.department?.name}</td>
                  <td>
                    <div className="flex items-center gap-1">
                      {can("employees.update") && (
                        <button
                          className="btn-icon h-8 w-8"
                          onClick={() =>
                            setEditor({ kind: "employee", item: employee })
                          }
                        >
                          <Pencil size={15} />
                        </button>
                      )}
                      {can("employees.delete") && employee.id !== user.id && (
                        <button
                          className="btn-icon h-8 w-8 hover:text-danger-500"
                          onClick={() => remove("employee", employee.id)}
                        >
                          <Trash2 size={15} />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      {editor && editorConfig && (
        <ReusableFormModal
          {...editorConfig}
          onSubmit={save}
          onClose={() => setEditor(null)}
        />
      )}
    </div>
  );
}
