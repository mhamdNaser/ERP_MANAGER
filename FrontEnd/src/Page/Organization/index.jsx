import { Building2, Pencil, Trash2, Users } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { FilterBar } from "../../Components/FilterBar";
import { ListPager } from "../../Components/ListPager";
import { ListSearch } from "../../Components/ListSearch";
import { ReusableFormModal } from "../../Components/ReusableFormModal";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { useConfirm } from "../../Provider/ConfirmContext";
import { getEditorConfig } from "./editorConfig";
import { employeeFilterDefinition, EMPTY_EMPLOYEE_FILTERS } from "./employeeFilters";
import { UnitSection } from "./components/UnitSection";

/** خمسة عناصر في الصفحة — والخادم هو من يقسّمها، لا المتصفح. */
const PER_PAGE = 5;

export function OrganizationPage({ user, notify }) {
  const { t } = useLanguage();

  // القوائم المعروضة: مرقَّمة ومبحوثة في الخادم.
  const [branches, setBranches] = useState({ data: [], meta: null });
  const [departments, setDepartments] = useState({ data: [], meta: null });
  const [employees, setEmployees] = useState({ data: [], meta: null });

  // القوائم الكاملة: تملأ القوائم المنسدلة في نماذج التحرير والفلاتر.
  const [options, setOptions] = useState({ branches: [], departments: [] });
  const [offices, setOffices] = useState([]);

  const [branchQuery, setBranchQuery] = useState({ search: "", page: 1 });
  const [departmentQuery, setDepartmentQuery] = useState({ search: "", page: 1 });
  const [employeeQuery, setEmployeeQuery] = useState({
    search: "",
    page: 1,
    ...EMPTY_EMPLOYEE_FILTERS,
  });

  const [editor, setEditor] = useState(null);
  const ref = useRef(notify);
  const can = (permission) => user.permissions?.includes(permission);
  const confirm = useConfirm();

  const loadBranches = useCallback(
    () =>
      api
        .organizationBranches({ ...branchQuery, per_page: PER_PAGE })
        .then(setBranches)
        .catch((error) => ref.current(error.message, "error")),
    [branchQuery],
  );

  const loadDepartments = useCallback(
    () =>
      api
        .organizationDepartments({ ...departmentQuery, per_page: PER_PAGE })
        .then(setDepartments)
        .catch((error) => ref.current(error.message, "error")),
    [departmentQuery],
  );

  const loadEmployees = useCallback(
    () =>
      api
        .employees({ ...employeeQuery, per_page: PER_PAGE })
        .then((data) => setEmployees({ data: data.employees, meta: data.meta }))
        .catch((error) => ref.current(error.message, "error")),
    [employeeQuery],
  );

  const loadOptions = useCallback(() => {
    api
      .organization()
      .then((data) => setOptions({ branches: data.branches, departments: data.departments }))
      .catch((error) => ref.current(error.message, "error"));
    if (can("offices.manage"))
      api
        .offices()
        .then(setOffices)
        .catch((error) => ref.current(error.message, "error"));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => { loadBranches(); }, [loadBranches]);
  useEffect(() => { loadDepartments(); }, [loadDepartments]);
  useEffect(() => { loadEmployees(); }, [loadEmployees]);
  useEffect(() => { loadOptions(); }, [loadOptions]);

  /** بعد أي تعديل: القوائم الثلاث والقوائم المنسدلة معاً، فالعدّادات تتغيّر. */
  const load = () => {
    loadBranches();
    loadDepartments();
    loadEmployees();
    loadOptions();
  };

  // تغيير البحث أو الفلتر يعيدنا إلى الصفحة الأولى: الصفحة الخامسة من
  // نتيجةٍ جديدة قد لا تكون موجودة أصلاً.
  const setEmployeeFilter = (key, value) =>
    setEmployeeQuery((current) => ({ ...current, [key]: value, page: 1 }));

  const filterDefinition = useMemo(
    () => employeeFilterDefinition(options, t),
    [options, t],
  );
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
  const canAssignCommunication =
    user?.permissions?.includes("tasks.communication.assign") === true;
  const editorConfig =
    editor &&
    getEditorConfig(
      editor,
      options.branches,
      options.departments,
      offices,
      t,
      canAssignCommunication,
    );
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
        const department = options.departments.find(
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
          // الفرع يُرسل كما اختاره المستخدم؛ وإن تركه فارغاً واختار قسماً
          // أُخذ فرع القسم — فمن لا قسم له (مدير فرع مثلاً) يبقى له فرع.
          branch_id: officeId
            ? undefined
            : Number(values.branch_id) || department?.branch_id || null,
          office_id: officeId,
          // لا تُرسل أصلاً ممن لا يملك سلطة التعيين، فلا يبدو الحفظ محاولةَ سحب.
          ...(canAssignCommunication
            ? { is_communication_officer: Boolean(values.is_communication_officer) }
            : {}),
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
          search={branchQuery.search}
          onSearch={(search) => setBranchQuery({ search, page: 1 })}
          searchPlaceholder={t("ui_searchBranches")}
          meta={branches.meta}
          onPage={(page) => setBranchQuery((current) => ({ ...current, page }))}
          items={branches.data.map((branch) => ({
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
          search={departmentQuery.search}
          onSearch={(search) => setDepartmentQuery({ search, page: 1 })}
          searchPlaceholder={t("ui_searchDepartments")}
          meta={departments.meta}
          onPage={(page) => setDepartmentQuery((current) => ({ ...current, page }))}
          action={
            can("departments.create")
              ? () => setEditor({ kind: "department" })
              : undefined
          }
          actionLabel={t("addDepartment")}
          items={departments.data.map((department) => ({
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
        <div className="card-head flex-wrap gap-2">
          <div>
            <h2 className="section-title">{t("employees")}</h2>
            <p className="text-xs text-muted">
              {employees.meta?.total ?? employees.data.length} {t("withinManagement")}
            </p>
          </div>
          <div className="flex flex-1 items-center justify-end gap-2">
            <ListSearch
              value={employeeQuery.search}
              onChange={(search) => setEmployeeFilter("search", search)}
              placeholder={t("ui_searchEmployees")}
            />
            {can("employees.create") && (
              <button
                className="btn btn-secondary btn-sm shrink-0"
                onClick={() => setEditor({ kind: "employee" })}
              >
                {t("addEmployee")}
              </button>
            )}
          </div>
        </div>
        <div className="border-b border-line px-4 py-3">
          <FilterBar
            definition={filterDefinition}
            values={employeeQuery}
            onChange={setEmployeeFilter}
            onReset={() =>
              setEmployeeQuery((current) => ({
                ...current,
                ...EMPTY_EMPLOYEE_FILTERS,
                page: 1,
              }))
            }
          />
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
              {employees.data.map((employee) => (
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
        {employees.data.length === 0 && (
          <p className="px-4 py-6 text-center text-xs text-muted">
            {t("ui_noSearchResults")}
          </p>
        )}
        <ListPager
          meta={employees.meta}
          onPage={(page) => setEmployeeQuery((current) => ({ ...current, page }))}
        />
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
