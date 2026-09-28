import { Building2, Landmark, Network, Users } from "lucide-react";
import { useEffect, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { OrgChartModal } from "./components/OrgChartModal";

export function OrgTreePage({ notify }) {
  const { t } = useLanguage();
  const [branches, setBranches] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [dragging, setDragging] = useState(null);
  const [preview, setPreview] = useState(false);
  const [overTarget, setOverTarget] = useState(null);

  const load = () => {
    api
      .organization()
      .then((data) => {
        setBranches(data.branches);
        setDepartments(data.departments);
      })
      .catch((error) => notify(error.message, "error"));
  };
  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps

  const reassign = async (departmentId, branchId) => {
    setOverTarget(null);
    setDragging(null);
    const department = departments.find((item) => item.id === departmentId);
    if (!department || department.branch_id === branchId) return;
    try {
      await api.saveDepartment({ branch_id: branchId }, departmentId);
      notify(t("orgTreeMoved"), "success");
      load();
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const departmentsOf = (branchId) =>
    departments.filter((department) => department.branch_id === branchId);

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("orgTreeEyebrow")}</p>
          <h1 className="page-title">{t("orgTreeTitle")}</h1>
          <p className="page-subtitle">{t("orgTreeIntro")}</p>
        </div>
        <button className="btn btn-secondary" onClick={() => setPreview(true)}>
          <Network size={16} />
          {t("orgChartPreview")}
        </button>
      </div>

      <section
        className={`card p-4 transition-colors ${
          overTarget === "admin" ? "border-brand-500 bg-brand-50" : ""
        }`}
        onDragOver={(event) => {
          event.preventDefault();
          setOverTarget("admin");
        }}
        onDragLeave={() => setOverTarget((current) => (current === "admin" ? null : current))}
        onDrop={() => dragging && reassign(dragging, null)}
      >
        <div className="mb-3 flex items-center gap-2">
          <span className="inline-grid h-9 w-9 shrink-0 place-items-center rounded border border-line bg-subtle text-brand-500">
            <Landmark className="h-4 w-4" />
          </span>
          <div>
            <b className="block text-[13px] font-semibold text-ink">
              {t("noBranchAdminLevel")}
            </b>
            <small className="block text-xs text-muted">{t("orgTreeAdminHint")}</small>
          </div>
        </div>
        <DepartmentChips
          items={departmentsOf(null)}
          dragging={dragging}
          onDragStart={setDragging}
          onDragEnd={() => setDragging(null)}
          t={t}
        />
      </section>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {branches.map((branch) => (
          <section
            key={branch.id}
            className={`card p-4 transition-colors ${
              branch.is_active === false ? "opacity-60" : ""
            } ${overTarget === branch.id ? "border-brand-500 bg-brand-50" : ""}`}
            onDragOver={(event) => {
              event.preventDefault();
              setOverTarget(branch.id);
            }}
            onDragLeave={() =>
              setOverTarget((current) => (current === branch.id ? null : current))
            }
            onDrop={() => dragging && reassign(dragging, branch.id)}
          >
            <div className="mb-3 flex items-center gap-2">
              <span className="inline-grid h-9 w-9 shrink-0 place-items-center rounded border border-line bg-subtle text-brand-500">
                <Building2 className="h-4 w-4" />
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <b className="truncate text-[13px] font-semibold text-ink">
                    {branch.name}
                  </b>
                  {branch.is_active === false && (
                    <span className="badge badge-neutral shrink-0">
                      {t("statusInactive")}
                    </span>
                  )}
                </div>
                <small className="block text-xs text-muted">{branch.code}</small>
              </div>
            </div>
            <DepartmentChips
              items={departmentsOf(branch.id)}
              dragging={dragging}
              onDragStart={setDragging}
              onDragEnd={() => setDragging(null)}
              t={t}
            />
          </section>
        ))}
      </div>

      {preview && (
        <OrgChartModal
          branches={branches}
          departments={departments}
          notify={notify}
          close={() => setPreview(false)}
        />
      )}
    </div>
  );
}

function DepartmentChips({ items, dragging, onDragStart, onDragEnd, t }) {
  if (!items.length) {
    return <p className="text-xs text-muted">{t("orgTreeEmptyDropzone")}</p>;
  }
  return (
    <div className="flex flex-wrap gap-2">
      {items.map((department) => (
        <div
          key={department.id}
          draggable
          onDragStart={() => onDragStart(department.id)}
          onDragEnd={onDragEnd}
          className={`flex cursor-grab items-center gap-1.5 rounded border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink ${
            dragging === department.id ? "opacity-50" : ""
          } ${department.is_active === false ? "opacity-60" : ""}`}
        >
          <Users size={13} className="text-muted" />
          {department.name}
          {department.is_active === false && (
            <span className="badge badge-neutral">{t("statusInactive")}</span>
          )}
        </div>
      ))}
    </div>
  );
}
