function selectableBranches(branches, currentId) {
  return branches
    .filter((branch) => branch.is_active || branch.id === currentId)
    .map((branch) => ({ value: branch.id, label: branch.name }));
}

function selectableDepartments(departments, currentId, t) {
  return departments
    .filter((department) => department.is_active || department.id === currentId)
    .map((department) => ({
      value: department.id,
      label: `${department.name} · ${department.branch?.name || t("noBranchAdminLevel")}`,
    }));
}

/**
 * أقسام الفرع المختار وحدها؛ وبلا فرع تظهر أقسام الإدارة المباشرة.
 * القسم المحفوظ حالياً يبقى ظاهراً مهما كان الفرع، كي لا يفقده حفظٌ لموظف
 * سجلُّه غير متطابق أصلاً.
 */
function departmentsOfBranch(departments, branchId, currentId, t) {
  const belongs = (department) =>
    String(department.branch_id ?? "") === String(branchId ?? "");

  return selectableDepartments(
    departments.filter(
      (department) => belongs(department) || department.id === currentId,
    ),
    currentId,
    t,
  );
}

export function getEditorConfig(editor, branches, departments, offices, t, canAssignCommunication = false) {
  if (editor.kind === "branch")
    return {
      title: editor.item ? t("editBranch") : t("addBranch"),
      subtitle: t("branchFormHint"),
      fields: [
        { name: "name", label: t("branch"), required: true },
        { name: "code", label: t("code"), required: true },
        {
          name: "description",
          label: t("description"),
          type: "textarea",
          wide: true,
        },
      ],
      initial: {
        name: editor.item?.name || "",
        code: editor.item?.code || "",
        description: editor.item?.description || "",
      },
    };
  if (editor.kind === "department")
    return {
      title: editor.item ? t("editDepartment") : t("addDepartment"),
      subtitle: t("departmentFormHint"),
      fields: [
        { name: "name", label: t("department"), required: true },
        { name: "code", label: t("code"), required: true },
        {
          name: "branch_id",
          label: t("branch"),
          type: "select",
          options: [
            { value: "", label: t("noBranchAdminLevel") },
            ...selectableBranches(branches, editor.item?.branch_id),
          ],
        },
        {
          name: "description",
          label: t("description"),
          type: "textarea",
          wide: true,
        },
      ],
      initial: {
        name: editor.item?.name || "",
        code: editor.item?.code || "",
        branch_id: editor.item?.branch_id || "",
        description: editor.item?.description || "",
      },
    };
  return {
    title: editor.item ? t("editEmployee") : t("addEmployee"),
    subtitle: t("employeeFormHint"),
    // دالّة لا مصفوفة: قائمة الأقسام تتبع الفرع المختار لحظةَ العرض.
    fields: (values) => [
      { name: "name", label: t("employee"), required: true },
      { name: "email", label: t("email"), type: "email", required: true },
      { name: "job_title", label: t("jobTitle") },
      { name: "employee_number", label: t("employeeNumber") },
      {
        name: "employment_type",
        label: t("employmentType"),
        type: "select",
        required: true,
        options: [
          { value: "fixed", label: t("fixedEmployee") },
          { value: "contract", label: t("contractEmployee") },
        ],
      },
      {
        name: "role",
        label: t("role"),
        type: "select",
        required: true,
        options: [
          { value: "employee", label: t("employeeRole") },
          { value: "technician", label: t("technician") },
          { value: "department_head", label: t("departmentHead") },
          { value: "branch_manager", label: t("branchManager") },
          { value: "general_manager", label: t("generalManager") },
          { value: "database_manager", label: t("databaseManager") },
        ],
      },
      {
        name: "branch_id",
        label: t("branch"),
        type: "select",
        // تغيير الفرع يُفرغ القسم، فلا يبقى قسمٌ من فرعٍ آخر.
        resets: ["department_id"],
        options: [
          { value: "", label: t("noBranchAdminLevel") },
          ...selectableBranches(branches, editor.item?.branch_id),
        ],
      },
      {
        name: "department_id",
        label: t("department"),
        type: "select",
        options: [
          { value: "", label: t("noDepartment") },
          ...departmentsOfBranch(
            departments,
            values.branch_id,
            editor.item?.department_id,
            t,
          ),
        ],
      },
      // الخانة لمن يملك سلطة التعيين وحده؛ والخادم يتجاهلها من غيره أيضاً.
      ...(canAssignCommunication
        ? [
            {
              name: "is_communication_officer",
              label: t("communicationOfficer"),
              type: "checkbox",
              hint: t("communicationOfficerHint"),
              wide: true,
            },
          ]
        : []),
      {
        name: "office_id",
        label: t("office"),
        type: "select",
        options: offices.map((office) => ({
          value: office.id,
          label: office.name,
        })),
      },
      ...(!editor.item
        ? [
            {
              name: "password",
              label: t("password"),
              type: "password",
              required: true,
              wide: true,
            },
          ]
        : []),
    ],
    initial: {
      name: editor.item?.name || "",
      email: editor.item?.email || "",
      job_title: editor.item?.job_title || "",
      employee_number: editor.item?.employee_number || "",
      employment_type: editor.item?.employment_type || "contract",
      role: editor.item?.role || "employee",
      branch_id: editor.item?.branch_id || "",
      department_id: editor.item?.department_id || "",
      // الصفة صلاحية ممنوحة، فتُقرأ من صلاحيات الموظف لا من عمود.
      is_communication_officer:
        editor.item?.permissions?.includes("tasks.communication") || false,
      office_id: editor.item?.office_id || "",
    },
  };
}
