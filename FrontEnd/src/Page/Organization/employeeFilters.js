/** الفلاتر التي يفهمها مسار الموظفين في الخادم — مفاتيحها أسماء معاملاته. */
export const EMPTY_EMPLOYEE_FILTERS = {
  branch_id: "",
  department_id: "",
  role: "",
  employment_type: "",
  status: "",
};

/** مفاتيح ترجمة الأدوار كما هي في القاموس — لا تتبع نمطاً واحداً. */
const ROLE_LABEL_KEYS = {
  employee: "employeeRole",
  technician: "technician",
  department_head: "department_head",
  branch_manager: "branch_manager",
  general_manager: "general_manager",
  database_manager: "database_manager",
  office_manager: "officeManagerRole",
};

/** تعريف شريط الفلاتر: خياراته تُبنى من القوائم الكاملة لا من الصفحة المعروضة. */
export function employeeFilterDefinition(options, t) {
  return [
    {
      key: "branch_id",
      label: t("branch"),
      options: options.branches.map((branch) => ({ value: branch.id, label: branch.name })),
    },
    {
      key: "department_id",
      label: t("department"),
      options: options.departments.map((department) => ({
        value: department.id,
        label: department.name,
      })),
    },
    {
      key: "role",
      label: t("role"),
      options: Object.entries(ROLE_LABEL_KEYS).map(([role, key]) => ({
        value: role,
        label: t(key),
      })),
    },
    {
      key: "employment_type",
      label: t("employmentType"),
      options: [
        { value: "fixed", label: t("fixedEmployee") },
        { value: "contract", label: t("contractEmployee") },
      ],
    },
    {
      key: "status",
      label: t("status"),
      options: [
        { value: "active", label: t("statusActive") },
        { value: "inactive", label: t("statusInactive") },
      ],
    },
  ];
}
