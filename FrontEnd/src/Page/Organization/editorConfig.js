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

export function getEditorConfig(editor, branches, departments, offices, t) {
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
    fields: [
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
        name: "department_id",
        label: t("department"),
        type: "select",
        options: selectableDepartments(departments, editor.item?.department_id, t),
      },
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
      department_id: editor.item?.department_id || "",
      office_id: editor.item?.office_id || "",
    },
  };
}
