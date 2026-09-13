import { Save, Search, UserRound } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { PermissionGroupMatrix } from "./PermissionGroupMatrix";
import { buildPermissionGroups } from "./permissionGroups";
export function UserPermissionsTab({ notify }) {
  const { t } = useLanguage();
  const [employees, setEmployees] = useState([]);
  const [availablePermissions, setAvailablePermissions] = useState([]);
  const [search, setSearch] = useState("");
  const [activeId, setActiveId] = useState(null);
  const [selected, setSelected] = useState([]);
  const [saving, setSaving] = useState(false);
  useEffect(() => {
    api
      .employees()
      .then((d) => setEmployees(d.employees))
      .catch((e) => notify(e.message, "error"));
    api
      .rolesPermissions()
      .then((roles) => setAvailablePermissions(roles[0]?.available_permissions || []))
      .catch((e) => notify(e.message, "error"));
  }, [notify]);
  const list = useMemo(
    () =>
      employees.filter(
        (item) =>
          item.name.toLowerCase().includes(search.toLowerCase()) ||
          (item.email || "").toLowerCase().includes(search.toLowerCase()),
      ),
    [employees, search],
  );
  const active = employees.find((item) => item.id === activeId);
  const groups = useMemo(
    () => buildPermissionGroups(availablePermissions),
    [availablePermissions],
  );
  const select = (employee) => {
    setActiveId(employee.id);
    setSelected(employee.direct_permissions || []);
  };
  const toggle = (permission) =>
    setSelected((items) =>
      items.includes(permission)
        ? items.filter((p) => p !== permission)
        : [...items, permission],
    );
  const toggleGroup = (permissions, enable) =>
    setSelected((items) =>
      enable
        ? Array.from(new Set([...items, ...permissions]))
        : items.filter((p) => !permissions.includes(p)),
    );
  const save = async () => {
    setSaving(true);
    try {
      const updated = await api.updateEmployeePermissions(active.id, selected);
      setEmployees((items) =>
        items.map((item) => (item.id === updated.id ? updated : item)),
      );
      notify(t("employeePermissionsSaved"), "success");
    } catch (e) {
      notify(e.message, "error");
    } finally {
      setSaving(false);
    }
  };
  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-[280px_1fr]">
      <aside className="card flex h-max flex-col gap-1 p-2">
        <label className="relative flex items-center">
          <Search className="pointer-events-none absolute start-3 h-4 w-4 text-muted" />
          <input
            className="input ps-9"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchEmployee")}
          />
        </label>
        <div className="flex max-h-[520px] flex-col gap-0.5 overflow-y-auto">
          {list.map((item) => (
            <button
              key={item.id}
              className={`flex w-full items-center gap-2.5 rounded border-s-2 px-3 py-2.5 text-start ${
                item.id === activeId
                  ? "border-brand-500 bg-brand-50 text-brand-700"
                  : "border-transparent text-muted hover:bg-canvas hover:text-ink"
              }`}
              onClick={() => select(item)}
            >
              <UserRound size={17} className="shrink-0" />
              <span className="flex min-w-0 flex-col gap-0.5">
                <b className="truncate text-[13px] font-semibold">
                  {item.name}
                </b>
                <small className="truncate text-xs text-muted">
                  {item.job_title}
                </small>
              </span>
            </button>
          ))}
        </div>
      </aside>
      {active && (
        <section className="card">
          <header className="card-head">
            <div>
              <small className="block text-xs text-muted">
                {t("employeePermissions")}
              </small>
              <h2 className="section-title">{active.name}</h2>
              <p className="mt-1 text-xs text-muted">
                {t("employeePermissionsHint")}
              </p>
            </div>
            <button
              className="btn btn-primary"
              onClick={save}
              disabled={saving}
            >
              <Save size={17} />
              {t("saveEmployeePermissions")}
            </button>
          </header>
          <PermissionGroupMatrix
            groups={groups}
            selected={selected}
            onToggle={toggle}
            onToggleGroup={toggleGroup}
            indicatorFor={(permission) =>
              (active.permissions || []).includes(permission) &&
              !selected.includes(permission) && (
                <em className="badge badge-neutral not-italic">
                  {t("permissionFromRole")}
                </em>
              )
            }
          />
        </section>
      )}
    </div>
  );
}
