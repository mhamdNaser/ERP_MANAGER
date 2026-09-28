import { SlidersHorizontal, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";

/**
 * شريط فلاتر مطويّ لقائمة.
 *
 * الفلاتر مخفيّة حتى تُطلب كي لا تزاحم القائمة نفسها، لكن ما هو مفعَّل منها
 * يبقى ظاهراً كرقائق تحت الشريط — فلا يحتار أحدٌ لماذا القائمة شبه فارغة.
 *
 * definition: [{ key, label, options: [{ value, label }] }]
 */
export function FilterBar({ definition, values, onChange, onReset }) {
  const { t } = useLanguage();
  const [open, setOpen] = useState(false);

  const active = definition
    .map((filter) => {
      const value = values[filter.key];
      if (!value) return null;
      const option = filter.options.find((item) => String(item.value) === String(value));
      return option ? { ...filter, option } : null;
    })
    .filter(Boolean);

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center gap-2">
        <button
          className={`btn btn-sm ${open || active.length ? "btn-primary" : "btn-secondary"}`}
          onClick={() => setOpen((shown) => !shown)}
          aria-expanded={open}
        >
          <SlidersHorizontal size={15} />
          {t("ui_filters")}
          {active.length > 0 && (
            <span className="badge badge-neutral h-5 min-w-5 justify-center px-1 text-[10px]">
              {active.length}
            </span>
          )}
        </button>
        {active.length > 0 && (
          <button className="btn btn-ghost btn-sm" onClick={onReset}>
            {t("ui_clearFilters")}
          </button>
        )}
      </div>

      {open && (
        <div className="grid grid-cols-1 gap-2 rounded-lg border border-line bg-subtle p-3 sm:grid-cols-2 lg:grid-cols-3">
          {definition.map((filter) => (
            <label key={filter.key} className="text-xs font-medium text-muted">
              {filter.label}
              <select
                className="select mt-1 h-9 text-xs"
                value={values[filter.key] ?? ""}
                onChange={(event) => onChange(filter.key, event.target.value)}
              >
                <option value="">{t("ui_filterAll")}</option>
                {filter.options.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
          ))}
        </div>
      )}

      {active.length > 0 && (
        <div className="flex flex-wrap items-center gap-1.5">
          {active.map((filter) => (
            <span
              key={filter.key}
              className="badge badge-brand gap-1 ps-2 pe-1 text-[11px]"
            >
              <span className="opacity-70">{filter.label}:</span>
              {filter.option.label}
              <button
                className="grid h-4 w-4 place-items-center rounded-full hover:bg-black/10"
                onClick={() => onChange(filter.key, "")}
                aria-label={t("ui_removeFilter", { label: filter.label })}
              >
                <X size={11} />
              </button>
            </span>
          ))}
        </div>
      )}
    </div>
  );
}
