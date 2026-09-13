import { Save, ShieldCheck } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { PermissionGroupMatrix } from "./PermissionGroupMatrix";
import { buildPermissionGroups } from "./permissionGroups";
export function RolePermissionsTab({ notify }) {
  const { t } = useLanguage();
  const [roles, setRoles] = useState([]);
  const [active, setActive] = useState(0);
  useEffect(() => {
    api
      .rolesPermissions()
      .then(setRoles)
      .catch((e) => notify(e.message, "error"));
  }, [notify]);
  const role = roles[active];
  const groups = useMemo(
    () => buildPermissionGroups(role?.available_permissions || []),
    [role],
  );
  const toggle = (permission) =>
    setRoles((items) =>
      items.map((item, index) =>
        index === active
          ? {
              ...item,
              permissions: item.permissions.includes(permission)
                ? item.permissions.filter((p) => p !== permission)
                : [...item.permissions, permission],
            }
          : item,
      ),
    );
  const toggleGroup = (permissions, enable) =>
    setRoles((items) =>
      items.map((item, index) =>
        index === active
          ? {
              ...item,
              permissions: enable
                ? Array.from(new Set([...item.permissions, ...permissions]))
                : item.permissions.filter((p) => !permissions.includes(p)),
            }
          : item,
      ),
    );
  const save = async () => {
    try {
      await api.syncRolePermissions(role.id, role.permissions);
      notify(t("permissionsSaved"), "success");
    } catch (e) {
      notify(e.message, "error");
    }
  };
  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-[240px_1fr]">
      <aside className="card flex h-max flex-col gap-0.5 p-1">
        {roles.map((item, index) => (
          <button
            className={`flex w-full items-center gap-2.5 rounded border-s-2 px-3 py-2.5 text-start ${
              index === active
                ? "border-brand-500 bg-brand-50 text-brand-700"
                : "border-transparent text-muted hover:bg-canvas hover:text-ink"
            }`}
            key={item.id}
            onClick={() => setActive(index)}
          >
            <ShieldCheck size={17} className="shrink-0" />
            <span className="flex min-w-0 flex-col gap-0.5">
              <b className="truncate text-[13px] font-semibold">
                {t(item.name)}
              </b>
              <small className="truncate text-xs text-muted">
                {item.permissions.length} {t("activePermissions")}
              </small>
            </span>
          </button>
        ))}
      </aside>
      {role && (
        <section className="card">
          <header className="card-head">
            <div>
              <small className="block text-xs text-muted">
                {t("selectedRole")}
              </small>
              <h2 className="section-title">{t(role.name)}</h2>
            </div>
            <button className="btn btn-primary" onClick={save}>
              <Save size={17} />
              {t("savePermissions")}
            </button>
          </header>
          <PermissionGroupMatrix
            groups={groups}
            selected={role.permissions}
            onToggle={toggle}
            onToggleGroup={toggleGroup}
          />
        </section>
      )}
    </div>
  );
}
