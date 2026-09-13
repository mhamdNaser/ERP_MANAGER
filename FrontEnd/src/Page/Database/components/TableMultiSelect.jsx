import { useMemo } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { buildVisibleGroups } from "./tableGroups";

export function TableMultiSelect({ tables, selected, onChange }) {
  const { t } = useLanguage();
  const groups = useMemo(() => buildVisibleGroups(tables), [tables]);

  const toggle = (table) => {
    onChange(
      selected.includes(table)
        ? selected.filter((item) => item !== table)
        : [...selected, table],
    );
  };

  const toggleGroup = (groupTables, allSelected) => {
    if (allSelected) {
      onChange(selected.filter((item) => !groupTables.includes(item)));
    } else {
      onChange([...new Set([...selected, ...groupTables])]);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between text-xs text-muted">
        <span>
          {t("selectTables")}
          {" · "}
          {selected.length
            ? t("tablesSelectedCount", { count: selected.length })
            : t("noTablesSelected")}
        </span>
        <span className="flex items-center gap-2">
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={() => onChange(tables)}
          >
            {t("selectAllTables")}
          </button>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={() => onChange([])}
          >
            {t("clearSelection")}
          </button>
        </span>
      </div>
      <div className="grid max-h-72 grid-cols-1 gap-2 overflow-y-auto rounded border border-line bg-canvas p-2 sm:grid-cols-2">
        {groups.map((group) => {
          const selectedInGroup = group.tables.filter((table) =>
            selected.includes(table),
          );
          const allSelected = selectedInGroup.length === group.tables.length;
          return (
            <section
              key={group.key}
              className="flex flex-col gap-2 rounded border border-line bg-surface p-2.5"
            >
              <header className="flex items-center gap-2">
                <span className="inline-grid h-6 w-6 shrink-0 place-items-center rounded border border-line bg-subtle text-brand-500">
                  <group.icon size={13} />
                </span>
                <b className="min-w-0 flex-1 truncate text-xs font-semibold text-ink">
                  {t(group.labelKey)}
                </b>
                <span className="shrink-0 text-[11px] text-muted">
                  {selectedInGroup.length}/{group.tables.length}
                </span>
                <button
                  type="button"
                  className="shrink-0 text-[11px] font-semibold text-brand-600 hover:underline"
                  onClick={() => toggleGroup(group.tables, allSelected)}
                >
                  {allSelected ? t("clearGroupAction") : t("selectGroupAction")}
                </button>
              </header>
              <div className="flex flex-wrap gap-1.5">
                {group.tables.map((table) => {
                  const active = selected.includes(table);
                  return (
                    <button
                      type="button"
                      key={table}
                      onClick={() => toggle(table)}
                      aria-pressed={active}
                      className={`rounded border px-2 py-1 font-mono text-[11px] transition-colors ${
                        active
                          ? "border-brand-500 bg-brand-50 text-brand-700"
                          : "border-line bg-canvas text-muted hover:text-ink"
                      }`}
                    >
                      {table}
                    </button>
                  );
                })}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
