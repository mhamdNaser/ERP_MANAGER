import { useLanguage } from "../../../Provider/LanguageContext";
import { permissionDescription } from "../../../i18n/permissionDescriptions";
export function PermissionGroupMatrix({
  groups,
  selected,
  onToggle,
  onToggleGroup,
  indicatorFor,
}) {
  const { t, lang } = useLanguage();
  return (
    <div className="flex flex-col gap-4 p-4">
      {groups.map((group) => {
        const activeCount = group.permissions.filter((p) =>
          selected.includes(p),
        ).length;
        const allActive = activeCount === group.permissions.length;
        return (
          <section
            key={group.key}
            className="rounded border border-line bg-surface"
          >
            <header className="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-subtle px-3 py-2.5">
              <b className="text-[13px] font-semibold text-ink">
                {t(group.labelKey)}
              </b>
              <div className="flex items-center gap-2">
                <small className="text-xs text-muted">
                  {t("groupActiveCount", {
                    active: activeCount,
                    total: group.permissions.length,
                  })}
                </small>
                <button
                  type="button"
                  className="text-xs font-semibold text-brand-500 hover:text-brand-600"
                  onClick={() => onToggleGroup(group.permissions, !allActive)}
                >
                  {allActive ? t("clearAllGroup") : t("selectAllGroup")}
                </button>
              </div>
            </header>
            <div className="grid grid-cols-1 gap-2 p-3 md:grid-cols-2 xl:grid-cols-3">
              {group.permissions.map((permission) => (
                <label
                  key={permission}
                  className={`flex cursor-pointer items-start gap-3 rounded border p-3 ${
                    selected.includes(permission)
                      ? "border-brand-200 bg-brand-50"
                      : "border-line bg-surface"
                  }`}
                >
                  <input
                    type="checkbox"
                    className="sr-only"
                    checked={selected.includes(permission)}
                    onChange={() => onToggle(permission)}
                  />
                  <span
                    className={`mt-0.5 h-4 w-4 shrink-0 rounded border ${
                      selected.includes(permission)
                        ? "border-brand-500 bg-brand-500"
                        : "border-line-strong bg-white"
                    }`}
                  />
                  <div className="flex min-w-0 flex-col gap-1">
                    <span className="flex flex-wrap items-center gap-1.5">
                      <b className="font-mono text-xs break-all text-ink">
                        {permission}
                      </b>
                      {indicatorFor?.(permission)}
                    </span>
                    <small className="text-xs text-muted">
                      {permissionDescription(
                        permission,
                        lang,
                        t("permissionToggleHint"),
                      )}
                    </small>
                  </div>
                </label>
              ))}
            </div>
          </section>
        );
      })}
    </div>
  );
}
